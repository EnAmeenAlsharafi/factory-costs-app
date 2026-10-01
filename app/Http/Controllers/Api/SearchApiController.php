<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchApiController extends Controller
{
    /**
     * Search active Product Models by name_ar, name_en, model_code.
     */
    public function productModels(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $perPage = min(30, max(1, (int) $request->input('per_page', 20)));

        $models = ProductModel::query()
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%")
                        ->orWhere('model_code', 'like', "%{$query}%");
                });
            })
            ->when($query !== '', function ($q) use ($query) {
                $q->orderByRaw('CASE 
                    WHEN model_code LIKE ? THEN 1 
                    WHEN name_ar LIKE ? THEN 2 
                    WHEN name_en LIKE ? THEN 3 
                    ELSE 4 END', ["{$query}%", "{$query}%", "{$query}%"]);
            })
            ->orderBy('name_ar')
            ->limit($perPage)
            ->get();

        $results = $models->map(function ($model) {
            return [
                'id' => $model->id,
                'code' => $model->model_code,
                'name_ar' => $model->name_ar,
                'label' => $model->name_ar,
                'requires_fabric_selection' => (bool) $model->requires_fabric_selection,
            ];
        });

        return response()->json($results);
    }

    /**
     * Search Product Configurations for a model.
     */
    public function productConfigurations(Request $request): JsonResponse
    {
        $modelId = $request->input('model_id') ?? $request->input('product_model_id');
        $query = trim($request->input('q', ''));
        $perPage = min(30, max(1, (int) $request->input('per_page', 20)));

        if (! $modelId) {
            return response()->json([]);
        }

        $configs = ProductConfiguration::query()
            ->where('product_model_id', $modelId)
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('configuration_code', 'like', "%{$query}%")
                        ->orWhere('width_cm', 'like', "%{$query}%")
                        ->orWhere('length_cm', 'like', "%{$query}%");
                });
            })
            ->orderBy('width_cm')
            ->orderBy('length_cm')
            ->limit($perPage)
            ->get();

        $results = $configs->map(function ($cfg) {
            $storageLabel = $cfg->has_storage ? 'بتخزين' : 'بدون تخزين';
            $label = "{$cfg->width_cm}×{$cfg->length_cm} — {$storageLabel}";

            return [
                'id' => $cfg->id,
                'code' => $cfg->configuration_code,
                'width_cm' => (float) $cfg->width_cm,
                'length_cm' => (float) $cfg->length_cm,
                'has_storage' => (bool) $cfg->has_storage,
                'label' => $label,
            ];
        });

        return response()->json($results);
    }

    /**
     * Search active Suppliers by name, supplier_code.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $perPage = min(30, max(1, (int) $request->input('per_page', 20)));

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('commercial_name', 'like', "%{$query}%")
                        ->orWhere('supplier_code', 'like', "%{$query}%");
                });
            })
            ->when($query !== '', function ($q) use ($query) {
                $q->orderByRaw('CASE 
                    WHEN supplier_code LIKE ? THEN 1 
                    WHEN name LIKE ? THEN 2 
                    ELSE 3 END', ["{$query}%", "{$query}%"]);
            })
            ->orderBy('name')
            ->limit($perPage)
            ->get();

        $results = $suppliers->map(function ($s) {
            return [
                'id' => $s->id,
                'code' => $s->supplier_code,
                'name' => $s->name,
                'label' => $s->name,
            ];
        });

        return response()->json($results);
    }

    /**
     * Search Fabric Materials, optionally filtered by supplier_id.
     */
    public function fabricMaterials(Request $request): JsonResponse
    {
        $supplierId = $request->input('supplier_id');
        $query = trim($request->input('q', ''));
        $perPage = min(30, max(1, (int) $request->input('per_page', 20)));

        $hasSupplierFabrics = true;

        $materialsQuery = Material::query()
            ->with(['fabricSpec.supplier', 'suppliers'])
            ->where('is_active', true)
            ->whereHas('category', fn ($c) => $c->where('code', 'FABRIC'));

        if ($supplierId) {
            $hasSupplierFabrics = DB::table('material_supplier')
                ->join('materials', 'materials.id', '=', 'material_supplier.material_id')
                ->join('material_categories', 'material_categories.id', '=', 'materials.material_category_id')
                ->where('material_supplier.supplier_id', $supplierId)
                ->where('material_categories.code', 'FABRIC')
                ->exists();

            $materialsQuery->where(function ($q) use ($supplierId) {
                $q->whereHas('fabricSpec', fn ($specQ) => $specQ->where('supplier_id', $supplierId))
                    ->orWhereHas('suppliers', fn ($supQ) => $supQ->where('suppliers.id', $supplierId));
            });
        }

        if ($query !== '') {
            $materialsQuery->where(function ($sub) use ($query) {
                $sub->where('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhereHas('fabricSpec', function ($specQ) use ($query) {
                        $specQ->where('catalog_number', 'like', "%{$query}%")
                            ->orWhere('catalog_name', 'like', "%{$query}%")
                            ->orWhere('fabric_type', 'like', "%{$query}%")
                            ->orWhereHas('supplier', function ($supQ) use ($query) {
                                $supQ->where('name', 'like', "%{$query}%")
                                    ->orWhere('supplier_code', 'like', "%{$query}%");
                            });
                    });
            });

            $materialsQuery->orderByRaw('CASE 
                WHEN code LIKE ? THEN 1 
                WHEN name_ar LIKE ? THEN 2 
                ELSE 3 END', ["{$query}%", "{$query}%"]);
        }

        $materials = $materialsQuery->orderBy('name_ar')->limit($perPage)->get();

        $results = $materials->map(function ($m) {
            $spec = $m->fabricSpec;
            $supplier = $spec?->supplier ?? $m->suppliers->first();

            return [
                'id' => $m->id,
                'code' => $m->code,
                'name_ar' => $m->name_ar,
                'label' => $m->name_ar,
                'fabric_type' => $spec?->fabric_type,
                'catalog_number' => $spec?->catalog_number,
                'catalog_name' => $spec?->catalog_name,
                'catalog_image_url' => $spec?->catalog_image_path ? asset('storage/'.$spec->catalog_image_path) : null,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name,
                'supplier_code' => $supplier?->supplier_code,
            ];
        });

        return response()->json($results)->header('X-Supplier-Has-Fabrics', $hasSupplierFabrics ? '1' : '0');
    }

    /**
     * Get active and available colors for a specific fabric material.
     */
    public function fabricColors(Material $material): JsonResponse
    {
        $colors = $material->fabricColors()
            ->where('is_active', true)
            ->where('is_available', true)
            ->orderByRaw('CAST(color_code AS UNSIGNED), color_code ASC')
            ->get(['id', 'material_id', 'color_code', 'supplier_color_code', 'color_name_ar', 'color_name_en', 'hex_code', 'pattern']);

        $results = $colors->map(function ($c) {
            $supplierSuffix = $c->supplier_color_code ? " ({$c->supplier_color_code})" : '';

            return [
                'id' => $c->id,
                'color_code' => $c->color_code,
                'supplier_color_code' => $c->supplier_color_code,
                'color_name_ar' => $c->color_name_ar,
                'hex_code' => $c->hex_code,
                'pattern' => $c->pattern,
                'label' => "{$c->color_code} - {$c->color_name_ar}{$supplierSuffix}",
            ];
        });

        return response()->json($results);
    }
}
