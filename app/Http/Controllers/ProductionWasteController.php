<?php

namespace App\Http\Controllers;

use App\Http\Requests\WasteRecordFormRequest;
use App\Models\Department;
use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\QualityIncident;
use App\Models\UnitOfMeasure;
use App\Services\DocumentNumberService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionWasteController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionWasteRecord::visibleTo($request->user())->with([
            'productionOrder',
            'operation',
            'qualityIncident',
            'material.baseUnit',
            'fabricColor',
            'wasteReason',
            'detectedDepartment',
            'responsibleDepartment',
            'recordedByUser',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('waste_number', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn ($po) => $po->where('production_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('material', fn ($m) => $m->where('name_ar', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('reason_id')) {
            $query->where('waste_reason_id', $request->reason_id);
        }

        if ($request->filled('department_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('detected_department_id', $request->department_id)
                    ->orWhere('responsible_department_id', $request->department_id);
            });
        }

        $wasteRecords = $query->latest('occurred_at')->paginate(15)->withQueryString();
        $reasons = ProductionWasteReason::where('is_active', true)->get();
        $departments = Department::query()
            ->when($request->user()->isDepartmentRestricted(), fn ($q) => $q->whereKey($request->user()->productionDepartmentId()))
            ->get();

        return view('production.waste.index', compact('wasteRecords', 'reasons', 'departments'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('production.record_waste'), 403);

        $orderId = $request->query('production_order_id');
        $incidentId = $request->query('quality_incident_id');

        $productionOrder = ProductionOrder::visibleTo($request->user())->with('operations.workCenter.department')->findOrFail($orderId);
        $qualityIncident = $incidentId ? QualityIncident::find($incidentId) : null;

        $materials = Material::with('baseUnit')->where('is_active', true)->get();
        $fabricColors = FabricColor::where('is_active', true)->get();
        $reasons = ProductionWasteReason::where('is_active', true)->get();
        $departments = Department::all();
        $units = UnitOfMeasure::all();
        $lots = InventoryLot::where('remaining_quantity', '>', 0)
            ->with(['material', 'fabricColor'])
            ->get();

        return view('production.waste.create', compact(
            'productionOrder',
            'qualityIncident',
            'materials',
            'fabricColors',
            'reasons',
            'departments',
            'units',
            'lots'
        ));
    }

    public function store(WasteRecordFormRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $waste = DB::transaction(function () use ($data, $request) {
                $wasteNumber = DocumentNumberService::generateWasteNumber();

                $lot = ! empty($data['inventory_lot_id'])
                    ? InventoryLot::where('id', $data['inventory_lot_id'])->lockForUpdate()->first()
                    : null;
                $material = Material::findOrFail($data['material_id']);

                $unitCost = $lot ? (float) $lot->unit_cost : (float) $material->average_unit_cost;
                $quantity = (float) $data['quantity'];
                $totalCost = round($quantity * $unitCost, 4);

                $waste = ProductionWasteRecord::create([
                    'waste_number' => $wasteNumber,
                    'production_order_id' => $data['production_order_id'],
                    'production_order_operation_id' => $data['production_order_operation_id'] ?? null,
                    'quality_incident_id' => $data['quality_incident_id'] ?? null,
                    'material_issue_line_id' => $data['material_issue_line_id'] ?? null,
                    'inventory_lot_id' => $lot?->id,
                    'material_id' => $material->id,
                    'fabric_color_id' => $data['fabric_color_id'] ?? ($lot?->fabric_color_id ?? null),
                    'quantity' => $quantity,
                    'unit_id' => $data['unit_id'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'waste_reason_id' => $data['waste_reason_id'],
                    'detected_department_id' => $data['detected_department_id'] ?? $request->user()->department_id,
                    'responsible_department_id' => $data['responsible_department_id'] ?? null,
                    'recorded_by_user_id' => $request->user()->id,
                    'notes' => $data['notes'] ?? null,
                    'occurred_at' => $data['occurred_at'],
                ]);

                if ($lot) {
                    if ($quantity > $lot->remaining_quantity) {
                        throw new Exception("الكمية المسجلة كهدر ({$quantity}) تتجاوز الكمية المتاحة في اللوت {$lot->lot_code} ({$lot->remaining_quantity}).");
                    }

                    $lot->remaining_quantity -= $quantity;
                    if ($lot->remaining_quantity <= 0) {
                        $lot->remaining_quantity = 0;
                        $lot->status = 'EXHAUSTED';
                    }
                    $lot->save();

                    InventoryMovement::create([
                        'movement_number' => DocumentNumberService::generateMovementNumber(),
                        'movement_type' => 'ADJUSTMENT_OUT',
                        'material_id' => $material->id,
                        'fabric_color_id' => $waste->fabric_color_id,
                        'warehouse_id' => $lot->warehouse_id,
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $quantity,
                        'unit_id' => $data['unit_id'],
                        'direction' => 'OUT',
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'reference_type' => ProductionWasteRecord::class,
                        'reference_id' => $waste->id,
                        'occurred_at' => $data['occurred_at'],
                        'performed_by_user_id' => $request->user()->id,
                        'notes' => "هدر رقم {$waste->waste_number}",
                    ]);
                }

                if (! empty($data['quality_incident_id'])) {
                    $incident = QualityIncident::find($data['quality_incident_id']);
                    if ($incident && $incident->disposition === 'SCRAP' && $incident->status !== 'RESOLVED') {
                        $incident->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
                    }
                }

                return $waste;
            });

            return redirect()->route('production.waste.index')
                ->with('success', "تم تسجيل كمية الهدر رقم {$waste->waste_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, ProductionWasteRecord $waste): RedirectResponse
    {
        abort_if(! $request->user()->can('production.approve_waste'), 403);

        if ($waste->approved_by_user_id === null) {
            $waste->update(['approved_by_user_id' => $request->user()->id]);
        }

        return redirect()->back()->with('success', "تم اعتماد سجل الهدر رقم {$waste->waste_number}.");
    }
}
