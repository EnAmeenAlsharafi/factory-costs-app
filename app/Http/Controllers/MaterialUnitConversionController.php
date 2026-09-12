<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialUnitConversionRequest;
use App\Models\MaterialUnitConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaterialUnitConversionController extends Controller
{
    public function store(MaterialUnitConversionRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة تحويل وحدات للمادة.');

        MaterialUnitConversion::updateOrCreate(
            [
                'material_id' => $request->validated('material_id'),
                'from_unit_id' => $request->validated('from_unit_id'),
                'to_unit_id' => $request->validated('to_unit_id'),
            ],
            [
                'conversion_factor' => $request->validated('conversion_factor'),
                'notes' => $request->validated('notes'),
            ]
        );

        return back()->with('success', 'تم إضافة تحويل الوحدة الخاص بالمادة بنجاح.');
    }

    public function destroy(Request $request, MaterialUnitConversion $conversion): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بحذف تحويل الوحدة.');

        $conversion->delete();

        return back()->with('success', 'تم حذف تحويل الوحدة بنجاح.');
    }
}
