<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $customer = Customer::first() ?? Customer::factory()->create();
        $salesChannel = SalesChannel::first() ?? SalesChannel::factory()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'quotation_number' => 'QUO-2026-'.fake()->unique()->numberBetween(100000, 999999),
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_code' => 'SAR',
            'status' => 'DRAFT',
            'subtotal' => 3000.00,
            'discount_total' => 0.00,
            'total_amount' => 3000.00,
            'notes' => 'عرض سعر تجريبي',
            'created_by_user_id' => $user->id,
        ];
    }
}
