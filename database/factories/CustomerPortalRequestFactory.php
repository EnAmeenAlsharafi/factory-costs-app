<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPortalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPortalRequest>
 */
class CustomerPortalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory(),
            'status' => 'DRAFT',
            'revision' => 1,
            'lines' => [],
        ];
    }
}
