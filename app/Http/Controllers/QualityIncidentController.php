<?php

namespace App\Http\Controllers;

use App\Http\Requests\QualityIncidentFormRequest;
use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\QualityIncident;
use App\Services\QualityIncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityIncidentController extends Controller
{
    public function __construct(
        protected QualityIncidentService $incidentService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = QualityIncident::with([
            'productionOrder',
            'operation',
            'detectedDepartment',
            'responsibleDepartment',
            'detectedByUser',
            'decidedByUser',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('incident_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn ($po) => $po->where('production_order_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $incidents = $query->latest()->paginate(15)->withQueryString();

        return view('production.quality_incidents.index', compact('incidents'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('production.report_quality'), 403);

        $orderId = $request->query('production_order_id');
        $productionOrder = ProductionOrder::with('operations.workCenter.department')->findOrFail($orderId);
        $departments = Department::all();

        return view('production.quality_incidents.create', compact('productionOrder', 'departments'));
    }

    public function store(QualityIncidentFormRequest $request): RedirectResponse
    {
        $productionOrder = ProductionOrder::findOrFail($request->production_order_id);

        $incident = $this->incidentService->createIncident($productionOrder, $request->user(), $request->validated());

        return redirect()->route('production.quality-incidents.show', $incident)
            ->with('success', "تم تسجيل بلاغ الجودة رقم {$incident->incident_number} بنجاح.");
    }

    public function show(Request $request, QualityIncident $qualityIncident): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $qualityIncident->load([
            'productionOrder.productModel',
            'productionOrder.productConfiguration',
            'operation.workCenter',
            'detectedDepartment',
            'responsibleDepartment',
            'detectedByUser',
            'decidedByUser',
            'reworkActions.assignedDepartment',
            'reworkActions.authorizedByUser',
            'reworkActions.sourceOperation',
            'reworkActions.targetOperation',
            'wasteRecords.material.baseUnit',
            'wasteRecords.wasteReason',
            'wasteRecords.recordedByUser',
        ]);

        $departments = Department::all();

        return view('production.quality_incidents.show', compact('qualityIncident', 'departments'));
    }

    public function updateDisposition(Request $request, QualityIncident $qualityIncident): RedirectResponse
    {
        abort_if(! $request->user()->can('production.manage_quality'), 403);

        $request->validate([
            'disposition' => 'required|string|in:REPAIR,REWORK,REMANUFACTURE,SCRAP,ACCEPT_AS_IS',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->incidentService->setDisposition(
            $qualityIncident,
            $request->user(),
            $request->input('disposition'),
            $request->input('notes')
        );

        return redirect()->back()->with('success', "تم تحديث قرار معالجة حالة الجودة إلى: {$qualityIncident->disposition}");
    }
}
