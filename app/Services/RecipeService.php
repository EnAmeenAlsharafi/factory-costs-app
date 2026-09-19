<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ManufacturingTemplate;
use App\Models\Material;
use App\Models\MaterialUnitConversion;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function __construct(protected DocumentNumberService $documentNumberService) {}

    /**
     * Create a new manufacturing recipe with Version 1 (DRAFT).
     */
    public function createRecipe(array $recipeData, array $itemsData): ManufacturingRecipe
    {
        return DB::transaction(function () use ($recipeData, $itemsData) {
            $targetType = $recipeData['target_type'] ?? 'PRODUCT_CONFIGURATION';

            if ($targetType === 'PRODUCT_CONFIGURATION' && ! empty($recipeData['product_configuration_id'])) {
                $existing = ManufacturingRecipe::where('target_type', 'PRODUCT_CONFIGURATION')
                    ->where('product_configuration_id', $recipeData['product_configuration_id'])
                    ->first();
                if ($existing) {
                    throw new Exception('توجد بالفعل وصفة تصنيع معرفة لهذا التكوين المصنعي ['.$existing->recipe_code.']. يرجى الانتقال للوصفة الحالية وتحديث إصداراتها.');
                }
            } elseif ($targetType === 'SEMI_FINISHED_COMPONENT' && ! empty($recipeData['semi_finished_component_id'])) {
                $existing = ManufacturingRecipe::where('target_type', 'SEMI_FINISHED_COMPONENT')
                    ->where('semi_finished_component_id', $recipeData['semi_finished_component_id'])
                    ->first();
                if ($existing) {
                    throw new Exception('توجد بالفعل وصفة تصنيع معرفة لهذا المكون نصف المصنع ['.$existing->recipe_code.']. يرجى الانتقال للوصفة الحالية وتحديث إصداراتها.');
                }
            }

            $recipeCode = $this->documentNumberService->generateRecipeCode();

            $recipe = ManufacturingRecipe::create([
                'recipe_code' => $recipeCode,
                'target_type' => $targetType,
                'product_configuration_id' => $recipeData['product_configuration_id'] ?? null,
                'semi_finished_component_id' => $recipeData['semi_finished_component_id'] ?? null,
                'name' => $recipeData['name'],
                'description' => $recipeData['description'] ?? null,
                'is_active' => true,
            ]);

            $version = ManufacturingRecipeVersion::create([
                'manufacturing_recipe_id' => $recipe->id,
                'version_number' => 1,
                'status' => 'DRAFT',
                'notes' => $recipeData['version_notes'] ?? 'إصدار مسودة أولي',
            ]);

            $this->syncItems($version, $itemsData);

            return $recipe;
        });
    }

    /**
     * Update a DRAFT recipe version items & metadata.
     */
    public function updateDraftVersion(ManufacturingRecipeVersion $version, array $versionData, array $itemsData): ManufacturingRecipeVersion
    {
        if ($version->status !== 'DRAFT') {
            throw new Exception('لا يمكن تعديل المكونات مباشرة لإصدار معتمد أو ملغى. يرجى إنشاء إصدار جديد.');
        }

        return DB::transaction(function () use ($version, $versionData, $itemsData) {
            $version->update([
                'notes' => $versionData['notes'] ?? $version->notes,
            ]);

            $version->items()->delete();
            $this->syncItems($version, $itemsData);

            return $version;
        });
    }

    /**
     * Approve a recipe version, superseding previous approved version.
     */
    public function approveVersion(ManufacturingRecipeVersion $version, User $approver): ManufacturingRecipeVersion
    {
        if (! in_array($version->status, ['DRAFT', 'INACTIVE'])) {
            throw new Exception('يمكن فقط اعتماد الإصدارات الموجودة في حالة مسودة.');
        }

        return DB::transaction(function () use ($version, $approver) {
            $recipe = $version->recipe;

            // Supersede any existing APPROVED version for this recipe
            ManufacturingRecipeVersion::where('manufacturing_recipe_id', $recipe->id)
                ->where('status', 'APPROVED')
                ->where('id', '!=', $version->id)
                ->update([
                    'status' => 'SUPERSEDED',
                    'effective_to' => now(),
                ]);

            $version->update([
                'status' => 'APPROVED',
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'effective_from' => now(),
                'effective_to' => null,
            ]);

            return $version;
        });
    }

    /**
     * Copy an existing version into a new DRAFT version.
     */
    public function copyToNewVersion(ManufacturingRecipeVersion $sourceVersion, ?string $notes = null): ManufacturingRecipeVersion
    {
        return DB::transaction(function () use ($sourceVersion, $notes) {
            $recipe = $sourceVersion->recipe;

            $maxVersionNumber = (int) $recipe->versions()->max('version_number');
            $newVersionNumber = $maxVersionNumber + 1;

            $newVersion = ManufacturingRecipeVersion::create([
                'manufacturing_recipe_id' => $recipe->id,
                'version_number' => $newVersionNumber,
                'status' => 'DRAFT',
                'notes' => $notes ?? 'نسخة مشتقة من الإصدار رقم V'.$sourceVersion->version_number,
            ]);

            foreach ($sourceVersion->items as $item) {
                ManufacturingRecipeItem::create([
                    'recipe_version_id' => $newVersion->id,
                    'item_type' => $item->item_type,
                    'material_id' => $item->material_id,
                    'semi_finished_component_id' => $item->semi_finished_component_id,
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'waste_percentage' => $item->waste_percentage,
                    'notes' => $item->notes,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $newVersion;
        });
    }

    /**
     * Create a new draft recipe using a Manufacturing Template.
     */
    public function createRecipeFromTemplate(ManufacturingTemplate $template, array $targetData): ManufacturingRecipe
    {
        $itemsData = [];
        foreach ($template->items as $index => $templateItem) {
            $itemsData[] = [
                'item_type' => $templateItem->item_type,
                'material_id' => $templateItem->material_id,
                'semi_finished_component_id' => $templateItem->semi_finished_component_id,
                'quantity' => $templateItem->quantity,
                'unit_id' => $templateItem->unit_id,
                'waste_percentage' => $templateItem->waste_percentage,
                'notes' => $templateItem->notes,
                'sort_order' => $index + 1,
            ];
        }

        $targetData['version_notes'] = 'تم إنشاء المسودة بناءً على القالب: '.$template->name_ar;

        return $this->createRecipe($targetData, $itemsData);
    }

    /**
     * Non-authoritative Standard Cost Preview (Estimated).
     */
    public function calculateStandardCostPreview(ManufacturingRecipeVersion $version): array
    {
        $totalCost = 0.0;
        $hasMissingPrice = false;
        $itemsPreview = [];

        foreach ($version->items as $item) {
            $plannedQty = $item->planned_quantity;
            $unitCost = 0.0;
            $hasPrice = false;

            if ($item->item_type === 'MATERIAL' && $item->material_id) {
                // Check latest lot unit cost in base unit
                $latestLotCost = InventoryLot::where('material_id', $item->material_id)
                    ->whereNotNull('unit_cost')
                    ->latest('id')
                    ->value('unit_cost');

                if ($latestLotCost !== null && (float) $latestLotCost > 0) {
                    $unitCost = (float) $latestLotCost;
                    $hasPrice = true;
                }
            } elseif ($item->item_type === 'SEMI_FINISHED_COMPONENT' && $item->semi_finished_component_id) {
                // If semi-finished component has an approved recipe, calculate its cost preview
                $componentRecipe = ManufacturingRecipe::where('semi_finished_component_id', $item->semi_finished_component_id)
                    ->where('is_active', true)
                    ->first();

                if ($componentRecipe && $componentRecipe->currentApprovedVersion) {
                    $compPreview = $this->calculateStandardCostPreview($componentRecipe->currentApprovedVersion);
                    if (! $compPreview['has_unavailable_prices'] && $compPreview['total_cost'] > 0) {
                        $unitCost = $compPreview['total_cost'];
                        $hasPrice = true;
                    }
                }
            }

            if (! $hasPrice) {
                $hasMissingPrice = true;
            }

            $lineEstimatedTotal = $plannedQty * $unitCost;
            $totalCost += $lineEstimatedTotal;

            $itemsPreview[] = [
                'item_type' => $item->item_type,
                'name' => $item->item_type === 'MATERIAL' ? $item->material?->name_ar : $item->semiFinishedComponent?->name_ar,
                'quantity' => (float) $item->quantity,
                'waste_percentage' => (float) $item->waste_percentage,
                'planned_quantity' => $plannedQty,
                'unit_symbol' => $item->unit?->code,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $lineEstimatedTotal,
                'has_price' => $hasPrice,
            ];
        }

        return [
            'total_cost' => $totalCost,
            'has_unavailable_prices' => $hasMissingPrice,
            'items_preview' => $itemsPreview,
        ];
    }

    /**
     * Helper to sync items for a version.
     */
    protected function syncItems(ManufacturingRecipeVersion $version, array $itemsData): void
    {
        $seenMaterials = [];

        foreach ($itemsData as $index => $item) {
            $itemType = $item['item_type'] ?? 'MATERIAL';
            $materialId = $item['material_id'] ?? null;
            $componentId = $item['semi_finished_component_id'] ?? null;

            if ($itemType === 'MATERIAL') {
                if (! $materialId) {
                    continue;
                }

                // Check duplicate material in same recipe version
                if (in_array($materialId, $seenMaterials)) {
                    throw new Exception('لا يمكن تكرار نفس المادة الخام في أكثر من بنود الوصفة.');
                }
                $seenMaterials[] = $materialId;

                // Check material status
                $mat = Material::find($materialId);
                if ($mat && ! $mat->is_active) {
                    throw new Exception('المادة ['.$mat->name_ar.'] معطلة ولا يمكن إضافتها لوصفة جديدة.');
                }

                if (! $this->validateUnitCompatibility($materialId, (int) $item['unit_id'])) {
                    throw new Exception('وحدة القياس المحددة للمادة غير متوافقة مع المادة الخام.');
                }
            } else {
                if (! $componentId) {
                    continue;
                }

                // Cycle check for semi-finished components: prevent component from consuming itself
                if ($version->recipe->target_type === 'SEMI_FINISHED_COMPONENT' && $version->recipe->semi_finished_component_id == $componentId) {
                    throw new Exception('لا يمكن للمكون نصف المصنع أن يحتوي على نفسه في قائمة المواد (الدائرية غير مسموحة).');
                }
            }

            ManufacturingRecipeItem::create([
                'recipe_version_id' => $version->id,
                'item_type' => $itemType,
                'material_id' => $itemType === 'MATERIAL' ? $materialId : null,
                'semi_finished_component_id' => $itemType === 'SEMI_FINISHED_COMPONENT' ? $componentId : null,
                'quantity' => $item['quantity'],
                'unit_id' => $item['unit_id'],
                'waste_percentage' => $item['waste_percentage'] ?? 0.00,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Check if a unit of measure is compatible with a material's base unit or conversion.
     */
    public function validateUnitCompatibility(int $materialId, int $unitId): bool
    {
        $material = Material::find($materialId);
        if (! $material) {
            return false;
        }

        if (! $unitId) {
            return false;
        }

        if ($material->base_unit_id == $unitId || $material->purchase_unit_id == $unitId) {
            return true;
        }

        // Check material-specific unit conversions
        $materialConversionExists = MaterialUnitConversion::where('material_id', $materialId)
            ->where(function ($q) use ($material, $unitId) {
                $q->where('from_unit_id', $material->base_unit_id)->where('to_unit_id', $unitId);
            })->orWhere(function ($q) use ($material, $unitId) {
                $q->where('from_unit_id', $unitId)->where('to_unit_id', $material->base_unit_id);
            })->exists();

        if ($materialConversionExists) {
            return true;
        }

        // Check global unit conversions
        $globalConversionExists = UnitConversion::where(function ($q) use ($material, $unitId) {
            $q->where('from_unit_id', $material->base_unit_id)->where('to_unit_id', $unitId);
        })->orWhere(function ($q) use ($material, $unitId) {
            $q->where('from_unit_id', $unitId)->where('to_unit_id', $material->base_unit_id);
        })->exists();

        if ($globalConversionExists) {
            return true;
        }

        $baseUnit = UnitOfMeasure::find($material->base_unit_id);
        $targetUnit = UnitOfMeasure::find($unitId);

        if (! $baseUnit || ! $targetUnit || ! $targetUnit->is_active) {
            return false;
        }

        // Reject incompatible physical dimension types (e.g. length vs weight, volume vs weight) without conversion
        $incompatibleTypes = ['length', 'weight', 'volume'];
        if (
            in_array($baseUnit->unit_type, $incompatibleTypes) &&
            in_array($targetUnit->unit_type, $incompatibleTypes) &&
            $baseUnit->unit_type !== $targetUnit->unit_type
        ) {
            return false;
        }

        return true;
    }
}
