@extends('layouts.app')

@section('title', 'ربحية الطلبات التشغيلية')
@section('page-title', 'ربحية الطلبات')

@php
    $money = fn ($value) => $value === null ? null : \App\Domain\Reports\ReportFormat::money($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="ربحية الطلبات التشغيلية" icon="fa-file-invoice-dollar" :period="$period" basis="تاريخ طلب العميل" exportable
        subtitle="القيمة التجارية التاريخية للطلب مقابل تكلفة المواد الفعلية. الطلبات غير المكتملة تُعرض بتكلفتها حتى تاريخه دون مساهمة نهائية."
        :filters="['الفترة' => $period->label()]" />

    <x-report.period-filter :period="$period" :keep="['sort' => request('sort')]">
        <div class="col-12 col-sm-6 col-lg-2">
            <label for="f-customer" class="form-label fs-7">العميل</label>
            <select id="f-customer" name="customer_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-channel" class="form-label fs-7">قناة البيع</label>
            <select id="f-channel" name="sales_channel_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}" @selected(request('sales_channel_id') == $channel->id)>{{ $channel->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-type" class="form-label fs-7">نوع العميل</label>
            <select id="f-type" name="customer_type_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($customerTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('customer_type_id') == $type->id)>{{ $type->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-status" class="form-label fs-7">حالة التكلفة</label>
            <select id="f-status" name="status" class="form-select">
                <option value="">الكل</option>
                @foreach ($statuses as $key => $status)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $status['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-sort" class="form-label fs-7">الترتيب</label>
            <select id="f-sort" name="sort" class="form-select">
                <option value="date" @selected(request('sort', 'date') === 'date')>الأحدث</option>
                <option value="value" @selected(request('sort') === 'value')>الأعلى قيمة</option>
                <option value="cost" @selected(request('sort') === 'cost')>الأعلى تكلفة</option>
                <option value="contribution" @selected(request('sort') === 'contribution')>الأعلى مساهمة</option>
                <option value="contribution_asc" @selected(request('sort') === 'contribution_asc')>الأقل مساهمة (أولاً السالبة)</option>
            </select>
        </div>
        <div class="col-12 col-lg-auto">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="include_cancelled" value="1" id="f-cancelled" @checked(request('include_cancelled'))>
                <label class="form-check-label fs-7" for="f-cancelled">تضمين الملغاة</label>
            </div>
        </div>
    </x-report.period-filter>

    <div class="report-kpis">
        <x-report.kpi label="القيمة التجارية ({{ number_format($totals['order_count']) }} طلب)" :value="$money($totals['commercial_value'])" icon="fa-coins" tone="primary" />
        <x-report.kpi label="تكلفة المواد الفعلية حتى تاريخه" :value="$money($totals['actual_cost'])" icon="fa-boxes-packing" tone="secondary" />
        <x-report.kpi label="المساهمة التشغيلية (مكتملة)" :value="$money($totals['contribution'])" icon="fa-scale-balanced" tone="success"
            :hint="$totals['final_count'].' طلب مكتمل التكلفة من '.$totals['order_count']" :alert="($totals['contribution'] ?? 0) < 0" />
        <x-report.kpi label="هامش المساهمة (مكتملة)" :value="$totals['contribution_pct'] !== null ? \App\Domain\Reports\ReportFormat::percent($totals['contribution_pct']) : null" icon="fa-percent" tone="success" :alert="($totals['contribution_pct'] ?? 0) < 0" />
        <x-report.kpi label="طلبات بتكلفة غير مكتملة" :value="number_format($totals['provisional_count'])" icon="fa-hourglass-half" tone="warning" :hint="'لم تبدأ تكلفتها: '.$totals['not_costed_count']" />
        @if ($totals['missing_cost_count'] > 0)
            <x-report.kpi label="إنتاج مكتمل بلا تكلفة مواد مسجلة" :value="number_format($totals['missing_cost_count'])" icon="fa-circle-exclamation" tone="danger" alert
                hint="بيانات ناقصة — مستبعدة من المساهمة" :href="request()->fullUrlWithQuery(['status' => 'MISSING_COST', 'page' => null])" />
        @endif
    </div>

    <section class="card-factory p-3" aria-label="اكتمال تكلفة الطلبات">
        <h2 class="report-section-title">اكتمال التكلفة</h2>
        <div class="report-legend">
            <span class="status-chip status-chip-success">نهائية تشغيلياً: {{ number_format($totals['final_count']) }}</span>
            <span class="status-chip status-chip-warning">مؤقتة: {{ number_format($totals['provisional_count']) }}</span>
            <span class="status-chip status-chip-secondary">قيد الإنتاج / لم يبدأ: {{ number_format($totals['not_costed_count']) }}</span>
            @if ($totals['missing_cost_count'] > 0)<span class="status-chip status-chip-danger">تكلفة مفقودة: {{ number_format($totals['missing_cost_count']) }}</span>@endif
        </div>
        <p class="report-note mt-2 mb-0">الحالة التفصيلية لكل طلب تميّز المؤقت وقيد الإنتاج. المساهمة والهامش للطلبات مكتملة التكلفة فقط.</p>
    </section>
    <x-report.table :table="$table" caption="ربحية الطلبات التشغيلية" :primary="['order', 'customer', 'commercial_value', 'actual_cost', 'contribution', 'contribution_pct', 'profitability_status']" mobile-cards />
    <x-report.boundary />
</div>
@endsection
