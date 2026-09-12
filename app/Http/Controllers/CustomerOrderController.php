<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrderRequest;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerProductAlias;
use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductModel;
use App\Models\SalesChannel;
use App\Services\CustomerOrderService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function __construct(protected CustomerOrderService $customerOrderService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('orders.view'), 403, 'غير مصرح لك بعرض طلبات العملاء.');

        $orders = CustomerOrder::with('customer', 'salesChannel', 'createdBy')
            ->filter($request->only(['search', 'status', 'priority', 'customer_id', 'sales_channel_id']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();

        return view('sales.orders.index', compact('orders', 'customers', 'salesChannels'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('orders.create'), 403, 'غير مصرح لك بإنشاء طلب عميل جديد.');

        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();
        $productModels = ProductModel::where('is_active', true)->with('configurations', 'aliases')->get();
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.orders.create', compact('customers', 'salesChannels', 'productModels', 'fabrics', 'colors', 'aliases'));
    }

    public function store(CustomerOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->customerOrderService->createOrder(
                $request->validated(),
                $request->validated('lines'),
                $request->user()
            );

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم إنشاء طلب العميل بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, CustomerOrder $order): View
    {
        abort_if(! $request->user()->can('orders.view'), 403, 'غير مصرح لك بعرض هذا الطلب.');

        $order->load([
            'customer',
            'salesChannel',
            'createdBy',
            'productionReviewedBy',
            'productionApprovedBy',
            'lines.productModel',
            'lines.productConfiguration',
            'lines.customerProductAlias',
            'lines.fabricMaterial',
            'lines.fabricColor',
            'changes.requestedBy',
            'changes.approvedBy',
        ]);

        return view('sales.orders.show', compact('order'));
    }

    public function edit(Request $request, CustomerOrder $order): View
    {
        abort_if(! $request->user()->can('orders.update'), 403, 'غير مصرح لك بتعديل هذا الطلب.');

        $order->load('lines');
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();
        $productModels = ProductModel::where('is_active', true)->with('configurations', 'aliases')->get();
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.orders.edit', compact('order', 'customers', 'salesChannels', 'productModels', 'fabrics', 'colors', 'aliases'));
    }

    public function update(CustomerOrderRequest $request, CustomerOrder $order): RedirectResponse
    {
        try {
            $this->customerOrderService->updateOrder(
                $order,
                $request->validated(),
                $request->validated('lines'),
                $request->user()
            );

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم تحديث طلب العميل وسجل التغييرات بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function submitReview(Request $request, CustomerOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('orders.update') && ! $request->user()->can('orders.create'), 403, 'غير مصرح لك بإرسال الطلب للمراجعة.');

        try {
            $this->customerOrderService->submitForProductionReview($order);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم تقديم الطلب لمراجعة مدير الإنتاج بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, CustomerOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('orders.cancel'), 403, 'غير مصرح لك بإلغاء الطلب.');

        try {
            $this->customerOrderService->cancelOrder($order);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم إلغاء الطلب بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
