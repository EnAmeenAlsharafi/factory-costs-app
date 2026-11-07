@extends('layouts.app')

@section('title', 'منطقة فحص المرتجعات - مصنع مفروشات سدير')
@section('page-title', 'منطقة فحص المرتجعات')

@section('content')
<div class="d-flex flex-column gap-4">
    @include('partials.page-header', [
        'title' => 'منطقة فحص المرتجعات',
        'subtitle' => 'منتجات رُفضت عند التوصيل أو أرجعها العملاء. لا تُحسب ضمن المخزون المتاح للتوصيل حتى يُقرر الفحص أنها سليمة.',
        'icon' => 'fas fa-shield-halved',
        'breadcrumbs' => [
            ['title' => 'المنتجات الجاهزة', 'url' => route('finished-goods.index')],
            ['title' => 'منطقة الفحص'],
        ],
    ])

    @if ($productionOrders->isEmpty())
        <div class="empty-state card">
            <i class="fas fa-circle-check fs-2 mb-3 d-block text-success" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold">لا توجد منتجات بانتظار الفحص.</p>
        </div>
    @else
        <div class="table-factory-wrapper">
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead>
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>العميل والمنتج</th>
                            <th>الكمية في الفحص</th>
                            <th>المصدر</th>
                            <th>القرار</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productionOrders as $po)
                            @php
                                $quantity = (float) $balances[$po->id];
                                $returns = $openReturns->get($po->id, collect());
                                $fromReturns = (float) $returns->sum('quantity');
                                $freeQuantity = max(0, $quantity - $fromReturns);
                            @endphp
                            <tr>
                                <td class="fw-bold ltr-isolate">{{ $po->production_order_number }}</td>
                                <td>
                                    <div>{{ $po->customerOrder?->customer?->name }}</div>
                                    <div class="fs-8 text-muted">{{ $po->productModel?->name_ar ?? $po->custom_design_name }}</div>
                                </td>
                                <td><span class="status-chip status-chip-warning">{{ rtrim(rtrim(number_format($quantity, 2), '0'), '.') }} قطعة</span></td>
                                <td>
                                    @foreach ($returns as $customerReturn)
                                        <a href="{{ route('customer-returns.show', $customerReturn) }}" class="d-block fs-7 ltr-isolate">{{ $customerReturn->return_number }}</a>
                                    @endforeach
                                    @if ($freeQuantity > 0)
                                        <span class="d-block fs-7">رفض عند التوصيل ({{ rtrim(rtrim(number_format($freeQuantity, 2), '0'), '.') }})</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($freeQuantity > 0)
                                        @can('finished_goods.receive')
                                            <form method="POST" action="{{ route('finished-goods.quarantine.release', $po) }}" class="d-flex flex-wrap gap-2 align-items-end">
                                                @csrf
                                                <div>
                                                    <label for="q-qty-{{ $po->id }}" class="form-label fs-8 mb-1">الكمية</label>
                                                    <input type="number" id="q-qty-{{ $po->id }}" name="quantity" class="form-control form-control-sm" style="width: 6rem"
                                                           min="1" step="1" max="{{ $freeQuantity }}" value="{{ $freeQuantity }}" required>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label for="q-notes-{{ $po->id }}" class="form-label fs-8 mb-1">نتيجة الفحص</label>
                                                    <input type="text" id="q-notes-{{ $po->id }}" name="notes" class="form-control form-control-sm" required maxlength="500" placeholder="مثال: لا يوجد عيب، أُعيد تغليفه">
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-success">سليم: للمخزون</button>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" formaction="{{ route('finished-goods.quarantine.scrap', $po) }}" data-confirm="تسجيل إتلاف هذه الكمية؟">إتلاف</button>
                                            </form>
                                        @endcan
                                    @else
                                        <span class="fs-7 text-muted">القرار من صفحة المرتجع</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
