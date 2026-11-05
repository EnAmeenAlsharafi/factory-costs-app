<?php

namespace Database\Factories;

use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductConfiguration>
 */
class ProductConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'configuration_code' => 'CFG-'.fake()->unique()->numerify('######'),
            'product_model_id' => ProductModel::factory(),
            'width_cm' => 100,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ];
    }
}
