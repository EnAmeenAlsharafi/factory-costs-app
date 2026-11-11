<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrderRequest;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerProductAlias;
use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductModel;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Services\CustomerOrderService;
use App\Services\OrderTimelineService;
use App\Services\OrderTrackingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerOrderController extends Controller
{
    public function __construct(
        protected CustomerOrderService $customerOrderService,
        protected OrderTrackingService $trackingService,
        protected OrderTimelineService $timelineService,
    ) {}

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
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors', 'suppliers')->get();
        $suppliers = Supplier::where('is_active', true)->with('materials')->orderBy('name')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.orders.create', compact('customers', 'salesChannels', 'productModels', 'fabrics', 'suppliers', 'colors', 'aliases'));
    }

    public function store(CustomerOrderRequest $request): RedirectResponse
    {
        $storedImages = [];
        try {
            $lines = $this->storeDesignImages($request->validated('lines'), $storedImages);
            $order = $this->customerOrderService->createOrder(
                $request->validated(),
                $lines,
                $request->user()
            );

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم إنشاء طلب العميل بنجاح.');
        } catch (Exception $e) {
            Storage::disk('local')->delete($storedImages);

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
            'lines.material.readyParent',
            'lines.productConfiguration',
            'lines.customerProductAlias',
            'lines.fabricSupplier',
            'lines.fabricMaterial',
            'lines.fabricColor',
            'lines.productionOrders',
            'changes.requestedBy',
            'changes.approvedBy',
        ]);

        $trackingSummary = $this->trackingService->summary($order);
        $timeline = $this->timelineService->forCustomerOrder($order, 50, $request->user()->role?->name);
        $specialFabricColors = $order->lines->contains('special_fabric', true)
            && ($request->user()->can('orders.update') || $request->user()->can('orders.review_production'))
            ? FabricColor::active()->available()->whereHas('material', fn ($query) => $query->active()->whereHas('category', fn ($category) => $category->where('code', 'FABRIC')))
                ->with('material.fabricSpec.supplier')->orderBy('material_id')->orderBy('color_code')->get()
            : collect();

        return view('sales.orders.show', compact('order', 'trackingSummary', 'timeline', 'specialFabricColors'));
    }

    public function edit(Request $request, CustomerOrder $order): View
    {
        abort_if(! $request->user()->can('orders.update'), 403, 'غير مصرح لك بتعديل هذا الطلب.');

        $order->load([
            'lines.productModel',
            'lines.material.readyParent',
            'lines.productConfiguration',
            'lines.fabricSupplier',
            'lines.fabricMaterial',
        ]);
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();
        $productModels = ProductModel::where('is_active', true)->with('configurations', 'aliases')->get();
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors', 'suppliers')->get();
        $suppliers = Supplier::where('is_active', true)->with('materials')->orderBy('name')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.orders.edit', compact('order', 'customers', 'salesChannels', 'productModels', 'fabrics', 'suppliers', 'colors', 'aliases'));
    }

    public function update(CustomerOrderRequest $request, CustomerOrder $order): RedirectResponse
    {
        $storedImages = [];
        try {
            $lines = $this->storeDesignImages($request->validated('lines'), $storedImages);
            $this->customerOrderService->updateOrder(
                $order,
                $request->validated(),
                $lines,
                $request->user()
            );

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم تحديث طلب العميل وسجل التغييرات بنجاح.');
        } catch (Exception $e) {
            Storage::disk('local')->delete($storedImages);

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function designImage(Request $request, CustomerOrder $order, CustomerOrderLine $line): BinaryFileResponse
    {
        abort_unless($request->user()->can('orders.view'), 403);
        abort_unless($line->customer_order_id === $order->id && $line->custom_design && $line->custom_design_image_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($line->custom_design_image_path), 404);

        return response()->file($disk->path($line->custom_design_image_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, string>  $storedImages
     * @return array<int, array<string, mixed>>
     */
    protected function storeDesignImages(array $lines, array &$storedImages): array
    {
        foreach ($lines as &$line) {
            $image = $line['custom_design_image'] ?? null;
            if ($image instanceof UploadedFile) {
                $path = $image->store('customer-order-designs', 'local');
                if ($path === false) {
                    throw new Exception('تعذر حفظ صورة التصميم. يرجى إعادة المحاولة.');
                }
                $storedImages[] = $path;
                $line['custom_design_image_path'] = $path;
            }
            unset($line['custom_design_image']);
        }

        return $lines;
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
            $this->customerOrderService->cancelOrder($order, $request->user(), $request->string('reason')->trim()->value() ?: null);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم إلغاء الطلب بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
