<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierQuotationFormRequest;
use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRfq;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\UnitOfMeasure;
use App\Services\SupplierQuotationService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierQuotationController extends Controller
{
    public function __construct(
        protected SupplierQuotationService $quotationService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $quotations = SupplierQuotation::with(['supplier', 'purchaseRfq', 'createdByUser', 'selectedByUser'])
            ->latest()
            ->paginate(15);

        return view('purchasing.quotations.index', compact('quotations'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.manage_quotes'), 403);

        $rfqId = $request->query('rfq_id');
        $rfq = $rfqId ? PurchaseRfq::with('purchaseRequest.lines.material', 'rfqSuppliers.supplier')->find($rfqId) : null;

        $suppliers = Supplier::where('is_active', true)->get();
        $materials = Material::where('is_active', true)->with('unitOfMeasure', 'fabricColors')->get();
        $units = UnitOfMeasure::where('is_active', true)->get();

        return view('purchasing.quotations.create', compact('rfq', 'suppliers', 'materials', 'units'));
    }

    public function store(SupplierQuotationFormRequest $request): RedirectResponse
    {
        try {
            $quotation = $this->quotationService->recordQuotation($request->validated(), $request->user());

            return redirect()->route('purchasing.quotations.show', $quotation)
                ->with('success', "تم تسجل عرض سعر المورد رقم {$quotation->supplier_quotation_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(SupplierQuotation $supplierQuotation, Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $supplierQuotation->load(['supplier', 'purchaseRfq', 'createdByUser', 'selectedByUser', 'lines.material.unitOfMeasure', 'lines.fabricColor', 'lines.purchaseUnit']);

        return view('purchasing.quotations.show', compact('supplierQuotation'));
    }

    public function select(SupplierQuotation $supplierQuotation, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.manage_quotes'), 403);

        try {
            $this->quotationService->selectQuotation($supplierQuotation, $request->user());

            return back()->with('success', 'تم تحديد واعتماد عرض سعر المورد كمرجعية تجارية بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Matrix comparison page for supplier quotations against a Purchase Request or Material.
     */
    public function compare(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $prId = $request->query('purchase_request_id');
        $purchaseRequest = $prId ? PurchaseRequest::with('lines.material')->find($prId) : null;
        $approvedRequests = PurchaseRequest::where('status', 'APPROVED')->get();

        $quotationsQuery = SupplierQuotation::with(['supplier', 'lines.material.unitOfMeasure', 'lines.purchaseUnit', 'lines.fabricColor']);

        if ($purchaseRequest) {
            $rfqIds = PurchaseRfq::where('purchase_request_id', $purchaseRequest->id)->pluck('id');
            $quotationsQuery->whereIn('purchase_rfq_id', $rfqIds);
        }

        $quotations = $quotationsQuery->get();

        // Group lines by material to build the side-by-side comparison matrix
        $comparisonMatrix = [];
        foreach ($quotations as $quote) {
            foreach ($quote->lines as $line) {
                $matKey = $line->material_id.'_'.($line->fabric_color_id ?? '0');
                if (! isset($comparisonMatrix[$matKey])) {
                    $comparisonMatrix[$matKey] = [
                        'material_id' => $line->material_id,
                        'material_name' => $line->material->name_ar,
                        'color_name' => $line->fabricColor?->color_name_ar,
                        'base_unit' => $line->material->unitOfMeasure?->name_ar,
                        'quotes' => [],
                    ];
                }

                $comparisonMatrix[$matKey]['quotes'][] = [
                    'quotation_id' => $quote->id,
                    'quotation_number' => $quote->supplier_quotation_number,
                    'supplier_name' => $quote->supplier->name,
                    'quoted_quantity' => (float) $line->quoted_quantity,
                    'purchase_unit' => $line->purchaseUnit?->name_ar,
                    'unit_price' => (float) $line->unit_price,
                    'base_unit_equivalent_price' => (float) $line->base_unit_equivalent_price,
                    'line_total' => (float) $line->line_total,
                    'lead_time_days' => $line->lead_time_days ?? $quote->lead_time_days,
                    'is_selected' => $quote->status === 'SELECTED',
                ];
            }
        }

        return view('purchasing.quotations.compare', compact('purchaseRequest', 'approvedRequests', 'comparisonMatrix'));
    }
}
