<?php

namespace Database\Seeders;

use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $channels = [
            [
                'code' => 'SADIR_STORE',
                'name_ar' => 'متجر سدير',
                'name_en' => 'Sadir Store',
                'description' => 'طلبات متجر سدير الإلكتروني (يمثل حوالي 60% من حجم الإنتاج).',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'WHOLESALE',
                'name_ar' => 'الجملة',
                'name_en' => 'Wholesale',
                'description' => 'مبيعات معارض ومحلات الأثاث والتجار بالجملة.',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'DIRECT',
                'name_ar' => 'عميل مباشر',
                'name_en' => 'Direct Customer',
                'description' => 'مبيعات الأفراد والمشاريع المباشرة من المصنع.',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'CUSTOM',
                'name_ar' => 'طلب خاص',
                'name_en' => 'Custom Order',
                'description' => 'طلبات التصاميم والمواصفات المخصصة والاستثنائية.',
                'sort_order' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($channels as $channel) {
            SalesChannel::updateOrCreate(
                ['code' => $channel['code']],
                $channel
            );
        }
    }
}
