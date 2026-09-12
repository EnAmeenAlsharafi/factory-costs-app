<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'DIRECT_CUSTOMER',
                'name_ar' => 'عميل مباشر',
                'name_en' => 'Direct Customer',
                'is_active' => true,
            ],
            [
                'code' => 'WHOLESALE_CUSTOMER',
                'name_ar' => 'عميل جملة',
                'name_en' => 'Wholesale Customer',
                'is_active' => true,
            ],
            [
                'code' => 'SADIR_STORE',
                'name_ar' => 'متجر سدير',
                'name_en' => 'Sadir Store',
                'is_active' => true,
            ],
            [
                'code' => 'BUSINESS_CUSTOMER',
                'name_ar' => 'عميل تجاري',
                'name_en' => 'Business Customer',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            CustomerType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
