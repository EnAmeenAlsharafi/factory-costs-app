<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'code' => Material::generateNextCode(),
            'name_ar' => 'مادة '.$this->faker->word(),
            'name_en' => 'Material '.$this->faker->word(),
            'material_category_id' => MaterialCategory::factory(),
            'base_unit_id' => UnitOfMeasure::factory(),
            'purchase_unit_id' => null,
            'min_stock_level' => 10,
            'reorder_point' => 20,
            'is_active' => true,
            'notes' => $this->faker->sentence(),
        ];
    }
}
