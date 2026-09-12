<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialIssueRequest;
use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialIssueController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض سندات الصرف.');

        $query = MaterialIssue::with(['warehouse', 'department', 'createdByUser', 'issuedByUser']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('issue_number', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%");
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $issues = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $departments = Department::where('is_active', true)->orderBy('name_ar')->get();

        return view('inventory.issues.index', compact('issues', 'departments'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.issue'), 403, 'غير مصرح لك بإضافة سند صرف مواد.');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name_ar')->get();
        $departments = Department::where('is_active', true)->orderBy('name_ar')->get();
        $materials = Material::with(['category', 'baseUnit'])->where('is_active', true)->orderBy('name_ar')->get();
        $units = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();

        // Active Lots available for issue
        $lots = InventoryLot::with(['material', 'fabricColor', 'supplier', 'baseUnit'])
            ->where('status', 'ACTIVE')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date', 'asc')
            ->get();

        $nextIssueNumber = DocumentNumberService::generateIssueNumber();

        return view('inventory.issues.create', compact('warehouses', 'departments', 'materials', 'units', 'lots', 'nextIssueNumber'));
    }

    public function store(MaterialIssueRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $issue = DB::transaction(function () use ($request, $validated) {
            $issue = MaterialIssue::create([
                'issue_number' => DocumentNumberService::generateIssueNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'issue_date' => $validated['issue_date'],
                'department_id' => $validated['department_id'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'DRAFT',
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $lineData) {
                $lot = InventoryLot::findOrFail($lineData['inventory_lot_id']);
                $qty = (float) $lineData['issued_quantity'];
                $unitCost = (float) $lot->unit_cost;
                $totalCost = round($qty * $unitCost, 4);

                MaterialIssueLine::create([
                    'material_issue_id' => $issue->id,
                    'material_id' => $lot->material_id,
                    'fabric_color_id' => $lot->fabric_color_id,
                    'inventory_lot_id' => $lot->id,
                    'requested_quantity' => $lineData['requested_quantity'] ?? $qty,
                    'issued_quantity' => $qty,
                    'base_unit_id' => $lot->base_unit_id,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            return $issue;
        });

        if ($request->has('post_immediately') || $request->input('action') === 'post') {
            try {
                $this->inventoryService->postIssue($issue, $request->user());

                return redirect()->route('inventory.issues.show', $issue)
                    ->with('success', 'تم تسجيل وإعتماد سند الصرف وخصم المخزون بنجاح.');
            } catch (Exception $e) {
                return redirect()->route('inventory.issues.show', $issue)
                    ->with('error', 'تم حفظ السند كمسودة فقط ولكن تعذر الاعتماد: '.$e->getMessage());
            }
        }

        return redirect()->route('inventory.issues.show', $issue)
            ->with('success', 'تم حفظ سند الصرف كمسودة بنجاح.');
    }

    public function show(Request $request, MaterialIssue $issue): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض سند الصرف.');

        $issue->load(['warehouse', 'department', 'createdByUser', 'issuedByUser', 'lines.material', 'lines.fabricColor', 'lines.lot', 'lines.baseUnit']);

        return view('inventory.issues.show', compact('issue'));
    }

    public function post(Request $request, MaterialIssue $issue): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.issue'), 403, 'غير مصرح لك باعتتماد سند صرف.');

        try {
            $this->inventoryService->postIssue($issue, $request->user());

            return back()->with('success', 'تم اعتماد سند الصرف وخصم اللوتات وتحديث حركة المخزون بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
