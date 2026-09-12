<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustmentReason;
use Illuminate\Database\Seeder;

class InventoryAdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            [
                'code' => 'PHYSICAL_COUNT_VARIANCE',
                'name_ar' => 'فارق جرد فعلي',
                'name_en' => 'Physical Count Variance',
            ],
            [
                'code' => 'DAMAGED_IN_WAREHOUSE',
                'name_ar' => 'تلف داخلي بالمستودع',
                'name_en' => 'Damaged in Warehouse',
            ],
            [
                'code' => 'DATA_CORRECTION',
                'name_ar' => 'تصحيح قيد خطأ',
                'name_en' => 'Data Entry Correction',
            ],
            [
                'code' => 'OPENING_STOCK_MIGRATION',
                'name_ar' => 'رصيد مخزون إفتتاحي',
                'name_en' => 'Opening Balance Migration',
            ],
            [
                'code' => 'OTHER',
                'name_ar' => 'تسوية أخرى',
                'name_en' => 'Other Adjustment',
            ],
        ];

        foreach ($reasons as $reason) {
            InventoryAdjustmentReason::updateOrCreate(
                ['code' => $reason['code']],
                [
                    'name_ar' => $reason['name_ar'],
                    'name_en' => $reason['name_en'],
                    'is_active' => true,
                ]
            );
        }
    }
}
