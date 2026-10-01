@extends('layouts.app')

@section('title', 'لوحة الإدارة')
@section('page-title', 'لوحة الإدارة')

@php
    $money = fn ($value) => $value === null ? null : \App\Domain\Reports\ReportFormat::money($value);
    $pct = fn ($value) => $value === null ? null : \App\Domain\Reports\ReportFormat::percent($value);
    $num = fn ($value) => $value === null ? null : number_format((float) $value);
    $qty = fn ($value) => $value === null ? null : \App\Domain\Reports\ReportFormat::quantity($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-4">
    <x-report.header title="لوحة الإدارة" icon="fa-chart-line" :period="$period"
        subtitle="ملخص تنفيذي من السجلات التشغيلية. تظهر الأقسام حسب صلاحياتك." :filters="['الفترة' => $period->label()]" />

    <x-report.period-filter :period="$period" />

    <section class="card-factory p-3" aria-labelledby="management-attention">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><h2 id="management-attention" class="report-section-title mb-1">ما الذي يحتاج انتباه الإدارة؟</h2><p class="report-note mb-0">{{ $exceptionCount > 0 ? 'توجد عوائق تشغيلية تحتاج المتابعة حسب صلاحياتك.' : 'لا توجد استثناءات تشغيلية مفتوحة ضمن صلاحياتك.' }}</p></div>
            <a href="{{ route('reports.exceptions') }}" class="btn btn-outline-danger"><bdi>{{ number_format($exceptionCount) }}</bdi> استثناءات — مراجعة الإجراءات</a>
        </div>
        <div class="report-executive-links mt-3">
            @if (isset($sections['inventory']) && $sections['inventory']['production_shortages'] > 0)<a href="{{ route('reports.inventory', ['tab' => 'shortages']) }}">نواقص الإنتاج: <bdi>{{ $sections['inventory']['production_shortages'] }}</bdi></a>@endif
            @if (isset($sections['procurement']) && $sections['procurement']['late_po_count'] > 0)<a href="{{ route('reports.procurement', $period->toQuery()) }}">شراء متأخر: <bdi>{{ $sections['procurement']['late_po_count'] }}</bdi></a>@endif
            @if (isset($sections['production']) && $sections['production']['open_incidents'] > 0)<a href="{{ route('reports.production.quality', ['tab' => 'quality']) }}">جودة مفتوحة: <bdi>{{ $sections['production']['open_incidents'] }}</bdi></a>@endif
            @if (isset($sections['fulfillment']) && $sections['fulfillment']['failed_rescheduled'] > 0)<a href="{{ route('reports.fulfillment', $period->toQuery() + ['tab' => 'delivery']) }}">تعذر / إعادة جدولة بالفترة: <bdi>{{ $sections['fulfillment']['failed_rescheduled'] }}</bdi></a>@endif
        </div>
    </section>
    @isset($sections['commercial'])
        @php $c = $sections['commercial']['current']; @endphp
        <section aria-labelledby="dash-commercial">
            <h2 id="dash-commercial" class="report-section-title"><i class="fas fa-coins" aria-hidden="true"></i> الملخص التنفيذي <span class="report-note fw-normal">(حسب تاريخ الطلب)</span></h2>
            <div class="report-kpis">
                <x-report.kpi label="القيمة التجارية للطلبات" :value="$money($c['commercial_value'])" icon="fa-coins" tone="primary" :href="route('reports.profitability.channels', $period->toQuery())" />
                <x-report.kpi label="عدد الطلبات" :value="$num($c['order_count'])" icon="fa-file-invoice" tone="info" />

                @if ($sections['commercial']['show_contribution'])
                    <x-report.kpi label="تكلفة المواد الفعلية" :value="$money($c['actual_cost'])" hint="صافي المصروف المرحّل حتى تاريخه" />
                    <x-report.kpi label="المساهمة التشغيلية" :value="$money($c['contribution'])" icon="fa-scale-balanced" tone="success"
                        :hint="'للطلبات المكتملة تكلفةً: '.$c['final_count'].' من '.$c['order_count']" :alert="($c['contribution'] ?? 0) < 0"
                        :href="route('reports.profitability.orders', $period->toQuery() + ['status' => 'FINAL_OPERATIONAL'])" />
                    <x-report.kpi label="هامش المساهمة التشغيلي" :value="$pct($c['contribution_pct'])" icon="fa-percent" tone="success" :alert="($c['contribution_pct'] ?? 0) < 0" />
                @endif
                @isset($sections['receivables'])<x-report.kpi label="المبالغ المستحقة" :value="$money($sections['receivables']['outstanding'])" hint="الوضع الحالي — جميع الفترات" :href="route('reports.receivables')" />@endisset
            </div>

            @if (count($trends))
                <div class="card-factory p-3 mt-3">
                    <h3 class="h6 fw-bold mb-2">مقارنة بالفترة السابقة المماثلة <span class="report-note fw-normal">({{ $period->previous()->label() }})</span></h3>
                    <div class="report-comparisons">
                        @foreach ($trends as $trend)<x-report.comparison :trend="$trend" />@endforeach
                    </div>
                </div>
            @endif
            @if ($sections['commercial']['show_contribution'])
                <div class="mt-3"><x-report.boundary /></div>
            @endif
        </section>
    @endisset

    <div class="report-operational-summary">
    @isset($sections['production'])
        @php $p = $sections['production']; @endphp
        <section class="report-operational-panel" aria-labelledby="dash-production">
            <h2 id="dash-production" class="report-section-title"><i class="fas fa-industry" aria-hidden="true"></i> الإنتاج</h2>
            <div class="report-kpis">
                <x-report.kpi label="أوامر إنتاج نشطة" :value="$num($p['active_orders'])" icon="fa-gears" tone="info" :href="route('production.board.index')" />
                <x-report.kpi label="الكمية المنجزة (أوامر مكتملة بالفترة)" :value="$num($p['completed_quantity'])" icon="fa-circle-check" tone="success" />
                @if ($p['show_cost'])
                    <x-report.kpi label="التكلفة المخططة / الفعلية (المكتملة)" :value="$p['completed_orders'] ? $money($p['planned_cost']).' / '.$money($p['actual_cost']) : null" icon="fa-scale-unbalanced" tone="secondary" :href="route('reports.production.variance', $period->toQuery())" />
                    <x-report.kpi label="انحراف تكلفة المواد" :value="$p['completed_orders'] ? $money($p['cost_variance']).($p['variance_pct'] !== null ? ' ('.$pct($p['variance_pct']).')' : '') : null" icon="fa-arrow-trend-up" tone="warning" :alert="$p['cost_variance'] > 0" />
                @endif
                <x-report.kpi label="حوادث جودة مفتوحة" :value="$num($p['open_incidents'])" icon="fa-triangle-exclamation" tone="danger" :alert="$p['open_incidents'] > 0" :href="route('reports.production.quality', ['tab' => 'quality'])" />
                <x-report.kpi label="كمية إعادة العمل المفتوحة" :value="$num($p['open_rework_qty'])" icon="fa-rotate" tone="warning" :href="route('reports.production.quality', ['tab' => 'rework'])" />
            </div>
        </section>
    @endisset

    @isset($sections['inventory'])
        @php $i = $sections['inventory']; @endphp
        <section class="report-operational-panel" aria-labelledby="dash-inventory">
            <h2 id="dash-inventory" class="report-section-title"><i class="fas fa-warehouse" aria-hidden="true"></i> المخزون</h2>
            <div class="report-kpis">
                @if ($i['stock_value'] !== null)
                    <x-report.kpi label="القيمة التشغيلية لمخزون المواد" :value="$money($i['stock_value'])" icon="fa-coins" tone="primary" hint="تكلفة اللوت — ليست تقييماً محاسبياً" :href="route('reports.inventory', ['tab' => 'valuation'])" />
                @endif
                <x-report.kpi label="مواد عند/دون حد إعادة الطلب" :value="$num($i['below_reorder'])" icon="fa-arrow-down-wide-short" tone="warning" :alert="$i['below_reorder'] > 0" :href="route('reports.inventory', ['tab' => 'stock', 'state' => 'below_reorder'])" />
                <x-report.kpi label="مواد بطيئة الحركة (90 يوماً)" :value="$num($i['slow_moving'])" icon="fa-hourglass-half" tone="secondary" :href="route('reports.inventory', ['tab' => 'slow', 'days' => 90])" />
                <x-report.kpi label="عجز أقمشة لأوامر نشطة" :value="$num($i['fabric_shortages'])" icon="fa-scroll" tone="danger" :alert="$i['fabric_shortages'] > 0" :href="route('reports.inventory', ['tab' => 'shortages'])" />
            </div>
        </section>
    @endisset

    @isset($sections['procurement'])
        @php $pr = $sections['procurement']; @endphp
        <section class="report-operational-panel" aria-labelledby="dash-procurement">
            <h2 id="dash-procurement" class="report-section-title"><i class="fas fa-cart-shopping" aria-hidden="true"></i> المشتريات</h2>
            <div class="report-kpis">
                <x-report.kpi label="قيمة الاستلام الفعلية (بالفترة)" :value="$money($pr['received_value'])" icon="fa-truck-ramp-box" tone="primary" :href="route('reports.procurement', $period->toQuery())" />
                <x-report.kpi label="قيمة أوامر الشراء المفتوحة (المتبقي)" :value="$money($pr['open_po_value'])" icon="fa-file-invoice" tone="info" />
                <x-report.kpi label="أوامر شراء متأخرة" :value="$num($pr['late_po_count'])" icon="fa-clock" tone="danger" :alert="$pr['late_po_count'] > 0" :href="route('reports.exceptions')" />
                <x-report.kpi label="انحراف أسعار الشراء (بالفترة)" :value="$money($pr['price_variance'])" icon="fa-scale-unbalanced" tone="warning" :alert="$pr['price_variance'] > 0" hint="موجب = دفعنا أكثر من المتفق" />
                <x-report.kpi label="التوريد في الموعد" :value="$pct($pr['on_time_pct'])" icon="fa-calendar-check" tone="success" />
            </div>
        </section>
    @endisset

    @isset($sections['fulfillment'])
        @php $f = $sections['fulfillment']; @endphp
        <section class="report-operational-panel" aria-labelledby="dash-fulfillment">
            <h2 id="dash-fulfillment" class="report-section-title"><i class="fas fa-truck-fast" aria-hidden="true"></i> التنفيذ والتوصيل</h2>
            <div class="report-kpis">
                <x-report.kpi label="منتجات جاهزة غير مسلمة" :value="$qty($f['fg_ready_qty'])" icon="fa-boxes-stacked" tone="warning" :href="route('reports.fulfillment', ['tab' => 'finished_goods'])" />
                <x-report.kpi label="توصيلات مكتملة (بالفترة)" :value="$num($f['delivered'])" icon="fa-circle-check" tone="success" />
                <x-report.kpi label="متعذرة / معاد جدولتها (بالفترة)" :value="$num($f['failed_rescheduled'])" icon="fa-triangle-exclamation" tone="danger" :alert="$f['failed_rescheduled'] > 0" />
                <x-report.kpi label="بانتظار التركيب" :value="$num($f['awaiting_installation'])" icon="fa-screwdriver-wrench" tone="info" />
            </div>
        </section>
    @endisset

    @isset($sections['receivables'])
        @php $r = $sections['receivables']; @endphp
        <section class="report-operational-panel" aria-labelledby="dash-receivables">
            <h2 id="dash-receivables" class="report-section-title"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> الذمم والتحصيل <span class="report-note fw-normal">(الوضع الحالي)</span></h2>
            <div class="report-kpis">
                <x-report.kpi label="المبالغ المستحقة" :value="$money($r['outstanding'])" icon="fa-file-invoice-dollar" tone="primary" :href="route('reports.receivables')" />
                <x-report.kpi label="المتأخرات" :value="$money($r['overdue'])" icon="fa-clock" tone="danger" :alert="$r['overdue'] > 0" />
                <x-report.kpi label="دفعات غير مخصصة" :value="$money($r['unallocated'])" icon="fa-money-bill-transfer" tone="warning" />
                <x-report.kpi label="عملاء تجاوزوا حد الائتمان" :value="$num($r['credit_holds'])" icon="fa-ban" tone="danger" :alert="$r['credit_holds'] > 0" />
            </div>
        </section>
    @endisset
    </div>
    {{-- Today --}}
    <section aria-labelledby="dash-today">
        <h2 id="dash-today" class="report-section-title"><i class="fas fa-sun" aria-hidden="true"></i> ملخص اليوم</h2>
        <div class="report-kpis report-today">
            @foreach ($today as $item)
                <x-report.kpi :label="$item['label']" :value="$item['value']" :icon="$item['icon']" tone="primary" />
            @endforeach
            <x-report.kpi label="استثناءات تشغيلية مفتوحة" :value="$num($exceptionCount)" icon="fa-triangle-exclamation" tone="danger" :alert="$exceptionCount > 0" :href="route('reports.exceptions')" />
        </div>
    </section>

</div>
@endsection
