<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['code' => 'RAW_MATERIALS'],
            [
                'name_ar' => 'مستودع المواد الخام',
                'name_en' => 'Raw Materials Warehouse',
                'description' => 'المستودع الرئيسي لاستلام وتخزين الأخشاب، الإسفنج، الأقمشة، ومستلزمات الإنتاج.',
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'WIP'],
            [
                'name_ar' => 'مخزن الإنتاج تحت التشغيل (WIP)',
                'name_en' => 'Work-In-Progress Store',
                'description' => 'مخزن حركات الإنتاج والأجزاء نصف المصنعة (محجوز للمراحل القادمة).',
                'is_active' => false,
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'FINISHED_GOODS'],
            [
                'name_ar' => 'مستودع المنتجات التامة',
                'name_en' => 'Finished Goods Warehouse',
                'description' => 'مستودع تخزين وتسليم المنتجات الجاهزة والطلبانيات.',
                'is_active' => true,
            ]
        );
    }
}
