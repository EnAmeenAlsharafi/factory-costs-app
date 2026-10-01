<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryOrderFormRequest;
use App\Models\CustomerOrder;
use App\Models\DeliveryOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use App\Services\OperationalDashboardService;
use App\Services\OrderPaymentEligibilityService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryOrderController extends Controller
{
    public function __construct(
        protected DeliveryOrderService $deliveryService,
        protected FinishedGoodsService $finishedGoodsService,
        protected OperationalDashboardService $dashboardService
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

    /**
     * "مهامي" filters: key => label.
     *
     * @var array<string, string>
     */
    public const MY_TASK_FILTERS = [
        'all' => 'كل المهام المفتوحة',
        'today' => 'اليوم',
        'assigned' => 'جاهزة للانطلاق',
        'out' => 'خارج للتوصيل',
        'installation' => 'بانتظار التركيب',
        'exceptions' => 'متعذرة / مؤجلة',
    ];

    public function myTasks(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $user = $request->user();
        $filter = array_key_exists((string) $request->query('filter'), self::MY_TASK_FILTERS) ? (string) $request->query('filter') : 'all';

        $filterCounts = [];
        foreach (array_keys(self::MY_TASK_FILTERS) as $filterKey) {
            $filterCounts[$filterKey] = $this->myTasksQuery($user, $filterKey)->count();
        }

        $myDeliveries = $this->myTasksQuery($user, $filter)
            ->with([
                'lines.productionOrder.productModel',
                'lines.productionOrder.fabricMaterial',
                'lines.productionOrder.fabricColor',
                'lines.productionOrder.customerOrderLine.productModel',
            ])
            ->orderByRaw('scheduled_delivery_date IS NULL')
            ->orderBy('scheduled_delivery_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $filters = self::MY_TASK_FILTERS;

        return view('delivery.orders.my_tasks', compact('myDeliveries', 'filter', 'filters', 'filterCounts'));
    }

    private function myTasksQuery(User $user, string $filter): Builder
    {
        $query = $this->dashboardService->deliveriesQueryFor($user);

        return match ($filter) {
            'today' => $query->whereIn('status', OperationalDashboardService::ACTIVE_DELIVERY_STATUSES)->whereDate('scheduled_delivery_date', today()),
            'assigned' => $query->where('status', 'ASSIGNED'),
            'out' => $query->where('status', 'OUT_FOR_DELIVERY'),
            'installation' => $query->where('status', 'DELIVERED')->where('installation_required', true),
            'exceptions' => $query->whereIn('status', ['FAILED', 'RESCHEDULED']),
            default => $query->whereIn('status', OperationalDashboardService::ACTIVE_DELIVERY_STATUSES)
                ->where(fn (Builder $q) => $q->where('status', '!=', 'DELIVERED')->orWhere('installation_required', true)),
        };
    }

    public function markReady(DeliveryOrder $delivery, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.create'), 403);

        try {
            $this->deliveryService->markReady($delivery, $request->user());

            return back()->with('success', 'تم تحويل أمر التوصيل إلى جاهز للتوصيل.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
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
            'lines.productionOrder.productModel',
            'lines.productionOrder.fabricMaterial',
            'lines.productionOrder.fabricColor',
            'events.user',
            'returns',
        ]);

        $drivers = collect();
        if ($request->user()->can('delivery.assign')) {
            $deliveryRole = Role::where('name', 'delivery_user')->first();
            $drivers = $deliveryRole
                ? User::where('role_id', $deliveryRole->id)->where('is_active', true)->get()
                : User::where('is_active', true)->get();
        }

        $paymentGate = $this->paymentGateFor($delivery, $request->user());

        return view('delivery.orders.show', compact('delivery', 'drivers', 'paymentGate'));
    }

    /**
     * Delivery payment gate as shown to the current user.
     * Field staff see only whether delivery is allowed and, for COD, the amount to collect;
     * the commercial reason (balances, credit) is limited to receivables-authorised users.
     *
     * @return array{eligible: bool, headline: string, detail: ?string, collect_amount: ?float}|null
     */
    private function paymentGateFor(DeliveryOrder $delivery, User $user): ?array
    {
        if (! $delivery->customerOrder || in_array($delivery->status, ['INSTALLATION_COMPLETED', 'CANCELLED'], true)) {
            return null;
        }

        $eligibility = app(OrderPaymentEligibilityService::class)->checkDeliveryEligibility($delivery->customerOrder);
        $isCashOnDelivery = ($eligibility['status'] ?? null) === 'ALLOWED_COD';

        return [
            'eligible' => (bool) $eligibility['eligible'],
            'headline' => $eligibility['eligible']
                ? ($isCashOnDelivery ? 'مسموح بالتسليم مع تحصيل المبلغ عند الاستلام' : 'مسموح بالتسليم')
                : 'التسليم موقوف — راجع قسم التحصيل قبل الانطلاق',
            'detail' => $user->can('receivables.view') && ! $isCashOnDelivery ? $eligibility['reason'] : null,
            'collect_amount' => $isCashOnDelivery ? (float) $eligibility['outstanding'] : null,
        ];
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
