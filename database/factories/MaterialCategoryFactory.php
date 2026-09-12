<?php

namespace Database\Factories;

use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialCategoryFactory extends Factory
{
    protected $model = MaterialCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('CAT-???')),
            'name_ar' => 'تصنيف '.$this->faker->word(),
            'name_en' => $this->faker->word().' Category',
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
