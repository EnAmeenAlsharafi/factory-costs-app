<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryOrderFormRequest;
use App\Models\CustomerOrder;
use App\Models\DeliveryOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryOrderController extends Controller
{
    public function __construct(
        protected DeliveryOrderService $deliveryService,
        protected FinishedGoodsService $finishedGoodsService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $query = DeliveryOrder::with([
            'customerOrder.customer',
            'assignedUser',
            'createdByUser',
            'lines.productionOrder',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('delivery_number', 'like', "%{$search}%")
                    ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('customer_phone_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', fn ($co) => $co->where('order_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }

        $deliveryOrders = $query->latest()->paginate(15)->withQueryString();

        $deliveryRole = Role::where('name', 'delivery_user')->first();
        $drivers = $deliveryRole
            ? User::where('role_id', $deliveryRole->id)->where('is_active', true)->get()
            : User::where('is_active', true)->get();

        return view('delivery.orders.index', compact('deliveryOrders', 'drivers'));
    }

    public function myTasks(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $user = $request->user();
        $query = DeliveryOrder::with([
            'customerOrder.customer',
            'lines.productionOrder.customerOrderLine.productModel',
            'events',
        ]);

        if (! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
            $query->where('assigned_user_id', $user->id);
        }

        $myDeliveries = $query->whereIn('status', ['ASSIGNED', 'READY_FOR_DELIVERY', 'OUT_FOR_DELIVERY', 'DELIVERED'])
            ->orderBy('scheduled_delivery_date', 'asc')
            ->get();

        return view('delivery.orders.my_tasks', compact('myDeliveries'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.create'), 403);

        $orderId = $request->query('customer_order_id');
        $customerOrder = CustomerOrder::with('customer', 'lines.productionOrders')->findOrFail($orderId);

        $deliveryRole = Role::where('name', 'delivery_user')->first();
        $drivers = $deliveryRole
            ? User::where('role_id', $deliveryRole->id)->where('is_active', true)->get()
            : User::where('is_active', true)->get();

        // Calculate available ready quantities for each line's production order
        $lineAvailabilities = [];
        foreach ($customerOrder->lines as $orderLine) {
            foreach ($orderLine->productionOrders as $po) {
                $lineAvailabilities[$po->id] = $this->finishedGoodsService->getAvailableQuantity($po);
            }
        }

        return view('delivery.orders.create', compact('customerOrder', 'drivers', 'lineAvailabilities'));
    }

    public function store(DeliveryOrderFormRequest $request): RedirectResponse
    {
        $customerOrder = CustomerOrder::findOrFail($request->customer_order_id);

        try {
            $delivery = $this->deliveryService->createDeliveryOrder($customerOrder, $request->user(), $request->validated());

            if ($request->input('action') === 'ready') {
                $this->deliveryService->markReady($delivery, $request->user());
            }

            return redirect()->route('delivery.orders.show', $delivery)
                ->with('success', "تم إنشاء أمر التوصيل رقم {$delivery->delivery_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(DeliveryOrder $delivery, Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $delivery->load([
            'customerOrder.customer',
            'assignedUser',
            'createdByUser',
            'dispatchedByUser',
            'lines.productionOrder.customerOrderLine.productModel',
            'events.user',
            'returns',
        ]);

        $deliveryRole = Role::where('name', 'delivery_user')->first();
        $drivers = $deliveryRole
            ? User::where('role_id', $deliveryRole->id)->where('is_active', true)->get()
            : User::where('is_active', true)->get();

        return view('delivery.orders.show', compact('delivery', 'drivers'));
    }

    public function assign(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.assign'), 403);

        $request->validate([
            'assigned_user_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $driver = User::findOrFail($request->assigned_user_id);
            $this->deliveryService->assignDriver($delivery, $driver, $request->user());

            return back()->with('success', "تم تعيين المسؤول ({$driver->name}) بنجاح.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function dispatch(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.dispatch'), 403);

        try {
            $this->deliveryService->dispatchDelivery($delivery, $request->user());

            return back()->with('success', 'تم اعتماد خروج الشحنة مع مسار التوصيل للعميل بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.complete'), 403);

        try {
            $this->deliveryService->markDelivered($delivery, $request->user(), $request->input('notes'));

            return back()->with('success', 'تم تأكيد استلام العميل للشحنة بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function install(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.install'), 403);

        try {
            $this->deliveryService->markInstalled($delivery, $request->user(), $request->input('notes'));

            return back()->with('success', 'تم تأكيد اكتمال التركيب والتشغيل النهائي للطلب بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function failOrReschedule(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.reschedule'), 403);

        $request->validate([
            'new_status' => ['required', 'in:FAILED,RESCHEDULED,CANCELLED'],
            'reason' => ['required', 'string', 'max:1000'],
            'scheduled_delivery_date' => ['nullable', 'date'],
            'returned_to_factory' => ['nullable', 'boolean'],
        ]);

        try {
            $this->deliveryService->failOrReschedule(
                $delivery,
                $request->user(),
                $request->new_status,
                $request->reason,
                $request->scheduled_delivery_date,
                $request->boolean('returned_to_factory', true)
            );

            return back()->with('success', 'تم تحديث حالة التوصيل وحفظ السجل الفني والزمني بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
