<?php

namespace Database\Factories;

use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_code' => 'PM-'.fake()->unique()->numerify('######'),
            'name_ar' => 'موديل '.fake()->word(),
            'is_active' => true,
            'requires_fabric_selection' => false,
        ];
    }
}
