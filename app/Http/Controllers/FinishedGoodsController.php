<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinishedGoodsReceiptFormRequest;
use App\Models\CustomerReturn;
use App\Models\FinishedGoodsMovement;
use App\Models\FinishedGoodsReceipt;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\FinishedGoodsService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinishedGoodsController extends Controller
{
    public function __construct(
        protected FinishedGoodsService $fgService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('finished_goods.view'), 403);

        $query = ProductionOrder::with([
            'customerOrder.customer',
            'customerOrderLine.productModel',
            'finishedGoodsReceipts',
        ])->whereIn('status', ['IN_PROGRESS', 'COMPLETED']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('production_order_number', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', fn ($co) => $co->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
            });
        }

        $productionOrders = $query->latest()->paginate(15)->withQueryString();

        $quarantineId = $this->fgService->quarantineWarehouse()->id;
        $quarantineCount = FinishedGoodsMovement::where('warehouse_id', $quarantineId)
            ->selectRaw("production_order_id, SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('production_order_id')
            ->havingRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) > 0")
            ->get()
            ->count();

        return view('finished_goods.index', compact('productionOrders', 'quarantineCount'));
    }

    public function createReceipt(Request $request): View
    {
        abort_if(! $request->user()->can('finished_goods.receive'), 403);

        $orderId = $request->query('production_order_id');
        $productionOrder = ProductionOrder::with('customerOrder.customer', 'customerOrderLine.productModel')->findOrFail($orderId);

        $warehouses = Warehouse::active()->get();
        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first() ?? $warehouses->first();

        $alreadyReceived = $this->fgService->getTotalReceivedQuantity($productionOrder);
        $maxEligible = max(0.0, (float) $productionOrder->completed_quantity - $alreadyReceived);

        return view('finished_goods.receipts.create', compact('productionOrder', 'warehouses', 'fgWarehouse', 'alreadyReceived', 'maxEligible'));
    }

    public function storeReceipt(FinishedGoodsReceiptFormRequest $request): RedirectResponse
    {
        $productionOrder = ProductionOrder::findOrFail($request->production_order_id);

        try {
            $receipt = $this->fgService->createReceipt($productionOrder, $request->user(), $request->validated());

            if ($request->input('action') === 'post') {
                $this->fgService->postReceipt($receipt, $request->user());

                return redirect()->route('finished-goods.receipts.show', $receipt)
                    ->with('success', "تم إنشاء وترحيل سند تسليم المنتجات الجاهزة رقم {$receipt->receipt_number} بنجاح.");
            }

            return redirect()->route('finished-goods.receipts.show', $receipt)
                ->with('success', "تم إنشاء مسودة سند تسليم المنتجات الجاهزة رقم {$receipt->receipt_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showReceipt(FinishedGoodsReceipt $receipt, Request $request): View
    {
        abort_if(! $request->user()->can('finished_goods.view'), 403);

        $receipt->load(['productionOrder.customerOrder.customer', 'warehouse', 'createdByUser', 'receivedByUser', 'movements']);

        return view('finished_goods.receipts.show', compact('receipt'));
    }

    public function postReceipt(FinishedGoodsReceipt $receipt, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('finished_goods.receive'), 403);

        try {
            $this->fgService->postReceipt($receipt, $request->user());

            return redirect()->route('finished-goods.receipts.show', $receipt)
                ->with('success', "تم ترحيل سند تسليم المنتجات الجاهزة رقم {$receipt->receipt_number} وإيداعها في المخزون بنجاح.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Inspection area: goods refused at the door or returned, waiting for a decision.
     */
    public function quarantine(Request $request): View
    {
        abort_if(! $request->user()->can('finished_goods.view'), 403);

        $quarantineId = $this->fgService->quarantineWarehouse()->id;
        $balances = FinishedGoodsMovement::query()
            ->where('warehouse_id', $quarantineId)
            ->selectRaw("production_order_id, SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) as quantity")
            ->groupBy('production_order_id')
            ->havingRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) > 0")
            ->pluck('quantity', 'production_order_id');

        $productionOrders = ProductionOrder::with(['customerOrder.customer', 'productModel'])
            ->whereIn('id', $balances->keys())
            ->get();
        $openReturns = CustomerReturn::whereIn('production_order_id', $balances->keys())
            ->whereIn('status', ['RECEIVED', 'LINKED_TO_QUALITY', 'UNDER_REPAIR'])
            ->get()
            ->groupBy('production_order_id');

        return view('finished_goods.quarantine', compact('productionOrders', 'balances', 'openReturns'));
    }

    public function releaseQuarantine(ProductionOrder $productionOrder, Request $request): RedirectResponse
    {
        return $this->quarantineDecision($productionOrder, $request, 'release');
    }

    public function scrapQuarantine(ProductionOrder $productionOrder, Request $request): RedirectResponse
    {
        return $this->quarantineDecision($productionOrder, $request, 'scrap');
    }

    private function quarantineDecision(ProductionOrder $productionOrder, Request $request, string $decision): RedirectResponse
    {
        abort_if(! $request->user()->can('finished_goods.receive'), 403);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['required', 'string', 'max:500'],
        ], ['notes.required' => 'اكتب نتيجة الفحص.']);

        try {
            $note = ($decision === 'release' ? 'نتيجة الفحص: سليم — ' : 'نتيجة الفحص: إتلاف — ').$data['notes'];
            $decision === 'release'
                ? $this->fgService->releaseFromQuarantine($productionOrder, (float) $data['quantity'], $request->user(), $note)
                : $this->fgService->scrapFromQuarantine($productionOrder, (float) $data['quantity'], $request->user(), $note);

            return back()->with('success', $decision === 'release' ? 'أعيدت الكمية للمخزون المتاح.' : 'تم تسجيل إتلاف الكمية.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
