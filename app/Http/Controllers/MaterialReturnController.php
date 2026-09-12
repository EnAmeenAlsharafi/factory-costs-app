<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialReturnRequest;
use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\MaterialIssueLine;
use App\Models\MaterialReturn;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialReturnController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض مرتجعات المواد.');

        $query = MaterialReturn::with(['warehouse', 'department', 'lines', 'createdByUser', 'receivedByUser']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('return_number', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $returns = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $departments = Department::orderBy('name_ar')->get();

        return view('inventory.returns.index', compact('returns', 'departments'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.return'), 403, 'غير مصرح لك بإضافة سند مرتجع.');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name_ar')->get();
        $departments = Department::where('is_active', true)->orderBy('name_ar')->get();
        $lots = InventoryLot::with(['material.baseUnit'])->latest('id')->get();
        $units = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();

        // Issued lines available for return
        $issueLines = MaterialIssueLine::with(['issue', 'material', 'fabricColor', 'lot', 'baseUnit'])
            ->whereHas('issue', fn ($q) => $q->where('status', 'POSTED'))
            ->get();

        $nextReturnNumber = DocumentNumberService::generateReturnNumber();

        return view('inventory.returns.create', compact('warehouses', 'departments', 'lots', 'units', 'issueLines', 'nextReturnNumber'));
    }

    public function store(MaterialReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $return = $this->inventoryService->createReturn($validated, $request->user()->id);

        if ($request->has('post_immediately') || $request->input('action') === 'post') {
            try {
                $this->inventoryService->postReturn($return, $request->user());

                return redirect()->route('inventory.returns.show', $return)
                    ->with('success', 'تم تسجيل وإعتماد سند المرتجع وإعادة الكمية للوت بنجاح.');
            } catch (Exception $e) {
                return redirect()->route('inventory.returns.show', $return)
                    ->with('error', 'تم حفظ السند كمسودة ولكن تعذر الاعتماد: '.$e->getMessage());
            }
        }

        return redirect()->route('inventory.returns.show', $return)
            ->with('success', 'تم حفظ سند المرتجع كمسودة بنجاح.');
    }

    public function show(Request $request, MaterialReturn $return): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض سند المرتجع.');

        $return->load(['warehouse', 'department', 'lines.material.baseUnit', 'lines.fabricColor', 'lines.lot', 'lines.originalIssueLine', 'createdByUser', 'receivedByUser']);

        return view('inventory.returns.show', compact('return'));
    }

    public function post(Request $request, MaterialReturn $return): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.return'), 403, 'غير مصرح لك باعتتماد سند مرتجع.');

        try {
            $this->inventoryService->postReturn($return, $request->user());

            return back()->with('success', 'تم اعتماد سند المرتجع وإعادة الكمية للوت المخزني بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
