<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderFormRequest;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceivingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $orderService,
        protected PurchaseReceivingService $receivingService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $query = PurchaseOrder::with(['supplier', 'warehouse', 'createdByUser', 'approvedByUser', 'lines']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchase_order_number', 'like', "%{$search}%")
                    ->orWhere('supplier_reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $purchaseOrders = $query->latest()->paginate(15)->withQueryString();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('purchasing.orders.index', compact('purchaseOrders', 'suppliers'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);

        $prId = $request->query('purchase_request_id');
        $sqId = $request->query('supplier_quotation_id');

        $purchaseRequest = $prId ? PurchaseRequest::with('lines.material', 'lines.fabricColor', 'warehouse')->find($prId) : null;
        $supplierQuotation = $sqId ? SupplierQuotation::with('lines.material', 'lines.fabricColor', 'supplier')->find($sqId) : null;

        $suppliers = Supplier::where('is_active', true)->get();
        $warehouses = Warehouse::active()->get();
        $materials = Material::where('is_active', true)->with('unitOfMeasure', 'fabricColors')->get();
        $units = UnitOfMeasure::where('is_active', true)->get();

        return view('purchasing.orders.create', compact('purchaseRequest', 'supplierQuotation', 'suppliers', 'warehouses', 'materials', 'units'));
    }

    public function store(PurchaseOrderFormRequest $request): RedirectResponse
    {
        try {
            $po = $this->orderService->createOrder($request->validated(), $request->user());

            if ($request->input('action') === 'approve' && $request->user()->can('purchasing.approve_po')) {
                $this->orderService->approveOrder($po, $request->user());
            }

            return redirect()->route('purchasing.orders.show', $po)
                ->with('success', "تم إنشاء أمر الشراء رقم {$po->purchase_order_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder, Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $purchaseOrder->load([
            'supplier',
            'warehouse',
            'createdByUser',
            'approvedByUser',
            'lines.material.unitOfMeasure',
            'lines.fabricColor',
            'lines.purchaseUnit',
            'materialReceipts.lines',
        ]);

        $varianceSummary = $this->receivingService->getPoPriceVarianceSummary($purchaseOrder);

        return view('purchasing.orders.show', compact('purchaseOrder', 'varianceSummary'));
    }

    public function approve(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.approve_po'), 403);

        try {
            $this->orderService->approveOrder($purchaseOrder, $request->user());

            return back()->with('success', 'تم اعتماد أمر الشراء بنجاح وتثبيت البنود التجارية.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function close(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.cancel_po'), 403);

        $request->validate([
            'close_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->orderService->closeOrder($purchaseOrder, $request->user(), $request->close_reason);

            return back()->with('success', 'تم إغلاق المتبقي من أمر الشراء بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.cancel_po'), 403);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->orderService->cancelOrder($purchaseOrder, $request->user(), $request->reason);

            return back()->with('success', 'تم إلغاء أمر الشراء بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receiveForm(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.receive'), 403);

        try {
            $receipt = $this->receivingService->createDraftReceiptFromPO($purchaseOrder, $request->user());

            return redirect()->route('inventory.receipts.show', $receipt)
                ->with('success', "تم إنشاء مسودة سند استلام رقم {$receipt->receipt_number} المرتبط بأمر الشراء {$purchaseOrder->purchase_order_number}. يمكنك مراجعة الكميات ثم ترحيل السند للمخزون.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Browser-printable HTML Purchase Order view.
     */
    public function print(PurchaseOrder $purchaseOrder, Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $purchaseOrder->load(['supplier', 'warehouse', 'lines.material', 'lines.fabricColor', 'lines.purchaseUnit', 'approvedByUser']);

        return view('purchasing.orders.print', compact('purchaseOrder'));
    }
}
