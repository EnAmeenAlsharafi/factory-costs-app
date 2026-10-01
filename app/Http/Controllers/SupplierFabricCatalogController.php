<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierFabricCatalogRequest;
use App\Models\Material;
use App\Models\SupplierFabricCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SupplierFabricCatalogController extends Controller
{
    public function store(StoreSupplierFabricCatalogRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة كتالوج أقمشة.');

        $validated = $request->validated();
        $imagePath = $request->file('catalog_image')?->store('fabric-catalogs', 'public');

        try {
            DB::transaction(function () use ($request, $validated, $imagePath): void {
                $material = Material::findOrFail($validated['material_id']);
                $material->suppliers()->syncWithoutDetaching([$validated['supplier_id']]);

                SupplierFabricCatalog::create([
                    'material_id' => $material->id,
                    'supplier_id' => $validated['supplier_id'],
                    'catalog_number' => $validated['catalog_number'],
                    'catalog_name' => $validated['catalog_name'] ?? null,
                    'supplier_material_code' => $validated['supplier_material_code'] ?? null,
                    'image_path' => $imagePath,
                    'is_active' => $request->boolean('is_active', true),
                    'notes' => $validated['notes'] ?? null,
                ]);
            });
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return back()->with('success', 'تمت إضافة كتالوج المورد. اربط الآن ألوانه بالأرقام الداخلية.');
    }

    public function destroy(Request $request, SupplierFabricCatalog $catalog): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بحذف كتالوج أقمشة.');

        $imagePath = $catalog->image_path;
        $catalog->delete();

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'تم حذف كتالوج المورد وربط ألوانه.');
    }
}
