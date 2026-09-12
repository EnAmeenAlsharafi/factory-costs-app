<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'code' => 'METER',
                'name_ar' => 'متر طولي',
                'name_en' => 'Linear Meter',
                'symbol' => 'م',
                'unit_type' => 'length',
                'allows_decimal' => true,
                'decimal_precision' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'BOARD',
                'name_ar' => 'لوح خشب',
                'name_en' => 'Wood Board',
                'symbol' => 'لوح',
                'unit_type' => 'area',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'PIECE',
                'name_ar' => 'حبة / قطعة',
                'name_en' => 'Piece',
                'symbol' => 'حبة',
                'unit_type' => 'count',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'CARTON',
                'name_ar' => 'كرتون',
                'name_en' => 'Carton',
                'symbol' => 'كرتون',
                'unit_type' => 'packaging',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'PACK',
                'name_ar' => 'عبوة',
                'name_en' => 'Pack',
                'symbol' => 'عبوة',
                'unit_type' => 'packaging',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'SHEET',
                'name_ar' => 'شريحة / صفيحة',
                'name_en' => 'Sheet',
                'symbol' => 'شريحة',
                'unit_type' => 'area',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'KG',
                'name_ar' => 'كيلوجرام',
                'name_en' => 'Kilogram',
                'symbol' => 'كجم',
                'unit_type' => 'weight',
                'allows_decimal' => true,
                'decimal_precision' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'LITER',
                'name_ar' => 'لتر',
                'name_en' => 'Liter',
                'symbol' => 'لتر',
                'unit_type' => 'volume',
                'allows_decimal' => true,
                'decimal_precision' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'ROLL',
                'name_ar' => 'رول قماش',
                'name_en' => 'Fabric Roll',
                'symbol' => 'رول',
                'unit_type' => 'packaging',
                'allows_decimal' => false,
                'decimal_precision' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }
}
