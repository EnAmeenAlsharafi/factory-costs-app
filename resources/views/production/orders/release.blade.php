@extends('layouts.app')

@section('title', 'إطلاق أمر إنتاج للورشة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="page-header-card d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-play-circle text-warning fs-5" aria-hidden="true"></i>إطلاق أمر الإنتاج للورشة</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">أمر إنتاج رقم: <bdi class="fw-bold text-primary">{{ $order->production_order_number }}</bdi></p>
        </div>
        <a href="{{ route('production.orders.show', $order) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة لأمر الإنتاج
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <form action="{{ route('production.orders.release', $order) }}" method="POST">
                @csrf

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-route me-1"></i> اختيار مسار التصنيع لتوليد العمليات</h6>
                    </div>
                    <div class="card-body">
                        @if (! $order->is_custom_design && ! $order->manufacturing_recipe_version_id)
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-ban me-1" aria-hidden="true"></i>
                                لا يمكن إطلاق هذا الأمر: المنتج غير مرتبط بوصفة تصنيع معتمدة. اربط الوصفة من المراجعة الفنية لطلب العميل أولاً.
                            </div>
                        @endif

                        <fieldset class="mb-3">
                            <legend class="form-label required fs-6 fw-semibold mb-2">اختر مسار التصنيع — ستُنشأ مهمة لكل مرحلة في قسمها</legend>
                            @forelse ($routings as $rtg)
                                <label class="routing-option">
                                    <input type="radio" name="production_routing_id" value="{{ $rtg->id }}" class="form-check-input" required @checked((int) old('production_routing_id', $routings->count() === 1 ? $rtg->id : 0) === $rtg->id)>
                                    <span class="routing-option-body">
                                        <span class="d-flex flex-wrap justify-content-between gap-2">
                                            <span class="fw-bold text-dark">{{ $rtg->name_ar }}</span>
                                            <span class="text-muted fs-7"><bdi>{{ $rtg->routing_code }}</bdi> · {{ $rtg->operations->count() }} مراحل</span>
                                        </span>
                                        <span class="routing-option-ops">
                                            @foreach ($rtg->operations as $operation)
                                                <span class="routing-op"><span class="routing-op-seq">{{ $loop->iteration }}</span>{{ $operation->name_ar }}</span>
                                            @endforeach
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <div class="alert alert-warning mb-0">لا توجد مسارات تصنيع مفعّلة. أنشئ مساراً من «مسارات التصنيع» أولاً.</div>
                            @endforelse
                        </fieldset>

                        <p class="text-muted fs-7 mb-0"><i class="fas fa-circle-info me-1" aria-hidden="true"></i> بعد الإطلاق تظهر المراحل الأولى «جاهزة» في أقسامها، وتُفتح كل مرحلة تالية تلقائياً عند اكتمال ما قبلها.</p>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('production.orders.show', $order) }}" class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-success btn-lg px-4" @disabled(! $order->is_custom_design && ! $order->manufacturing_recipe_version_id)><i class="fas fa-rocket me-1" aria-hidden="true"></i> إطلاق للورشة الآن</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark">ملخص أمر الإنتاج</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">طلب العميل:</td>
                            <td class="fw-bold font-monospace">{{ $order->customerOrder?->order_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">العميل:</td>
                            <td class="fw-bold">{{ $order->customerOrder?->customer?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المنتج:</td>
                            <td class="fw-bold">{{ $order->display_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الأبعاد:</td>
                            <td>{{ (int)$order->requested_width_cm }} × {{ (int)$order->requested_length_cm }} سم</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الكمية المطلوبة:</td>
                            <td class="fw-bold text-success fs-5">{{ $order->released_quantity }} قطعة</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
