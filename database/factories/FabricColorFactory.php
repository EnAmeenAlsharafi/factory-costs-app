<?php

namespace Database\Factories;

use App\Models\FabricColor;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FabricColor>
 */
class FabricColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'color_code' => fake()->unique()->numberBetween(1, 9999),
            'color_name_ar' => 'لون '.fake()->colorName(),
            'color_name_en' => fake()->colorName(),
            'hex_code' => fake()->hexColor(),
            'pattern' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
