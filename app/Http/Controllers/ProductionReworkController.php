<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReworkActionFormRequest;
use App\Models\ProductionReworkAction;
use App\Models\QualityIncident;
use App\Services\QualityIncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReworkController extends Controller
{
    public function __construct(
        protected QualityIncidentService $incidentService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionReworkAction::with([
            'qualityIncident',
            'productionOrder',
            'assignedDepartment',
            'authorizedByUser',
            'sourceOperation',
            'targetOperation',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rework_number', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn ($po) => $po->where('production_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('qualityIncident', fn ($qi) => $qi->where('incident_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reworks = $query->latest()->paginate(15)->withQueryString();

        return view('production.rework.index', compact('reworks'));
    }

    public function store(ReworkActionFormRequest $request): RedirectResponse
    {
        $incident = QualityIncident::findOrFail($request->quality_incident_id);

        $rework = $this->incidentService->createReworkAction($incident, $request->user(), $request->validated());

        return redirect()->back()->with('success', "تم إنشاء أمر إعادة التصنيع/الإصلاح رقم {$rework->rework_number} بنجاح.");
    }

    public function complete(Request $request, ProductionReworkAction $reworkAction): RedirectResponse
    {
        abort_if(! $request->user()->can('production.manage_rework'), 403);

        $this->incidentService->completeReworkAction($reworkAction, $request->user());

        return redirect()->back()->with('success', "تم إنجاز وتوثيق عملية إعادة التصنيع رقم {$reworkAction->rework_number} بنجاح.");
    }
}
