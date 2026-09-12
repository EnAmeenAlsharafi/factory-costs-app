<?php

namespace Database\Factories;

use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturingRecipeItem>
 */
class ManufacturingRecipeItemFactory extends Factory
{
    protected $model = ManufacturingRecipeItem::class;

    public function definition(): array
    {
        $unit = UnitOfMeasure::first() ?? UnitOfMeasure::factory()->create();

        return [
            'recipe_version_id' => ManufacturingRecipeVersion::factory(),
            'item_type' => 'MATERIAL',
            'material_id' => Material::factory(),
            'quantity' => 10.0000,
            'unit_id' => $unit->id,
            'waste_percentage' => 5.00,
            'sort_order' => 1,
        ];
    }
}
