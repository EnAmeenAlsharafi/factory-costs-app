<?php

namespace App\Http\Controllers;

use App\Models\MaterialCategory;
use App\Models\Warehouse;
use App\Services\ProcurementPlanningService;
use App\Services\PurchaseRequestService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementPlanningController extends Controller
{
    public function __construct(
        protected ProcurementPlanningService $planningService,
        protected PurchaseRequestService $requestService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $onlyDeficit = $request->boolean('only_deficit', false);

        $planningMatrix = $this->planningService->getPlanningMatrix($categoryId, $onlyDeficit);
        $categories = MaterialCategory::where('is_active', true)->get();
        $warehouses = Warehouse::active()->get();

        return view('purchasing.planning.index', compact('planningMatrix', 'categories', 'warehouses'));
    }

    public function createBulkRequest(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.request'), 403);

        $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $pr = $this->requestService->createFromLowStock(
                $request->items,
                $request->user(),
                $request->warehouse_id
            );

            return redirect()->route('purchasing.requests.show', $pr)
                ->with('success', "تم توليد طلب الشراء بنجاح برقم {$pr->request_number}.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
