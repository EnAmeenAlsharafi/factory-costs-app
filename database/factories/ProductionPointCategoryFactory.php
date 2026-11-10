<?php

namespace Database\Factories;

use App\Models\ProductionPointCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionPointCategory>
 */
class ProductionPointCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'تصنيف '.fake()->unique()->numberBetween(1, 9999),
            'points' => fake()->randomElement([0.5, 1, 1.2, 1.5]),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
