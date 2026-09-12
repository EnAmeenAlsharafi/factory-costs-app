<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialRequest;
use App\Models\FabricMaterialSpec;
use App\Models\FoamMaterialSpec;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\WoodMaterialSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('materials.view'), 403, 'غير مصرح لك بعرض كتالوج المواد.');

        $query = Material::with(['category', 'baseUnit', 'purchaseUnit']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('material_category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $materials = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $categories = MaterialCategory::where('is_active', true)->orderBy('name_ar')->get();

        return view('materials.index', compact('materials', 'categories'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة مادة جديدة.');

        $categories = MaterialCategory::where('is_active', true)->orderBy('name_ar')->get();
        $units = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();
        $generatedCode = Material::generateNextCode();

        return view('materials.create', compact('categories', 'units', 'generatedCode'));
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated) {
            $material = Material::create([
                'code' => $validated['code'],
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'] ?? null,
                'material_category_id' => $validated['material_category_id'],
                'base_unit_id' => $validated['base_unit_id'],
                'purchase_unit_id' => $validated['purchase_unit_id'] ?? null,
                'min_stock_level' => $validated['min_stock_level'] ?? 0,
                'reorder_point' => $validated['reorder_point'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'notes' => $validated['notes'] ?? null,
            ]);

            $category = MaterialCategory::find($validated['material_category_id']);
            if ($category) {
                switch ($category->code) {
                    case 'WOOD':
                        WoodMaterialSpec::create([
                            'material_id' => $material->id,
                            'wood_type' => $validated['wood_type'],
                            'thickness_mm' => $validated['thickness_mm'],
                            'width_cm' => $validated['width_cm'],
                            'length_cm' => $validated['length_cm'],
                            'grade' => $validated['grade'] ?? null,
                        ]);
                        break;
                    case 'FOAM':
                        FoamMaterialSpec::create([
                            'material_id' => $material->id,
                            'foam_type' => $validated['foam_type'],
                            'density_kg_m3' => $validated['density_kg_m3'] ?? null,
                            'hardness_rating' => $validated['hardness_rating'] ?? null,
                            'thickness_mm' => $validated['thickness_mm'],
                            'width_cm' => $validated['width_cm'] ?? null,
                            'length_cm' => $validated['length_cm'] ?? null,
                            'block_dimensions' => $validated['block_dimensions'] ?? null,
                        ]);
                        break;
                    case 'FABRIC':
                        FabricMaterialSpec::create([
                            'material_id' => $material->id,
                            'fabric_type' => $validated['fabric_type'],
                            'pattern_type' => $validated['pattern_type'] ?? null,
                            'width_cm' => $validated['width_cm'],
                            'weight_gsm' => $validated['weight_gsm'] ?? null,
                            'composition' => $validated['composition'] ?? null,
                            'martindale_rub_count' => $validated['martindale_rub_count'] ?? null,
                        ]);
                        break;
                }
            }
        });

        return redirect()->route('materials.index')
            ->with('success', 'تم إنشاء المادة الخام بنجاح.');
    }

    public function show(Request $request, Material $material): View
    {
        abort_if(! $request->user()->can('materials.view'), 403, 'غير مصرح لك بعرض تفاصيل المادة.');

        $material->load([
            'category',
            'baseUnit',
            'purchaseUnit',
            'woodSpec',
            'foamSpec',
            'fabricSpec',
            'fabricColors',
            'suppliers',
            'unitConversions.fromUnit',
            'unitConversions.toUnit',
        ]);

        $allSuppliers = Supplier::where('is_active', true)->orderBy('name_ar')->get();
        $allUnits = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();

        return view('materials.show', compact('material', 'allSuppliers', 'allUnits'));
    }

    public function edit(Request $request, Material $material): View
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتعديل بيانات المادة.');

        $material->load(['category', 'baseUnit', 'purchaseUnit', 'woodSpec', 'foamSpec', 'fabricSpec']);
        $categories = MaterialCategory::where('is_active', true)->orderBy('name_ar')->get();
        $units = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();

        return view('materials.edit', compact('material', 'categories', 'units'));
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $material, $validated) {
            $material->update([
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'] ?? null,
                'material_category_id' => $validated['material_category_id'],
                'base_unit_id' => $validated['base_unit_id'],
                'purchase_unit_id' => $validated['purchase_unit_id'] ?? null,
                'min_stock_level' => $validated['min_stock_level'] ?? 0,
                'reorder_point' => $validated['reorder_point'] ?? 0,
                'is_active' => $request->boolean('is_active'),
                'notes' => $validated['notes'] ?? null,
            ]);

            $category = MaterialCategory::find($validated['material_category_id']);
            if ($category) {
                switch ($category->code) {
                    case 'WOOD':
                        WoodMaterialSpec::updateOrCreate(
                            ['material_id' => $material->id],
                            [
                                'wood_type' => $validated['wood_type'],
                                'thickness_mm' => $validated['thickness_mm'],
                                'width_cm' => $validated['width_cm'],
                                'length_cm' => $validated['length_cm'],
                                'grade' => $validated['grade'] ?? null,
                            ]
                        );
                        break;
                    case 'FOAM':
                        FoamMaterialSpec::updateOrCreate(
                            ['material_id' => $material->id],
                            [
                                'foam_type' => $validated['foam_type'],
                                'density_kg_m3' => $validated['density_kg_m3'] ?? null,
                                'hardness_rating' => $validated['hardness_rating'] ?? null,
                                'thickness_mm' => $validated['thickness_mm'],
                                'width_cm' => $validated['width_cm'] ?? null,
                                'length_cm' => $validated['length_cm'] ?? null,
                                'block_dimensions' => $validated['block_dimensions'] ?? null,
                            ]
                        );
                        break;
                    case 'FABRIC':
                        FabricMaterialSpec::updateOrCreate(
                            ['material_id' => $material->id],
                            [
                                'fabric_type' => $validated['fabric_type'],
                                'pattern_type' => $validated['pattern_type'] ?? null,
                                'width_cm' => $validated['width_cm'],
                                'weight_gsm' => $validated['weight_gsm'] ?? null,
                                'composition' => $validated['composition'] ?? null,
                                'martindale_rub_count' => $validated['martindale_rub_count'] ?? null,
                            ]
                        );
                        break;
                }
            }
        });

        return redirect()->route('materials.show', $material)
            ->with('success', 'تم تحديث بيانات المادة الخام بنجاح.');
    }

    public function toggleStatus(Request $request, Material $material): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتغيير حالة المادة.');

        $material->update(['is_active' => ! $material->is_active]);

        $msg = $material->is_active ? 'تم تفعيل المادة الخام بنجاح.' : 'تم تعطيل المادة الخام بنجاح.';

        return back()->with('success', $msg);
    }
}
