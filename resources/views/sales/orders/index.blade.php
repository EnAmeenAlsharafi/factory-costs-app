@extends('layouts.app')

@section('title', 'طلبات العملاء')

@section('page-title', 'طلبات العملاء')

@section('content')
@php
    $canSeeAmounts = auth()->user()->can('orders.create') || auth()->user()->can('orders.review_production') || auth()->user()->can('receivables.view');
    $activeFilterCount = collect(['customer_id', 'sales_channel_id', 'status'])->filter(fn ($key) => request()->filled($key))->count();
    $statusDomain = [
        'DRAFT' => ['مسودة', 'secondary', 'fa-file-pen'],
        'PENDING_PRODUCTION_REVIEW' => ['بانتظار مراجعة الإنتاج', 'warning', 'fa-hourglass-half'],
        'APPROVED_FOR_PRODUCTION' => ['معتمد للإنتاج', 'success', 'fa-check-double'],
        'CANCELLED' => ['ملغى', 'danger', 'fa-ban'],
    ];
@endphp
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="min-w-0">
            <h1 id="page-heading" class="h3 mb-1 text-dark fw-bold">طلبات العملاء</h1>
            <p class="text-muted mb-0 fs-7 d-none d-md-block">إدارة طلبات البيع التجارية، المراجعة الفنية، والاعتماد النهائي للإنتاج</p>
        </div>
        @can('orders.create')
            <a href="{{ route('sales.orders.create') }}" class="btn btn-warning px-3 fw-semibold">
                <i class="fas fa-plus" aria-hidden="true"></i> طلب جديد
            </a>
        @endcan
    </div>

    {{-- Flash messages are rendered once by the layout (partials.flash). --}}

    <x-filter-sheet id="orders-filters" :action="route('sales.orders.index')" :active-count="$activeFilterCount" :reset-url="route('sales.orders.index', request()->only('search'))">
        <x-slot:search>
            <input type="search" name="search" class="form-control" inputmode="search" enterkeyhint="search" autocomplete="off"
                   placeholder="ابحث برقم الطلب أو اسم العميل أو أمر الشراء" aria-label="بحث في طلبات العملاء" value="{{ request('search') }}">
        </x-slot:search>
        <div class="col-12 col-md-4">
            <label for="orders-customer" class="form-label fs-7">العميل</label>
            <select name="customer_id" id="orders-customer" class="form-select">
                <option value="">جميع العملاء</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="orders-channel" class="form-label fs-7">قناة البيع</label>
            <select name="sales_channel_id" id="orders-channel" class="form-select">
                <option value="">جميع القنوات</option>
                @foreach($salesChannels as $channel)
                    <option value="{{ $channel->id }}" @selected(request('sales_channel_id') == $channel->id)>{{ $channel->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="orders-status" class="form-label fs-7">الحالة</label>
            <select name="status" id="orders-status" class="form-select">
                <option value="">جميع الحالات</option>
                @foreach ($statusDomain as $statusKey => $statusMeta)
                    <option value="{{ $statusKey }}" @selected(request('status') === $statusKey)>{{ $statusMeta[0] }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-sheet>

    @if ($activeFilterCount > 0 || request()->filled('search'))
        <div class="d-flex flex-wrap gap-2 mb-3 fs-7" aria-label="الفلاتر النشطة">
            @if (request()->filled('search'))<span class="status-chip status-chip-secondary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> {{ request('search') }}</span>@endif
            @if (request()->filled('status'))<span class="status-chip status-chip-secondary">{{ $statusDomain[request('status')][0] ?? request('status') }}</span>@endif
            @if (request()->filled('customer_id'))<span class="status-chip status-chip-secondary">{{ $customers->firstWhere('id', (int) request('customer_id'))?->name }}</span>@endif
            @if (request()->filled('sales_channel_id'))<span class="status-chip status-chip-secondary">{{ $salesChannels->firstWhere('id', (int) request('sales_channel_id'))?->name_ar }}</span>@endif
            <a href="{{ route('sales.orders.index') }}" class="link-secondary align-self-center">مسح الفلاتر</a>
        </div>
    @endif

    {{-- Mobile: order cards --}}
    <div class="task-list mobile-cards-only-lg mb-3">
        @forelse($orders as $order)
            @php
                $statusMeta = $statusDomain[$order->status] ?? [$order->status, 'secondary', 'fa-circle-info'];
            @endphp
            <article class="task-card tone-{{ $statusMeta[1] }}">
                <div class="task-card-head">
                    <div class="min-w-0">
                        <h2 class="task-card-title"><a href="{{ route('sales.orders.show', $order) }}" class="text-reset text-decoration-none">{{ $order->customer?->name }}</a></h2>
                        <div class="task-card-ref"><span class="ltr-isolate">{{ $order->order_number }}</span> &bull; {{ $order->salesChannel?->name_ar }}</div>
                    </div>
                    <span class="status-chip status-chip-{{ $statusMeta[1] }}"><i class="fas {{ $statusMeta[2] }}" aria-hidden="true"></i> {{ $statusMeta[0] }}</span>
                </div>
                <div class="task-card-meta">
                    <span><i class="far fa-calendar" aria-hidden="true"></i><span class="ltr-isolate">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</span></span>
                    @if ($canSeeAmounts)
                        <span><i class="fas fa-coins" aria-hidden="true"></i>{{ number_format($order->total_amount, 2) }} ر.س</span>
                    @endif
                </div>
                <div class="task-card-actions">
                    <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-outline-primary">عرض الطلب</a>
                    @if($order->status === 'PENDING_PRODUCTION_REVIEW')
                        @can('orders.review_production')
                            <a href="{{ route('sales.orders.review', $order) }}" class="btn btn-warning">مراجعة</a>
                        @endcan
                    @endif
                </div>
            </article>
        @empty
            <div class="card-factory empty-state" role="status">
                <i class="fas fa-shopping-bag fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
                <p class="mb-0">لا توجد طلبات مطابقة للبحث أو الفلاتر الحالية. جرّب مسح الفلاتر أو أنشئ طلباً جديداً.</p>
            </div>
        @endforelse
        @if($orders->hasPages())
            <div class="d-flex justify-content-center">{{ $orders->links() }}</div>
        @endif
    </div>

    {{-- Tablet/desktop: orders table --}}
    <div class="card border-0 shadow-sm rounded-3 has-mobile-cards-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">رقم الطلب</th>
                            <th>العميل / أمر شراء العميل (PO)</th>
                            <th>قناة البيع</th>
                            <th>تاريخ الطلب / التسليم</th>
                            <th>إجمالي المبلغ</th>
                            <th>حالة الطلب</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">
                                    <a href="{{ route('sales.orders.show', $order) }}" class="text-decoration-none fw-bold">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $order->customer?->name }}</div>
                                    @if($order->customer_po_number)
                                        <small class="text-muted"><i class="fas fa-hashtag me-1"></i> PO: {{ $order->customer_po_number }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $order->salesChannel?->name_ar }}</span>
                                </td>
                                <td>
                                    <div class="fs-7 text-dark">{{ $order->order_date ? $order->order_date->format('Y-m-d') : '-' }}</div>
                                    @if($order->promised_delivery_date)
                                        <small class="text-muted">التسليم: {{ $order->promised_delivery_date->format('Y-m-d') }}</small>
                                    @endif
                                </td>
                                <td class="fw-bold text-dark">
                                    @if ($canSeeAmounts)
                                        {{ number_format($order->total_amount, 2) }} ر.س
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($order->status === 'DRAFT')
                                        <span class="badge bg-secondary">مسودة</span>
                                    @elseif($order->status === 'PENDING_PRODUCTION_REVIEW')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> بانتظار مراجعة الإنتاج</span>
                                    @elseif($order->status === 'APPROVED_FOR_PRODUCTION')
                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> معتمد للإنتاج</span>
                                    @elseif($order->status === 'CANCELLED')
                                        <span class="badge bg-danger"><i class="fas fa-ban me-1"></i> ملغى</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($order->status === 'PENDING_PRODUCTION_REVIEW')
                                            @can('orders.review_production')
                                                <a href="{{ route('sales.orders.review', $order) }}" class="btn btn-sm btn-warning fw-semibold" title="مراجعة فنية واتماد">
                                                    <i class="fas fa-clipboard-check me-1"></i> مراجعة
                                                </a>
                                            @endcan
                                        @endif

                                        @if(in_array($order->status, ['DRAFT', 'PENDING_PRODUCTION_REVIEW', 'APPROVED_FOR_PRODUCTION']))
                                            @can('orders.edit')
                                                <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-outline-secondary" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-shopping-bag fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد طلبات مطابقة للبحث أو الفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
