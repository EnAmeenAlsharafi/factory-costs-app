<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sadirType = CustomerType::where('code', 'SADIR_STORE')->first();
        $sadirChannel = SalesChannel::where('code', 'SADIR_STORE')->first();

        if ($sadirType && $sadirChannel) {
            Customer::updateOrCreate(
                ['customer_code' => 'CUS-000001'],
                [
                    'customer_type_id' => $sadirType->id,
                    'default_sales_channel_id' => $sadirChannel->id,
                    'name' => 'متجر مفروشات سدير',
                    'commercial_name' => 'متجر سدير الإلكتروني',
                    'city' => 'الرياض',
                    'contact_person' => 'إدارة متجر سدير',
                    'email' => 'store@sadir.com',
                    'notes' => 'العميل المرجعي الأساسي لتوثيق وتصنيع طلبات متجر سدير الإلكتروني.',
                    'is_active' => true,
                    'is_credit_customer' => false,
                    'credit_limit' => null,
                    'opening_balance' => 0,
                ]
            );
        }
    }
}
