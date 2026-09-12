<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrder>
 */
class CustomerOrderFactory extends Factory
{
    protected $model = CustomerOrder::class;

    public function definition(): array
    {
        $customer = Customer::first() ?? Customer::factory()->create();
        $salesChannel = SalesChannel::first() ?? SalesChannel::factory()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'order_number' => 'ORD-2026-'.fake()->unique()->numberBetween(100000, 999999),
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'customer_reference' => 'REF-'.$this->faker->numberBetween(100, 999),
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => now()->addDays(14)->toDateString(),
            'priority' => 'NORMAL',
            'status' => 'DRAFT',
            'subtotal' => 3500.00,
            'discount_total' => 0.00,
            'total_amount' => 3500.00,
            'commercial_notes' => 'طلب عميل تجريبي',
            'created_by_user_id' => $user->id,
        ];
    }
}
