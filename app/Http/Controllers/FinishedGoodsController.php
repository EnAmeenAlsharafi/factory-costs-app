<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinishedGoodsReceiptFormRequest;
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
                        ->orWhereHas('customer', fn ($c) => $c->where('name_ar', 'like', "%{$search}%")));
            });
        }

        $productionOrders = $query->latest()->paginate(15)->withQueryString();

        return view('finished_goods.index', compact('productionOrders'));
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
}
