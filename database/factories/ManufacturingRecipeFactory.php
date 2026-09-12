<?php

namespace Database\Factories;

use App\Models\ManufacturingRecipe;
use App\Models\ProductConfiguration;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturingRecipe>
 */
class ManufacturingRecipeFactory extends Factory
{
    protected $model = ManufacturingRecipe::class;

    public function definition(): array
    {
        return [
            'recipe_code' => app(DocumentNumberService::class)->generateRecipeCode(),
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => ProductConfiguration::factory(),
            'name' => 'وصفة تصنيع تجريبية',
            'description' => 'وصفة تصنيع لاختبار النظام',
            'is_active' => true,
        ];
    }
}
