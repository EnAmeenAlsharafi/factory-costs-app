<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DocumentSequence;
use App\Models\FinishedGoodsMovement;
use App\Models\FinishedGoodsReceipt;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingTemplate;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialReceipt;
use App\Models\MaterialReturn;
use App\Models\ProductConfiguration;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionReworkAction;
use App\Models\ProductionRouting;
use App\Models\ProductionWasteRecord;
use App\Models\ProductModel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRfq;
use App\Models\QualityIncident;
use App\Models\Quotation;
use App\Models\SemiFinishedComponent;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Generate next sequential purchase request number (e.g. PRQ-2026-000001)
     */
    public static function generatePurchaseRequestNumber(): string
    {
        return static::generateYearlyCode(PurchaseRequest::class, 'request_number', 'PRQ');
    }

    /**
     * Generate next sequential RFQ number (e.g. RFQ-2026-000001)
     */
    public static function generateRfqNumber(): string
    {
        return static::generateYearlyCode(PurchaseRfq::class, 'rfq_number', 'RFQ');
    }

    /**
     * Generate next sequential supplier quotation number (e.g. SQT-2026-000001)
     */
    public static function generateSupplierQuotationNumber(): string
    {
        return static::generateYearlyCode(SupplierQuotation::class, 'supplier_quotation_number', 'SQT');
    }

    /**
     * Generate next sequential purchase order number (e.g. PO-2026-000001)
     */
    public static function generatePurchaseOrderNumber(): string
    {
        return static::generateYearlyCode(PurchaseOrder::class, 'purchase_order_number', 'PO');
    }

    /**
     * Generate next sequential finished goods receipt number (e.g. FGR-2026-000001)
     */
    public static function generateFinishedGoodsReceiptNumber(): string
    {
        return static::generateYearlyCode(FinishedGoodsReceipt::class, 'receipt_number', 'FGR');
    }

    /**
     * Generate next sequential finished goods movement number (e.g. FGM-2026-000001)
     */
    public static function generateFinishedGoodsMovementNumber(): string
    {
        return static::generateYearlyCode(FinishedGoodsMovement::class, 'movement_number', 'FGM');
    }

    /**
     * Generate next sequential delivery order number (e.g. DEL-2026-000001)
     */
    public static function generateDeliveryNumber(): string
    {
        return static::generateYearlyCode(DeliveryOrder::class, 'delivery_number', 'DEL');
    }

    /**
     * Generate next sequential customer return number (e.g. CRN-2026-000001)
     */
    public static function generateCustomerReturnNumber(): string
    {
        return static::generateYearlyCode(CustomerReturn::class, 'return_number', 'CRN');
    }

    /**
     * Generate next sequential customer payment number (e.g. PAY-2026-000001)
     */
    public static function generateCustomerPaymentNumber(): string
    {
        return static::generateYearlyCode(CustomerPayment::class, 'payment_number', 'PAY');
    }

    /**
     * Generate next sequential material request number (e.g. PMR-2026-000001)
     */
    public static function generateRequestNumber(): string
    {
        return static::generateYearlyCode(ProductionMaterialRequest::class, 'request_number', 'PMR');
    }

    /**
     * Generate next sequential quality incident number (e.g. QIN-2026-000001)
     */
    public static function generateIncidentNumber(): string
    {
        return static::generateYearlyCode(QualityIncident::class, 'incident_number', 'QIN');
    }

    /**
     * Generate next sequential waste record number (e.g. WST-2026-000001)
     */
    public static function generateWasteNumber(): string
    {
        return static::generateYearlyCode(ProductionWasteRecord::class, 'waste_number', 'WST');
    }

    /**
     * Generate next sequential rework action number (e.g. RWK-2026-000001)
     */
    public static function generateReworkNumber(): string
    {
        return static::generateYearlyCode(ProductionReworkAction::class, 'rework_number', 'RWK');
    }

    /**
     * Generic yearly code generator (PREFIX-YYYY-XXXXXX)
     */
    protected static function generateYearlyCode(string $modelClass, string $column, string $prefix): string
    {
        $year = now()->format('Y');
        $fullPrefix = "{$prefix}-{$year}";

        return static::formatCode(
            $fullPrefix,
            static::nextSequenceValue($modelClass, $column, $fullPrefix, $prefix, $year)
        );
    }

    /**
     * Generate next sequential production order number (e.g. PRO-2026-000001)
     */
    public static function generateProductionOrderNumber(): string
    {
        return static::generateYearlyCode(ProductionOrder::class, 'production_order_number', 'PRO');
    }

    /**
     * Generate next sequential routing code (e.g. RTG-000001)
     */
    public static function generateRoutingCode(): string
    {
        return static::generateCode(ProductionRouting::class, 'routing_code', 'RTG');
    }

    /**
     * Generate next sequential receipt number (e.g. REC-2026-000001)
     */
    public static function generateReceiptNumber(): string
    {
        return static::generateYearlyCode(MaterialReceipt::class, 'receipt_number', 'REC');
    }

    /**
     * Generate next sequential issue number (e.g. ISS-2026-000001)
     */
    public static function generateIssueNumber(): string
    {
        return static::generateYearlyCode(MaterialIssue::class, 'issue_number', 'ISS');
    }

    /**
     * Generate next sequential return number (e.g. RET-2026-000001)
     */
    public static function generateReturnNumber(): string
    {
        return static::generateYearlyCode(MaterialReturn::class, 'return_number', 'RET');
    }

    /**
     * Generate next sequential adjustment number (e.g. ADJ-2026-000001)
     */
    public static function generateAdjustmentNumber(): string
    {
        return static::generateYearlyCode(InventoryAdjustment::class, 'adjustment_number', 'ADJ');
    }

    /**
     * Generate next sequential inventory lot code (e.g. LOT-000001)
     */
    public static function generateLotCode(): string
    {
        return static::generateCode(InventoryLot::class, 'lot_code', 'LOT');
    }

    /**
     * Generate next sequential inventory movement number (e.g. MOV-000001)
     */
    public static function generateMovementNumber(): string
    {
        return static::generateCode(InventoryMovement::class, 'movement_number', 'MOV');
    }

    /**
     * Generate next sequential product model code (e.g. MOD-000001)
     */
    public static function generateModelCode(): string
    {
        return static::generateCode(ProductModel::class, 'model_code', 'MOD');
    }

    /**
     * Generate next sequential product configuration code (e.g. CFG-000001)
     */
    public static function generateConfigurationCode(): string
    {
        return static::generateCode(ProductConfiguration::class, 'configuration_code', 'CFG');
    }

    /**
     * Generate next sequential manufacturing recipe code (e.g. RCP-000001)
     */
    public static function generateRecipeCode(): string
    {
        return static::generateCode(ManufacturingRecipe::class, 'recipe_code', 'RCP');
    }

    /**
     * Generate next sequential semi-finished component code (e.g. SFC-000001)
     */
    public static function generateComponentCode(): string
    {
        return static::generateCode(SemiFinishedComponent::class, 'component_code', 'SFC');
    }

    /**
     * Generate next sequential manufacturing template code (e.g. TPL-000001)
     */
    public static function generateTemplateCode(): string
    {
        return static::generateCode(ManufacturingTemplate::class, 'template_code', 'TPL');
    }

    /**
     * Generate next sequential quotation number (e.g. QUO-2026-000001)
     */
    public static function generateQuotationNumber(): string
    {
        return static::generateYearlyCode(Quotation::class, 'quotation_number', 'QUO');
    }

    /**
     * Generate next sequential customer order number (e.g. ORD-2026-000001)
     */
    public static function generateOrderNumber(): string
    {
        return static::generateYearlyCode(CustomerOrder::class, 'order_number', 'ORD');
    }

    public static function generateMaterialCode(): string
    {
        return static::generateCode(Material::class, 'code', 'MAT');
    }

    public static function generateCustomerCode(): string
    {
        return static::generateCode(Customer::class, 'customer_code', 'CUS');
    }

    public static function generateSupplierCode(): string
    {
        return static::generateCode(Supplier::class, 'supplier_code', 'SUP');
    }

    /**
     * Generic concurrency-safe code generator parsing numeric suffix.
     */
    protected static function generateCode(string $modelClass, string $column, string $prefix): string
    {
        return static::formatCode(
            $prefix,
            static::nextSequenceValue($modelClass, $column, $prefix, $prefix)
        );
    }

    protected static function nextSequenceValue(
        string $modelClass,
        string $column,
        string $codePrefix,
        string $sequenceKey,
        string $periodKey = ''
    ): int {
        return DB::transaction(function () use ($modelClass, $column, $codePrefix, $sequenceKey, $periodKey) {
            $existingMax = $modelClass::query()
                ->where($column, 'like', $codePrefix.'-%')
                ->pluck($column)
                ->map(function ($value) use ($codePrefix) {
                    $suffix = substr((string) $value, strlen($codePrefix) + 1);

                    return ctype_digit($suffix) ? (int) $suffix : 0;
                })
                ->max() ?? 0;

            DocumentSequence::query()->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'period_key' => $periodKey,
                'current_value' => $existingMax,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('sequence_key', $sequenceKey)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sequence->current_value < $existingMax) {
                $sequence->current_value = $existingMax;
            }

            $sequence->current_value++;
            $sequence->save();

            return $sequence->current_value;
        }, 5);
    }

    protected static function formatCode(string $prefix, int $number): string
    {
        return $prefix.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
