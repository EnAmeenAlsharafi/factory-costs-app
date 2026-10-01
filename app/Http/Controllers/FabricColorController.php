<?php

namespace App\Http\Controllers;

use App\Http\Requests\FabricColorRequest;
use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\ProductionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FabricColorController extends Controller
{
    public function store(FabricColorRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة لون أقمشة.');

        $colorCode = $request->validated('color_code');
        $colorNameAr = $request->validated('color_name_ar') ?: ('لون '.$colorCode);

        FabricColor::create([
            'material_id' => $request->validated('material_id'),
            'color_code' => $colorCode,
            'supplier_color_code' => $request->validated('supplier_color_code'),
            'color_name_ar' => $colorNameAr,
            'color_name_en' => $request->validated('color_name_en'),
            'hex_code' => $request->validated('hex_code'),
            'pattern' => $request->validated('pattern'),
            'is_available' => $request->boolean('is_available', true),
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->validated('notes'),
        ]);

        return back()->with('success', 'تم إضافة لون القماش بنجاح.');
    }

    public function update(FabricColorRequest $request, FabricColor $color): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتعديل لون أقمشة.');

        $colorCode = $request->validated('color_code');
        $colorNameAr = $request->validated('color_name_ar') ?: ('لون '.$colorCode);

        $color->update([
            'color_code' => $colorCode,
            'supplier_color_code' => $request->validated('supplier_color_code'),
            'color_name_ar' => $colorNameAr,
            'color_name_en' => $request->validated('color_name_en'),
            'hex_code' => $request->validated('hex_code'),
            'pattern' => $request->validated('pattern'),
            'is_available' => $request->boolean('is_available', true),
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->validated('notes'),
        ]);

        return back()->with('success', 'تم تحديث لون القماش بنجاح.');
    }

    public function destroy(Request $request, FabricColor $color): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بحذف لون أقمشة.');

        // Guard: Prevent deleting a color if used in orders, production, purchasing, receipts, or inventory
        $isUsedInOrders = CustomerOrderLine::where('fabric_color_id', $color->id)->exists();
        $isUsedInQuotes = DB::table('quotation_lines')->where('fabric_color_id', $color->id)->exists();
        $isUsedInProduction = ProductionOrder::where('fabric_color_id', $color->id)->exists();
        $isUsedInReceipts = DB::table('material_receipt_lines')->where('fabric_color_id', $color->id)->exists();
        $isUsedInLots = DB::table('inventory_lots')->where('fabric_color_id', $color->id)->exists();
        $isUsedInMovements = DB::table('inventory_movements')->where('fabric_color_id', $color->id)->exists();
        $isUsedInRequests = DB::table('production_material_request_lines')->where('fabric_color_id', $color->id)->exists();

        if ($isUsedInOrders || $isUsedInQuotes || $isUsedInProduction || $isUsedInReceipts || $isUsedInLots || $isUsedInMovements || $isUsedInRequests) {
            return back()->with('error', 'لا يمكن حذف هذا اللون لأنه مستخدم في سجلات تشغيلية (طلبات، إنتاج، مشتريات، أو مخزون). يمكنك تعطيله فقط.');
        }

        $color->delete();

        return back()->with('success', 'تم حذف لون القماش بنجاح.');
    }

    public function toggleStatus(Request $request, FabricColor $color): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتعديل حالة لون أقمشة.');

        if ($request->has('toggle_availability') || $request->input('field') === 'is_available') {
            $color->update(['is_available' => ! $color->is_available]);
            $msg = $color->is_available ? 'تم تحديد اللون كمتوفر.' : 'تم تحديد اللون كغير متوفر.';
        } else {
            $color->update(['is_active' => ! $color->is_active]);
            $msg = $color->is_active ? 'تم تفعيل اللون بنجاح.' : 'تم تعطيل اللون بنجاح.';
        }

        return back()->with('success', $msg);
    }
}
