<?php

namespace App\Services\Sales;

use App\Models\Material;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReadyStockProductImportService
{
    public function __construct(protected ReadyStockProductService $products) {}

    /** @return array{rows: array<int, array<string, mixed>>, skipped: int} */
    public function preview(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);

        try {
            $data = $workbook->getActiveSheet()->toArray(null, false, false, false);
        } finally {
            $workbook->disconnectWorksheets();
        }

        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($data) ?? []);
        foreach (['No.', 'النوع', 'أسم المنتج', 'نوع المنتج', 'رمز المنتج sku', 'السعر المخفض', 'الكمية المتوفرة', 'حالة المنتج'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw ValidationException::withMessages(['import_file' => "عمود إلزامي مفقود في ملف المنتجات: {$required}"]);
            }
        }

        $rows = [];
        $skipped = 0;
        $parentRowNumber = null;
        foreach ($data as $index => $values) {
            $source = array_combine($headers, $values);
            $rowType = trim((string) ($source['النوع'] ?? ''));
            if ($rowType === 'منتج') {
                $parentRowNumber = $index + 2;
            } elseif ($rowType === 'خيار' && isset($rows[$parentRowNumber])) {
                $rows[$parentRowNumber]['has_ready_options'] = true;
            }
            if (trim((string) ($source['النوع'] ?? '')) !== 'منتج' || trim((string) ($source['نوع المنتج'] ?? '')) !== 'منتج جاهز') {
                $skipped++;

                continue;
            }

            $status = $this->text($source['حالة المنتج'] ?? null);
            $quantity = $this->text($source['الكمية المتوفرة'] ?? null);
            if (in_array(mb_strtolower((string) $status), ['مخفي', 'مخفية', 'مخفيه', 'hidden'], true)
                || (is_numeric($quantity) && (float) $quantity === 0.0)) {
                $skipped++;

                continue;
            }

            $row = [
                'row_number' => $index + 2,
                'has_ready_options' => false,
                'salla_product_id' => $this->text($source['No.'] ?? null),
                'name_ar' => $this->text($source['أسم المنتج'] ?? null),
                'sales_sku' => $this->text($source['رمز المنتج sku'] ?? null),
                'sales_image_url' => $this->firstImage($source['صورة المنتج'] ?? null),
                'salla_category' => $this->text($source['تصنيف المنتج'] ?? null),
                'salla_status' => $status,
                'sales_price' => $this->text($source['السعر المخفض'] ?? null),
                'sales_reference_cost' => $this->text($source['سعر التكلفة'] ?? null),
                'salla_stock_quantity' => $quantity,
            ];

            $validator = Validator::make($row, [
                'salla_product_id' => ['required', 'regex:/^[0-9]+$/', 'max:100'],
                'name_ar' => ['required', 'string', 'max:255'],
                'sales_sku' => ['nullable', 'string', 'max:100'],
                'sales_image_url' => ['nullable', 'url:https', 'max:2048'],
                'salla_category' => ['nullable', 'string', 'max:255'],
                'salla_status' => ['nullable', 'string', 'max:100'],
                'sales_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
                'sales_reference_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
                'salla_stock_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            ], [
                'sales_price.required' => 'السعر المخفض مفقود؛ أكمله قبل الاستيراد.',
                'sales_price.numeric' => 'السعر المخفض يجب أن يكون رقمًا.',
                'sales_price.min' => 'السعر المخفض يجب ألا يكون سالبًا.',
            ]);
            $row['error'] = $validator->fails() ? 'بيانات غير صالحة: '.$validator->errors()->first() : null;
            $rows[$index + 2] = $row;
        }

        $idCounts = array_count_values(array_filter(array_column($rows, 'salla_product_id')));
        $skuCounts = array_count_values(array_filter(array_map(
            fn (array $row): ?string => SallaValueNormalizer::normalizeMatchValue($row['sales_sku']), $rows
        )));

        $existingMaterials = Material::query()->whereIn('salla_product_id', array_column($rows, 'salla_product_id'))
            ->orWhereIn(DB::raw('LOWER(TRIM(sales_sku))'), array_keys($skuCounts))->get();

        foreach ($rows as &$row) {
            $row['action'] = 'إضافة';
            if ($row['error']) {
                continue;
            }
            $normalizedSku = SallaValueNormalizer::normalizeMatchValue($row['sales_sku']);
            if (($idCounts[$row['salla_product_id']] ?? 0) > 1 || ($normalizedSku && ($skuCounts[$normalizedSku] ?? 0) > 1)) {
                $row['error'] = 'رقم المنتج أو SKU مكرر داخل الملف.';

                continue;
            }
            try {
                $existing = $this->resolveExisting($row, candidates: $existingMaterials);
                $row['action'] = $existing ? 'تحديث' : 'إضافة';
            } catch (ValidationException $exception) {
                $row['error'] = $exception->validator->errors()->first();
            }
        }
        unset($row);

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /** @param array<int, int> $selectedRows
     * @return array{created: int, updated: int}
     */
    public function import(string $path, array $selectedRows): array
    {
        $preview = $this->preview($path);

        return DB::transaction(function () use ($preview, $selectedRows) {
            $counts = ['created' => 0, 'updated' => 0];
            $unit = UnitOfMeasure::active()->where('code', 'PIECE')->firstOrFail();
            foreach (array_unique($selectedRows) as $rowNumber) {
                $row = $preview['rows'][$rowNumber] ?? null;
                if (! $row || $row['error']) {
                    throw ValidationException::withMessages(['selected_rows' => "تعذر استيراد الصف {$rowNumber}: ".($row['error'] ?? 'صف غير صالح.')]);
                }

                $existing = $this->resolveExisting($row, true);
                unset($row['row_number'], $row['error'], $row['action']);
                $row['sales_sku'] = $row['sales_sku'] ?? $existing?->sales_sku;
                $row['sales_image_url'] = $row['sales_image_url'] ?? $existing?->sales_image_url;
                $row['has_ready_options'] = $row['has_ready_options'] || ($existing?->has_ready_options ?? false);
                $row['sales_display_name'] = $row['name_ar'];
                $row['base_unit_id'] = $unit->id;
                if (! $existing) {
                    $row['is_active'] = true;
                }

                $this->products->save($row, $existing);
                $counts[$existing ? 'updated' : 'created']++;
            }

            return $counts;
        });
    }

    /** @param array<string, mixed> $row
     * @param  Collection<int, Material>|null  $candidates
     */
    protected function resolveExisting(array $row, bool $lock = false, ?Collection $candidates = null): ?Material
    {
        if ($candidates !== null) {
            $matches = $candidates->filter(fn (Material $material): bool => $material->salla_product_id === $row['salla_product_id']
                || ($row['sales_sku'] && mb_strtolower(trim((string) $material->sales_sku)) === mb_strtolower($row['sales_sku'])));
        } else {
            $query = Material::query()->where(function ($query) use ($row) {
                $query->where('salla_product_id', $row['salla_product_id']);
                if ($row['sales_sku']) {
                    $query->orWhereRaw('LOWER(TRIM(sales_sku)) = ?', [mb_strtolower($row['sales_sku'])]);
                }
            });
            if ($lock) {
                $query->lockForUpdate();
            }
            $matches = $query->get();
        }
        $material = $matches->first();
        if ($matches->count() > 1 || ($material && (! $material->is_sellable_ready_product || ($material->salla_product_id && $material->salla_product_id !== $row['salla_product_id'])))) {
            throw ValidationException::withMessages(['import_file' => 'تعارض بين رقم منتج سلة وSKU أو مادة غير جاهزة. راجع بيانات المنتج قبل الاستيراد.']);
        }

        return $material;
    }

    protected function firstImage(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }
        $images = preg_split('/\s*[,،\r\n]+\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return $this->text($images[0] ?? null);
    }

    protected function text(mixed $value): ?string
    {
        return SallaValueNormalizer::nullIfPlaceholder($value === null ? null : trim((string) $value));
    }
}
