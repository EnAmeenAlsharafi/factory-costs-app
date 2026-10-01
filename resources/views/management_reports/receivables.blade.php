@extends('layouts.app')

@section('title', 'الذمم والأعمار والتحصيل')
@section('page-title', 'الذمم والتحصيل')

@php use App\Domain\Reports\ReportFormat as F; @endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="$tab === 'collections' ? 'نشاط التحصيل' : 'الذمم وأعمار الديون'" icon="fa-file-invoice" :period="$tab === 'collections' ? $period : null"
        :basis="$tab === 'collections' ? 'تاريخ الدفعة' : 'الوضع الحالي'" :exportable="$tab === 'collections'"
        subtitle="الأرصدة والأعمار والائتمان من حسابات المرحلة 13 المعتمدة. لا توجد مخصصات محاسبية. نشاط التحصيل ليس قائمة تدفقات نقدية." />

    <nav class="segment-tabs report-no-print" aria-label="أقسام تقرير الذمم">
        <a href="{{ route('reports.receivables') }}" class="segment-tab {{ $tab === 'balances' ? 'active' : '' }}">الذمم والأعمار</a>
        <a href="{{ route('reports.receivables', ['tab' => 'collections']) }}" class="segment-tab {{ $tab === 'collections' ? 'active' : '' }}">نشاط التحصيل</a>
    </nav>

    @if ($tab === 'balances')
        <section class="card-factory p-3" aria-labelledby="aging">
            <h2 id="aging" class="h6 fw-bold">أعمار الديون (حسب أيام التأخر عن تاريخ الاستحقاق)</h2>
            @php $agingTotal = max(0.01, (float) $aging->sum('amount')); @endphp
            <div class="report-stack mb-3" aria-hidden="true">
                @foreach ($aging as $key => $bucket)
                    @if ($bucket['amount'] > 0)<span style="width: {{ round($bucket['amount'] / $agingTotal * 100, 1) }}%; background: {{ $key === 'current' ? '#64748b' : ($key === 'over_90' ? '#b91c1c' : '#b7791f') }}"></span>@endif
                @endforeach
            </div>
            <div class="report-kpis">
                @foreach ($aging as $key => $bucket)
                    <x-report.kpi :label="$bucket['label'].' — '.$bucket['count'].' طلب'" :value="F::money($bucket['amount'])" icon="fa-clock" :tone="$key === 'current' ? 'success' : ($key === 'over_90' ? 'danger' : 'warning')" :alert="$key !== 'current' && $bucket['amount'] > 0" />
                @endforeach
                <x-report.kpi label="الإجمالي المستحق" :value="F::money($aging->sum('amount'))" icon="fa-coins" tone="primary" />
            </div>
        </section>
        <x-report.period-filter :period="null" :showPeriod="false">
            <div class="col-12 col-md-4">
                <label for="f-search" class="form-label fs-7">بحث عن عميل</label>
                <input type="search" id="f-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="الاسم أو الكود أو الجوال" inputmode="search">
            </div>
            <div class="col-6 col-md-3">
                <label for="f-credit" class="form-label fs-7">نوع العميل</label>
                <select id="f-credit" name="is_credit_customer" class="form-select">
                    <option value="">الكل</option>
                    <option value="1" @selected(request('is_credit_customer') === '1')>آجل (ائتمان)</option>
                    <option value="0" @selected(request('is_credit_customer') === '0')>نقدي</option>
                </select>
            </div>
        </x-report.period-filter>
    @else
        <x-report.period-filter :period="$period" :keep="['tab' => 'collections']">
            <div class="col-6 col-lg-2">
                <label for="f-status" class="form-label fs-7">الحالة</label>
                <select id="f-status" name="status" class="form-select">
                    <option value="">الكل</option>
                    <option value="CONFIRMED" @selected(request('status') === 'CONFIRMED')>مؤكدة</option>
                    <option value="PENDING_CONFIRMATION" @selected(request('status') === 'PENDING_CONFIRMATION')>بانتظار التأكيد</option>
                    <option value="REVERSED" @selected(request('status') === 'REVERSED')>معكوسة</option>
                </select>
            </div>
        </x-report.period-filter>
        <div class="report-kpis">
            <x-report.kpi label="دفعات مؤكدة" :value="F::money($totals['confirmed'])" icon="fa-circle-check" tone="success" :hint="$totals['confirmed_count'].' دفعة'" />
            <x-report.kpi label="بانتظار التأكيد" :value="F::money($totals['pending'])" icon="fa-hourglass-half" tone="warning" :hint="$totals['pending_count'].' دفعة'" />
            @foreach ($totals['by_method'] as $method)
                <x-report.kpi :label="'مؤكد — '.$method->payment_method" :value="F::money($method->amount)" icon="fa-money-bill" tone="secondary" :hint="$method->n.' دفعة'" />
            @endforeach
        </div>
    @endif

    <x-report.table :table="$table" caption="الذمم" :primary="$tab === 'balances' ? ['customer', 'outstanding', 'overdue', 'unallocated', 'credit_status'] : []" :mobile-cards="$tab === 'balances'" />
</div>
@endsection
