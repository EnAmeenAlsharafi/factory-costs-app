<?php

namespace App\Services\Sales;

use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Models\SalesImportRow;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads a Salla Excel/CSV order export (one row per order line, order-level
 * fields repeated on every line of that order) and stages it as a
 * SalesImportBatch + one SalesImportRow per order. Pure parse-and-stage:
 * never creates a CustomerOrder (see SalesImportConfirmService).
 */
class SalesImportParserService
{
    /**
     * Column header (as it appears in the Salla export) -> internal field name.
     * Order-level fields are read once per order (from its first line); line
     * fields are collected into that order's `lines` array. Every mapped
     * header's raw value is also kept verbatim for audit.
     */
    private const HEADER_MAP = [
        // Order identity & status
        'رقم الطلب' => 'external_order_reference',
        'رقم مرجع الطلب' => 'external_order_internal_id',
        'حالة الطلب' => 'order_status',
        'تاريخ الطلب' => 'order_date',

        // Customer
        'اسم العميل' => 'customer_name',
        'رقم الجوال' => 'customer_mobile',

        // Delivery
        'المدينة' => 'city',
        'مدينة الشحن' => 'shipping_city',
        'الحي' => 'district',
        'عنوان الشحن' => 'shipping_address',
        'رابط موقع العميل على الخريطة' => 'map_url',
        'طريقة الشحن' => 'shipping_method',
        'رقم البوليصة' => 'tracking_number',
        'رابط تتبع الطلب' => 'tracking_url',
        'ملاحظات العميل' => 'customer_notes',

        // Payment
        'طريقة الدفع' => 'payment_method',
        'حالة الدفع' => 'payment_status',
        'رقم مرجع عملية الدفع' => 'payment_reference',

        // Line item
        'اسم المنتج' => 'product_name',
        'SKU' => 'sku',
        'الكمية' => 'quantity',
        'سعر المنتج قبل الخصم' => 'unit_price',
        'الخصم (على مستوى المنتج)' => 'line_discount',
        'الضريبة (على مستوى المنتج)' => 'line_tax',
        'الرقم المرجعي (على مستوى المنتج) بالطلب' => 'external_line_reference',

        // Order totals (cross-check only)
        'مجموع السلة' => 'cart_total',
        'الضريبة' => 'order_tax',
    ];

    private const REQUIRED_HEADERS = ['رقم الطلب', 'اسم المنتج', 'الكمية'];

    public function parse(string $filePath, string $originalFileName, SalesChannel $salesChannel, User $uploader): SalesImportBatch
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new Exception('الملف فارغ أو غير قابل للقراءة.');
        }

        $header = array_shift($rows);
        $columnIndex = [];
        foreach ($header as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $columnIndex[$name] = $i;
            }
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! array_key_exists($required, $columnIndex)) {
                throw new Exception("عمود إلزامي مفقود في الملف: \"{$required}\".");
            }
        }

        $ordersByReference = [];

        foreach ($rows as $row) {
            if (self::isBlankRow($row)) {
                continue;
            }

            $fields = $this->mapRow($row, $columnIndex);

            $orderRef = $fields['external_order_reference'] ?? null;
            if (! $orderRef) {
                continue;
            }

            if (! isset($ordersByReference[$orderRef])) {
                $orderLevel = $fields;
                unset($orderLevel['product_name'], $orderLevel['sku'], $orderLevel['quantity'], $orderLevel['unit_price'], $orderLevel['line_discount'], $orderLevel['line_tax'], $orderLevel['external_line_reference']);
                $orderLevel['lines'] = [];
                $ordersByReference[$orderRef] = $orderLevel;
            }

            $ordersByReference[$orderRef]['lines'][] = [
                'product_name' => $fields['product_name'] ?? null,
                'sku' => $fields['sku'] ?? null,
                'quantity' => $fields['quantity'] ?? null,
                'unit_price' => $fields['unit_price'] ?? null,
                'line_discount' => $fields['line_discount'] ?? null,
                'line_tax' => $fields['line_tax'] ?? null,
                'external_line_reference' => $fields['external_line_reference'] ?? null,
            ];
        }

        return DB::transaction(function () use ($filePath, $originalFileName, $salesChannel, $uploader, $ordersByReference) {
            $batch = SalesImportBatch::create([
                'file_name' => $originalFileName,
                'file_hash' => hash_file('sha256', $filePath),
                'sales_channel_id' => $salesChannel->id,
                'uploaded_by_user_id' => $uploader->id,
                'status' => 'UPLOADED',
            ]);

            foreach ($ordersByReference as $orderRef => $data) {
                SalesImportRow::create([
                    'import_batch_id' => $batch->id,
                    'external_order_reference' => $orderRef,
                    'raw_data' => $data,
                ]);
            }

            return $batch->fresh('rows');
        });
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnIndex
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $columnIndex): array
    {
        $fields = [];

        foreach (self::HEADER_MAP as $header => $field) {
            if (! array_key_exists($header, $columnIndex)) {
                continue;
            }

            $raw = $row[$columnIndex[$header]] ?? null;
            $raw = $raw === null ? null : (string) $raw;

            $fields[$field] = match ($field) {
                'customer_mobile' => SallaValueNormalizer::normalizeMobile($raw),
                default => SallaValueNormalizer::nullIfPlaceholder($raw),
            };
        }

        return $fields;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private static function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
