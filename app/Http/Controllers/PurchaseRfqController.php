<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRfq;
use App\Models\Supplier;
use App\Services\SupplierQuotationService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseRfqController extends Controller
{
    public function __construct(
        protected SupplierQuotationService $quotationService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $rfqs = PurchaseRfq::with(['purchaseRequest', 'createdByUser', 'rfqSuppliers.supplier'])
            ->latest()
            ->paginate(15);

        return view('purchasing.rfqs.index', compact('rfqs'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.manage_rfq'), 403);

        $approvedRequests = PurchaseRequest::where('status', 'APPROVED')->with('lines.material')->get();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('purchasing.rfqs.create', compact('approvedRequests', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.manage_rfq'), 403);

        $request->validate([
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'issue_date' => ['required', 'date'],
            'response_due_date' => ['nullable', 'date'],
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $rfq = $this->quotationService->createRfq($request->all(), $request->user());

            return redirect()->route('purchasing.rfqs.show', $rfq)
                ->with('success', "تم إنشاء طلب عرض السعر رقم {$rfq->rfq_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(PurchaseRfq $purchaseRfq, Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $purchaseRfq->load(['purchaseRequest.lines.material', 'createdByUser', 'rfqSuppliers.supplier', 'supplierQuotations.supplier']);

        return view('purchasing.rfqs.show', compact('purchaseRfq'));
    }
}
