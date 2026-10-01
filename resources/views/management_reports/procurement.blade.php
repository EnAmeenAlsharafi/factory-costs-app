@extends('layouts.app')

@section('title', 'المشتريات والموردون')
@section('page-title', 'تحليل المشتريات')

@php
    use App\Domain\Reports\ReportFormat as F;
    $money = fn ($value) => $value === null ? '—' : F::money($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="المشتريات والموردون" icon="fa-truck-field" :period="$period" basis="تاريخ الاستلام (الفعلي) / تاريخ أمر الشراء (الملتزم به)" exportable
        subtitle="الملتزم به (أوامر الشراء) منفصل عن المستلم فعلياً (سندات الاستلام المرحّلة). انحراف السعر = سعر الاستلام الفعلي − سعر أمر الشراء المتفق، بالوحدة الأساسية — دون أي أثر محاسبي."
        :filters="['الفترة' => $period->label()]">
        <x-slot:actions>
            <a href="{{ route('reports.procurement.price-history') }}" class="btn btn-outline-primary"><i class="fas fa-chart-line" aria-hidden="true"></i> تاريخ الأسعار</a>
        </x-slot:actions>
    </x-report.header>
    <x-report.period-filter :period="$period" />

    <div class="report-kpis">
        <x-report.kpi label="قيمة الاستلام الفعلية" :value="$money($summary['received_value'])" icon="fa-truck-ramp-box" tone="primary" :hint="$summary['receipt_count'].' سند — '.$summary['supplier_count'].' مورد'" />
        <x-report.kpi label="قيمة أوامر الشراء الملتزم بها" :value="$money($summary['ordered_value'])" icon="fa-file-signature" tone="info" :hint="$summary['ordered_count'].' أمر شراء (حسب تاريخ الأمر)'" />
        <x-report.kpi label="المتبقي على أوامر مفتوحة" :value="$money($summary['open_po_value'])" icon="fa-hourglass-half" tone="secondary" hint="الوضع الحالي" />
        <x-report.kpi label="أوامر شراء متأخرة" :value="number_format($summary['late_po_count'])" icon="fa-clock" tone="danger" :alert="$summary['late_po_count'] > 0" hint="الوضع الحالي" />
        <x-report.kpi label="انحراف أسعار الشراء" :value="$money($summary['price_variance'])" icon="fa-scale-unbalanced" tone="warning" :alert="$summary['price_variance'] > 0" hint="موجب = غير مواتٍ" />
    </div>

    <section id="supplier-analysis" class="table-factory-wrapper" aria-labelledby="suppliers-table">
        <h2 id="suppliers-table" class="report-section-title p-3 mb-0"><i class="fas fa-truck" aria-hidden="true"></i> تحليل الموردين <span class="report-note fw-normal">(حقائق دون تقييم؛ الأوامر حسب تاريخ الأمر، الاستلام حسب تاريخه)</span></h2>
        <div class="report-mobile-records report-no-print">
            @forelse ($suppliers as $row)
                <article class="report-record">
                    <h3 class="h6 fw-bold">{{ $row['supplier'] }}</h3>
                    <dl class="report-record-grid">
                        <div><dt>قيمة الاستلام الفعلية</dt><dd><bdi>{{ $money($row['received_value']) }}</bdi></dd></div>
                        <div><dt>أوامر الشراء</dt><dd><bdi>{{ number_format($row['po_count']) }}</bdi></dd></div>
                        <div><dt>مواد موردة</dt><dd><bdi>{{ number_format($row['materials']) }}</bdi></dd></div>
                        <div><dt>التوريد في الموعد</dt><dd><bdi>{{ $row['on_time_pct'] !== null ? F::percent($row['on_time_pct']) : '—' }}</bdi></dd></div>
                    </dl>
                    <details><summary>تفاصيل أداء المورد</summary><dl class="report-record-grid">
                        <div><dt>قيمة الأوامر</dt><dd><bdi>{{ $money($row['ordered_value']) }}</bdi></dd></div>
                        <div><dt>متأخرة / جزئية</dt><dd><bdi>{{ $row['late_pos'] }} / {{ $row['partial_pos'] }}</bdi></dd></div>
                        <div><dt>انحراف السعر</dt><dd><bdi>{{ $money($row['price_variance']) }}</bdi></dd></div>
                        <div><dt>متوسط مدة التوريد</dt><dd><bdi>{{ $row['avg_lead_days'] !== null ? F::quantity($row['avg_lead_days']).' يوم' : '—' }}</bdi></dd></div>
                        <div><dt>الأوامر المكتملة</dt><dd><bdi>{{ $row['completed_pos'] }}</bdi></dd></div>
                    </dl></details>
                </article>
            @empty<x-report.empty-state message="لا توجد أوامر شراء أو استلامات ضمن الفترة." />@endforelse
        </div>
        <div class="table-responsive report-desktop-table">
            <table class="table table-factory table-sticky-first align-middle mb-0">
                <thead><tr>
                    <th>المورد</th><th class="text-end">أوامر شراء</th><th class="text-end">قيمة الأوامر</th><th class="text-end">قيمة الاستلام الفعلية</th>
                    <th class="text-end">مواد موردة</th><th class="text-end">متوسط مدة التوريد (يوم)</th><th class="text-end">في الموعد</th>
                    <th class="text-end">متأخرة</th><th class="text-end">مستلمة جزئياً</th><th class="text-end">انحراف السعر</th>
                </tr></thead>
                <tbody>
                    @forelse ($suppliers as $row)
                        <tr @class(['report-row-alert' => ($row['price_variance'] ?? 0) > 0 || $row['late_pos'] > 0])>
                            <td class="fw-semibold">{{ $row['supplier'] }}</td>
                            <td class="text-end">{{ $row['po_count'] }}</td><td class="text-end">{{ $money($row['ordered_value']) }}</td>
                            <td class="text-end">{{ $money($row['received_value']) }}</td><td class="text-end">{{ $row['materials'] }}</td>
                            <td class="text-end">{{ $row['avg_lead_days'] !== null ? F::quantity($row['avg_lead_days']) : '—' }}</td>
                            <td class="text-end">{{ $row['on_time_pct'] !== null ? F::percent($row['on_time_pct']).' من '.$row['completed_pos'] : '—' }}</td>
                            <td class="text-end">{{ $row['late_pos'] }}</td><td class="text-end">{{ $row['partial_pos'] }}</td>
                            <td class="text-end">{{ $money($row['price_variance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">لا توجد أوامر شراء أو استلامات ضمن الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <h2 class="report-section-title mb-0"><i class="fas fa-scale-unbalanced" aria-hidden="true"></i> انحراف أسعار الشراء حسب سند الاستلام</h2>
    <x-report.table :table="$table" caption="انحراف أسعار الشراء" />
</div>
@endsection
