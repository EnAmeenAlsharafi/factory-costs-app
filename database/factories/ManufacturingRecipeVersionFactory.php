<?php

namespace Database\Factories;

use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturingRecipeVersion>
 */
class ManufacturingRecipeVersionFactory extends Factory
{
    protected $model = ManufacturingRecipeVersion::class;

    public function definition(): array
    {
        return [
            'manufacturing_recipe_id' => ManufacturingRecipe::factory(),
            'version_number' => 1,
            'status' => 'DRAFT',
            'notes' => 'إصدار مسودة تجريبي',
        ];
    }
}
