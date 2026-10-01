@extends('layouts.app')

@section('title', 'انحراف المواد — '.$po->production_order_number)
@section('page-title', 'انحراف المواد')

@php
    $money = fn ($value) => $value === null ? '—' : \App\Domain\Reports\ReportFormat::money($value);
    $qty = fn ($value) => $value === null ? '—' : \App\Domain\Reports\ReportFormat::quantity($value);
    $pct = fn ($value) => $value === null ? '—' : \App\Domain\Reports\ReportFormat::percent($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="'انحراف المواد — '.$po->production_order_number" icon="fa-layer-group"
        :subtitle="($po->is_custom_design ? ($po->custom_design_name ?: 'تصميم خاص') : $po->productModel?->name_ar).' — كمية مُطلقة '.$po->released_quantity.' — '.$po->status_arabic">
        <x-slot:actions>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('reports.production.variance') }}" class="btn btn-light border"><i class="fas fa-arrow-right" aria-hidden="true"></i> عودة</a>
            @can('production.view')
                <a href="{{ route('production.orders.show', $po) }}" class="btn btn-outline-primary">فتح أمر الإنتاج</a>
            @endcan
        </x-slot:actions>
    </x-report.header>

    @if ($siblings->isNotEmpty())
        <div class="alert alert-info mb-0 fs-7" role="note">
            <i class="fas fa-code-branch" aria-hidden="true"></i>
            بند الطلب مقسّم على عدة أوامر إنتاج؛ هذا التحليل يخص هذا الأمر فقط بكميته المُطلقة ({{ $po->released_quantity }}).
            الأوامر الأخرى:
            @foreach ($siblings as $sibling)
                <a href="{{ route('reports.production.variance.show', $sibling->id) }}" class="ltr-isolate">{{ $sibling->production_order_number }}</a> ({{ $sibling->released_quantity }})@if (! $loop->last)، @endif
            @endforeach
        </div>
    @endif

    <div class="report-kpis">
        <x-report.kpi label="التكلفة المخططة / المرجعية" :value="$summary['planned_bom_cost'] > 0 ? $money($summary['planned_bom_cost']) : null" icon="fa-clipboard-list" tone="secondary" />
        <x-report.kpi label="المصروف المرحّل" :value="$money($summary['total_issued_cost'])" icon="fa-dolly" tone="info" />
        <x-report.kpi label="المرتجع القابل للاستخدام" :value="$money($summary['total_returned_cost'])" icon="fa-rotate-left" tone="info" />
        <x-report.kpi label="تكلفة المواد الفعلية" :value="$money($summary['actual_net_material_cost'])" icon="fa-boxes-packing" tone="primary" />
        <x-report.kpi label="انحراف التكلفة" :value="$summary['planned_bom_cost'] > 0 ? $money($summary['cost_variance']).' ('.$pct($summary['cost_variance_percentage']).')' : null" icon="fa-arrow-trend-up" tone="warning" :alert="$summary['cost_variance'] > 0" />
        <x-report.kpi label="منها إعادة عمل / هدر (تحليلي)" :value="$money($summary['rework_issued_cost']).' / '.$money($summary['total_waste_cost'])" icon="fa-recycle" tone="secondary" />
    </div>

    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory table-sticky-first align-middle mb-0">
                <thead><tr>
                    <th>المادة</th><th>الفئة</th><th>الوحدة</th>
                    <th class="text-end">كمية الوصفة/وحدة</th><th class="text-end">هدر الوصفة %</th><th class="text-end">المخطط</th>
                    <th class="text-end">المصروف</th><th class="text-end">المرتجع</th><th class="text-end">المستهلك الفعلي</th><th class="text-end">الهدر المسجل</th>
                    <th class="text-end">انحراف الكمية</th><th class="text-end">التكلفة المخططة</th><th class="text-end">تكلفة اللوت الفعلية</th><th class="text-end">انحراف التكلفة</th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr @class(['report-row-alert' => $row['is_negative']])>
                            <td>{{ $row['material'] }} <span class="ltr-isolate fs-8 text-muted">{{ $row['code'] }}</span>
                                @if ($row['outside_bom'])<span class="status-chip status-chip-warning">خارج الوصفة</span>@endif</td>
                            <td>{{ $row['category'] ?? '—' }}</td>
                            <td>{{ $row['unit'] ?? '—' }}</td>
                            <td class="text-end">{{ $qty($row['bom_qty']) }}</td>
                            <td class="text-end">{{ $row['waste_pct'] !== null ? $pct($row['waste_pct']) : '—' }}</td>
                            <td class="text-end">{{ $qty($row['planned_qty']) }}</td>
                            <td class="text-end">{{ $qty($row['issued_qty']) }}</td>
                            <td class="text-end">{{ $qty($row['returned_qty']) }}</td>
                            <td class="text-end fw-semibold">{{ $qty($row['consumed_qty']) }}</td>
                            <td class="text-end">{{ $qty($row['waste_qty']) }}</td>
                            <td class="text-end">{{ $row['unit_mismatch'] ? 'وحدات مختلفة' : $qty($row['variance_qty']) }}</td>
                            <td class="text-end">{{ $money($row['planned_cost']) }}</td>
                            <td class="text-end">{{ $money($row['actual_cost']) }}</td>
                            <td class="text-end">{{ $money($row['cost_variance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="14">لا توجد متطلبات مواد أو صرف مسجل لهذا الأمر.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($wasteRecords->isNotEmpty())
        <section class="card-factory p-3" aria-labelledby="vs-waste">
            <h2 id="vs-waste" class="report-section-title"><i class="fas fa-recycle" aria-hidden="true"></i> سجلات الهدر (تحليلية — ضمن التكلفة الفعلية)</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>الرقم</th><th>المادة</th><th class="text-end">الكمية</th><th>السبب</th><th class="text-end">التكلفة</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        @foreach ($wasteRecords as $waste)
                            <tr>
                                <td class="ltr-isolate">{{ $waste->waste_number }}</td><td>{{ $waste->material }}</td>
                                <td class="text-end">{{ $qty($waste->quantity) }} {{ $waste->unit }}</td><td>{{ $waste->reason ?? '—' }}</td>
                                <td class="text-end">{{ $money($waste->total_cost) }}</td><td class="ltr-isolate">{{ \Illuminate\Support\Str::of($waste->occurred_at)->substr(0, 10) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
