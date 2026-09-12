<?php

namespace App\Http\Controllers;

use App\Http\Requests\FabricColorRequest;
use App\Models\FabricColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FabricColorController extends Controller
{
    public function store(FabricColorRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة لون أقمشة.');

        FabricColor::create([
            'material_id' => $request->validated('material_id'),
            'color_code' => $request->validated('color_code'),
            'color_name_ar' => $request->validated('color_name_ar'),
            'color_name_en' => $request->validated('color_name_en'),
            'hex_code' => $request->validated('hex_code'),
            'pattern' => $request->validated('pattern'),
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->validated('notes'),
        ]);

        return back()->with('success', 'تم إضافة لون القماش بنجاح.');
    }

    public function update(FabricColorRequest $request, FabricColor $color): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتعديل لون أقمشة.');

        $color->update([
            'color_code' => $request->validated('color_code'),
            'color_name_ar' => $request->validated('color_name_ar'),
            'color_name_en' => $request->validated('color_name_en'),
            'hex_code' => $request->validated('hex_code'),
            'pattern' => $request->validated('pattern'),
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->validated('notes'),
        ]);

        return back()->with('success', 'تم تحديث لون القماش بنجاح.');
    }

    public function destroy(Request $request, FabricColor $color): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بحذف لون أقمشة.');

        $color->delete();

        return back()->with('success', 'تم حذف لون القماش بنجاح.');
    }
}
