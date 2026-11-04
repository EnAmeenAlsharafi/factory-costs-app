<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderLine>
 */
class CustomerOrderLineFactory extends Factory
{
    protected $model = CustomerOrderLine::class;

    public function definition(): array
    {
        $customerOrder = CustomerOrder::first() ?? CustomerOrder::factory()->create();

        return [
            'customer_order_id' => $customerOrder->id,
            'line_type' => 'MANUFACTURED',
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'discount_amount' => 0,
            'line_total' => 1000,
        ];
    }

    public function readyStock(): self
    {
        return $this->state(fn () => [
            'line_type' => 'READY_STOCK',
            'requested_width_cm' => null,
            'requested_length_cm' => null,
        ]);
    }

    public function bundle(): self
    {
        return $this->state(fn () => [
            'line_type' => 'BUNDLE',
            'requested_width_cm' => null,
            'requested_length_cm' => null,
        ]);
    }
}
