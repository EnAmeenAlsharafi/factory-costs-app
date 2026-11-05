<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Services\SpecialFabricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOrderFabricController extends Controller
{
    public function update(Request $request, CustomerOrder $order, CustomerOrderLine $line, SpecialFabricService $fabrics): RedirectResponse
    {
        abort_unless($request->user()->can('orders.update') || $request->user()->can('orders.review_production'), 403);
        abort_unless($line->customer_order_id === $order->id, 404);
        $data = $request->validate(['fabric_color_id' => ['required', 'integer', 'exists:fabric_colors,id']]);
        $fabrics->link($line, (int) $data['fabric_color_id'], $request->user());

        return back()->with('success', 'تم ربط القماش الخاص بالمخزون مع حفظ وصف العميل والسعر المعتمد. يلزم استلامه وصرفه قبل التنجيد.');
    }
}
