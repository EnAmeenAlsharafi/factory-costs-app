<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialSupplierRequest;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialSupplierController extends Controller
{
    public function store(MaterialSupplierRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بربط مورد بالمادة.');

        $materialId = $request->validated('material_id');
        $supplierId = $request->validated('supplier_id');
        $isPreferred = $request->boolean('is_preferred');

        DB::transaction(function () use ($request, $materialId, $supplierId, $isPreferred) {
            if ($isPreferred) {
                DB::table('material_supplier')
                    ->where('material_id', $materialId)
                    ->update(['is_preferred' => false]);
            }

            $material = Material::findOrFail($materialId);
            $material->suppliers()->syncWithoutDetaching([
                $supplierId => [
                    'supplier_item_code' => $request->validated('supplier_item_code'),
                    'lead_time_days' => $request->validated('lead_time_days'),
                    'minimum_order_qty' => $request->validated('minimum_order_qty'),
                    'is_preferred' => $isPreferred,
                    'notes' => $request->validated('notes'),
                ],
            ]);
        });

        return back()->with('success', 'تم ربط المورد بالمادة بنجاح.');
    }

    public function destroy(Request $request, Material $material, int $supplierId): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإلغاء ربط المورد بالمادة.');

        $material->suppliers()->detach($supplierId);

        return back()->with('success', 'تم إلغاء ربط المورد بالمادة بنجاح.');
    }
}
