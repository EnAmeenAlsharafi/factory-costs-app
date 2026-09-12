<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLot;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.adjust'), 403, 'غير مصرح لك بإدارة تسويات المخزون.');
        $query = InventoryAdjustment::with(['warehouse', 'reason', 'user'])
            ->latest('adjustment_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('adjustment_number', 'like', "%{$search}%");
        }

        $adjustments = $query->paginate(15)->withQueryString();
        $warehouses = Warehouse::orderBy('name_ar')->get();

        return view('inventory.adjustments.index', compact('adjustments', 'warehouses'));
    }

    public function create(): View
    {
        abort_if(! request()->user()->can('inventory.adjust'), 403, 'غير مصرح لك بإدارة تسويات المخزون.');

        $warehouses = Warehouse::orderBy('name_ar')->get();
        $reasons = InventoryAdjustmentReason::where('is_active', true)->orderBy('name_ar')->get();
        $lots = InventoryLot::with(['material.baseUnit', 'warehouse'])
            ->where('remaining_quantity', '>', 0)
            ->orderBy('lot_code')
            ->get();

        return view('inventory.adjustments.create', compact('warehouses', 'reasons', 'lots'));
    }

    public function store(InventoryAdjustmentRequest $request): RedirectResponse
    {
        $adjustment = $this->inventoryService->createAdjustment(
            $request->validated(),
            $request->user()->id
        );

        if ($request->input('action') === 'post') {
            try {
                $this->inventoryService->postAdjustment($adjustment, $request->user()->id);

                return redirect()
                    ->route('inventory.adjustments.show', $adjustment)
                    ->with('success', __('تم إنشاء وتسجيل تسوية المخزون بنجاح.'));
            } catch (\Exception $e) {
                return redirect()
                    ->route('inventory.adjustments.show', $adjustment)
                    ->with('error', $e->getMessage());
            }
        }

        return redirect()
            ->route('inventory.adjustments.show', $adjustment)
            ->with('success', __('تم حفظ مسودة تسوية المخزون بنجاح.'));
    }

    public function show(InventoryAdjustment $adjustment): View
    {
        abort_if(! request()->user()->can('inventory.adjust'), 403, 'غير مصرح لك بإدارة تسويات المخزون.');

        $adjustment->load(['warehouse', 'reason', 'user', 'postedBy', 'lines.lot.material.baseUnit', 'lines.material.baseUnit']);

        return view('inventory.adjustments.show', compact('adjustment'));
    }

    public function post(InventoryAdjustment $adjustment, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.adjust'), 403, 'غير مصرح لك بإدارة تسويات المخزون.');

        try {
            $this->inventoryService->postAdjustment($adjustment, $request->user()->id);

            return redirect()
                ->route('inventory.adjustments.show', $adjustment)
                ->with('success', __('تم ترحيل وتسجيل تسوية المخزون بنجاح.'));
        } catch (\Exception $e) {
            return redirect()
                ->route('inventory.adjustments.show', $adjustment)
                ->with('error', $e->getMessage());
        }
    }
}
