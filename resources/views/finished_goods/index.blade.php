@extends('layouts.app')

@section('title', 'المنتجات الجاهزة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="page-header-card d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-boxes text-warning fs-5" aria-hidden="true"></i>مخزون المنتجات الجاهزة</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">تتبع استلامات الإنتاج المكتمل، الجاهزية للتوصيل، وصافي أرصدة المنتجات التامة بالمستودع</p>
        </div>
        <a href="{{ route('finished-goods.quarantine') }}" class="btn btn-outline-warning">
            <i class="fas fa-shield-halved" aria-hidden="true"></i> منطقة فحص المرتجعات
            @if ($quarantineCount > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $quarantineCount }}</span>
            @endif
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('finished-goods.index') }}" method="GET" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-select border-start-0" placeholder="بحث برقم أمر الإنتاج، رقم طلب العميل، أو اسم العميل..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('finished-goods.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط الفلاتر" aria-label="إعادة ضبط الفلاتر"><i class="fas fa-undo" aria-hidden="true"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Finished Goods Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>طلب العميل</th>
                            <th>العميل</th>
                            <th>المنتج / الموديل</th>
                            <th class="text-center">إنجاز التغليف</th>
                            <th class="text-center">المستلم بالجاهز</th>
                            <th class="text-center">المتاح للتوصيل</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productionOrders as $po)
                            @php
                                $fgService = app(\App\Services\FinishedGoodsService::class);
                                $receivedQty = $fgService->getTotalReceivedQuantity($po);
                                $availableQty = $fgService->getAvailableQuantity($po);
                                $maxEligible = max(0.0, (float)$po->completed_quantity - $receivedQty);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('production.orders.show', $po) }}" class="fw-bold text-primary text-decoration-none">
                                        {{ $po->production_order_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $po->customerOrder->order_number }}</span>
                                </td>
                                <td>{{ $po->customerOrder->customer->name ?? $po->customerOrder->customer->name_ar }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $po->customerOrderLine->productModel?->name_ar ?? 'تخصيص حر' }}</div>
                                    <small class="text-muted">{{ $po->customerOrderLine->requested_width_cm }} × {{ $po->customerOrderLine->requested_length_cm }} سم</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark">{{ number_format($po->completed_quantity, 0) }} / {{ number_format($po->ordered_quantity, 0) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ number_format($receivedQty, 0) }}</span>
                                </td>
                                <td class="text-center">
                                    @if($availableQty > 0)
                                        <span class="badge bg-success fs-7 px-3 py-1">{{ number_format($availableQty, 0) }} قطعة</span>
                                    @else
                                        <span class="text-muted fs-8">غير متاح</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        @can('finished_goods.receive')
                                            @if($maxEligible > 0)
                                                <a href="{{ route('finished-goods.receipts.create', ['production_order_id' => $po->id]) }}" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-hand-holding-box me-1"></i> تسليم للمستودع
                                                </a>
                                            @endif
                                        @endcan
                                        @can('delivery.create')
                                            @if($availableQty > 0)
                                                <a href="{{ route('delivery.orders.create', ['customer_order_id' => $po->customer_order_id]) }}" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-truck-fast me-1"></i> أمر توصيل
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">لا توجد أوامر إنتاج مكتملة جاهزة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($productionOrders->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $productionOrders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
