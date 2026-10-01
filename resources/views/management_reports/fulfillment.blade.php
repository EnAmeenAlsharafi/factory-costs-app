@extends('layouts.app')

@section('title', $tabs[$tab])
@section('page-title', 'التنفيذ والتوصيل')

@php
    use App\Domain\Reports\ReportFormat as F;
    $basis = ['pipeline' => 'تاريخ طلب العميل', 'finished_goods' => 'الوضع الحالي (سجل حركة المنتجات الجاهزة)', 'delivery' => 'تواريخ أحداث التوصيل', 'returns' => 'تاريخ بلاغ المرتجع'][$tab];
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="$tabs[$tab]" icon="fa-route" :period="$tab === 'finished_goods' ? null : $period" :basis="$basis" :exportable="$tab !== 'delivery'"
        :filters="['الفترة' => $tab === 'finished_goods' ? 'الوضع الحالي' : $period->label()]" />

    <nav class="segment-tabs report-no-print" aria-label="أقسام تقرير التنفيذ">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('reports.fulfillment', ($key === 'finished_goods' ? [] : $period->toQuery()) + ['tab' => $key]) }}" class="segment-tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab !== 'finished_goods')
        <x-report.period-filter :period="$period" :keep="['tab' => $tab]">
            @if ($tab === 'pipeline')
                <div class="col-6 col-lg-2">
                    <label for="f-stage" class="form-label fs-7">العرض</label>
                    <select id="f-stage" name="stage" class="form-select">
                        <option value="">كل الطلبات</option>
                        <option value="open" @selected(request('stage') === 'open')>لم تُسلَّم بالكامل</option>
                    </select>
                </div>
            @endif
        </x-report.period-filter>
    @endif

    @if ($tab === 'delivery')
        <div class="report-kpis">
            <x-report.kpi label="توصيلات مكتملة بالفترة" :value="number_format($summary['delivered_in_period'])" icon="fa-circle-check" tone="success" />
            <x-report.kpi label="تركيبات مكتملة بالفترة" :value="number_format($summary['installed_in_period'])" icon="fa-screwdriver-wrench" tone="success" />
            <x-report.kpi label="تعذر التسليم بالفترة" :value="number_format($summary['failed_in_period'])" icon="fa-triangle-exclamation" tone="danger" :alert="$summary['failed_in_period'] > 0" />
            <x-report.kpi label="إعادة جدولة بالفترة" :value="number_format($summary['rescheduled_in_period'])" icon="fa-calendar-days" tone="warning" />
            <x-report.kpi label="متوسط زمن الخروج ← التسليم" :value="$summary['avg_dispatch_to_delivery_hours'] !== null ? F::quantity($summary['avg_dispatch_to_delivery_hours']).' ساعة' : null" icon="fa-stopwatch" tone="info" :hint="$summary['duration_sample'] ? 'من '.$summary['duration_sample'].' توصيلة' : 'لا توجد بيانات كافية'" />
            <x-report.kpi label="بانتظار التركيب (الآن)" :value="number_format($summary['awaiting_installation'])" icon="fa-hourglass-half" tone="primary" />
        </div>
        @php
            $total = max(1, $summary['statuses']->sum('count'));
            $colors = ['secondary' => '#94a3b8', 'primary' => '#3b82f6', 'info' => '#06b6d4', 'warning' => '#f59e0b', 'success' => '#22c55e', 'danger' => '#ef4444', 'dark' => '#334155'];
        @endphp
        <section class="card-factory p-3" aria-labelledby="delivery-dist">
            <h2 id="delivery-dist" class="h6 fw-bold">توزيع أوامر التوصيل حسب الحالة (الوضع الحالي)</h2>
            <div class="report-stack" role="img" aria-label="توزيع حالات التوصيل">
                @foreach ($summary['statuses'] as $status)
                    @if ($status['count'] > 0)<span style="width: {{ round($status['count'] / $total * 100, 1) }}%; background: {{ $colors[$status['tone']] ?? '#94a3b8' }}" title="{{ $status['label'] }}: {{ $status['count'] }}"></span>@endif
                @endforeach
            </div>
            <div class="report-legend">
                @foreach ($summary['statuses'] as $status)
                    <span><i style="background: {{ $colors[$status['tone']] ?? '#94a3b8' }}"></i>{{ $status['label'] }}: <strong>{{ $status['count'] }}</strong></span>
                @endforeach
            </div>
        </section>
    @else
        @if ($tab === 'returns')
            <div class="report-kpis">
                <x-report.kpi label="مرتجعات بالفترة" :value="number_format($totals['return_count'])" icon="fa-rotate-left" tone="warning" />
                <x-report.kpi label="الكمية المرتجعة" :value="F::quantity($totals['quantity'])" icon="fa-cubes" tone="secondary" />
            </div>
            <div class="alert alert-info mb-0 fs-7" role="note">استلام المرتجع فعلياً لا يعني تنفيذ استرداد مالي، ولا يُخفّض القيمة التجارية للطلب في تقارير الربحية.</div>
        @endif
        @if ($tab === 'finished_goods')
            @php
                $waitingBuckets = collect($table->rows)->filter(fn ($row) => $row['days_waiting'] !== null)->groupBy(fn ($row) => $row['days_waiting'] <= 2 ? '0–2' : ($row['days_waiting'] <= 7 ? '3–7' : ($row['days_waiting'] <= 14 ? '8–14' : '15+')));
            @endphp
            <section aria-label="أعمار المنتجات الجاهزة في الصفحة">
                <h2 class="report-section-title">مدة الانتظار — السجلات في هذه الصفحة</h2>
                <div class="report-kpis">
                    @foreach (['0–2', '3–7', '8–14', '15+'] as $bucket)
                        <x-report.kpi :label="$bucket.' يوم'" :value="number_format($waitingBuckets->get($bucket, collect())->count()).' أمر'" :hint="$bucket === '15+' ? 'انتظار طويل — راجع خطة التوصيل' : 'من أول استلام إنتاج'" :alert="$bucket === '15+' && $waitingBuckets->get($bucket, collect())->isNotEmpty()" />
                    @endforeach
                </div>
            </section>
            <p class="report-note mb-0">الكمية الجاهزة = إجمالي الحركات الواردة − الصادرة في سجل المنتجات الجاهزة لكل أمر إنتاج. "جاهز منذ" = أول استلام إنتاج.</p>
        @endif
        <x-report.table :table="$table" :caption="$tabs[$tab]" />
    @endif
</div>
@endsection
