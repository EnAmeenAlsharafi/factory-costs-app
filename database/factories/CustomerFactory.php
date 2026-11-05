<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_code' => Customer::generateNextCode(),
            'name' => fake()->company(),
            'customer_type_id' => CustomerType::where('code', 'WHOLESALE_CUSTOMER')->value('id'),
            'default_sales_channel_id' => SalesChannel::where('is_active', true)->value('id'),
            'is_active' => true,
            'opening_balance' => 0,
        ];
    }
}
