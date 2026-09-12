<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationProgressRequest;
use App\Models\Department;
use App\Models\ProductionOrderOperation;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentQueueController extends Controller
{
    public function __construct(protected ProductionOrderService $productionOrderService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->can('production.view'), 403);

        $query = ProductionOrderOperation::with([
            'productionOrder.customerOrder.customer',
            'productionOrder.productModel',
            'productionOrder.fabricMaterial',
            'productionOrder.fabricColor',
            'workCenter.department',
        ])
            ->whereHas('productionOrder', function ($po) {
                $po->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED']);
            })
            ->whereIn('status', ['READY', 'IN_PROGRESS', 'PARTIALLY_COMPLETED']);

        // Department isolation for operational workers
        if (! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
            if ($user->department_id) {
                $query->whereHas('workCenter', function ($wc) use ($user) {
                    $wc->where('department_id', $user->department_id);
                });
            }
        } elseif ($request->filled('department_id')) {
            $query->whereHas('workCenter', function ($wc) use ($request) {
                $wc->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('priority')) {
            $query->whereHas('productionOrder', fn ($po) => $po->where('priority', $request->priority));
        }

        $operations = $query->orderBy('sequence_number')->latest()->paginate(20)->withQueryString();
        $departments = Department::production()->active()->orderBy('sort_order')->get();

        return view('production.queue.index', compact('operations', 'departments'));
    }

    public function updateProgress(OperationProgressRequest $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.update_progress'), 403);

        $this->productionOrderService->recordProgress(
            $operation,
            (int) $request->added_quantity,
            'PROGRESS',
            $request->notes,
            $request->user()
        );

        return redirect()->back()->with('success', 'تم تسجيل إنجاز العملية بنجاح.');
    }

    public function correctProgress(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.correct_progress'), 403);

        $request->validate([
            'new_completed_quantity' => ['required', 'integer', 'min:0'],
            'correction_notes' => ['required', 'string', 'max:500'],
        ]);

        $this->productionOrderService->correctProgress(
            $operation,
            (int) $request->new_completed_quantity,
            $request->correction_notes,
            $request->user()
        );

        return redirect()->back()->with('success', 'تم تصحيح كمية الإنجاز بنجاح.');
    }
}
