<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierFabricCatalogColorRequest;
use App\Models\SupplierFabricCatalogColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierFabricCatalogColorController extends Controller
{
    public function store(StoreSupplierFabricCatalogColorRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بربط ألوان الكتالوج.');

        $validated = $request->validated();

        SupplierFabricCatalogColor::create([
            'supplier_fabric_catalog_id' => $validated['supplier_fabric_catalog_id'],
            'fabric_color_id' => $validated['fabric_color_id'],
            'supplier_color_code' => $validated['supplier_color_code'],
            'supplier_color_name' => $validated['supplier_color_name'] ?? null,
            'is_available' => $request->boolean('is_available', true),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'تم ربط رقم اللون الداخلي بكود المورد.');
    }

    public function destroy(Request $request, SupplierFabricCatalogColor $catalogColor): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بحذف ربط لون الكتالوج.');

        $catalogColor->delete();

        return back()->with('success', 'تم حذف ربط اللون من الكتالوج.');
    }
}
