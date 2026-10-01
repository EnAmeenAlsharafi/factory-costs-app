@extends('layouts.app')

@section('title', 'تفصيل مساهمة الطلب '.$order->order_number)
@section('page-title', 'تفصيل مساهمة الطلب')

@php
    use App\Domain\Reports\ReportFormat as F;
    $money = fn ($value) => $value === null ? '—' : F::money($value);
    $qty = fn ($value) => $value === null ? '—' : F::quantity($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="'تفصيل المساهمة التشغيلية — '.$order->order_number" icon="fa-magnifying-glass-chart"
        :subtitle="$order->customer?->name.' — '.$order->salesChannel?->name_ar.' — '.$order->order_date?->format('Y-m-d')">
        <x-slot:actions>
            <a href="{{ route('reports.profitability.orders') }}" class="btn btn-light border"><i class="fas fa-arrow-right" aria-hidden="true"></i> عودة للتقرير</a>
            <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-outline-primary">فتح الطلب</a>
        </x-slot:actions>
    </x-report.header>

    @if ($summary)
        <div class="report-kpis">
            <x-report.kpi label="القيمة التجارية للطلب" :value="F::money($summary['commercial_value'])" icon="fa-coins" tone="primary" />
            <x-report.kpi label="تكلفة المواد الفعلية" :value="$summary['actual_cost'] !== null ? F::money($summary['actual_cost']) : null" icon="fa-boxes-packing" tone="secondary" />
            <x-report.kpi label="المساهمة التشغيلية" :value="$summary['contribution'] !== null ? F::money($summary['contribution']) : null" icon="fa-scale-balanced" tone="success" :alert="$summary['is_negative']" />
            <x-report.kpi label="هامش المساهمة" :value="$summary['contribution_pct'] !== null ? F::percent($summary['contribution_pct']) : null" icon="fa-percent" tone="success" :alert="$summary['is_negative']" />
            <x-report.kpi label="حالة التكلفة" :value="$summary['profitability_status']['label']" icon="fa-flag-checkered" :tone="$summary['profitability_status']['tone']" />
        </div>
        @if ($summary['profitability_status']['label'] !== \App\Domain\Reports\ProfitabilityReportingService::STATUSES['FINAL_OPERATIONAL']['label'])
            <div class="alert alert-warning mb-0 fs-7" role="note"><i class="fas fa-hourglass-half" aria-hidden="true"></i> الإنتاج أو ترحيل المواد لم يكتمل بعد؛ تكلفة المواد الفعلية قد تتغير ولا تُعرض مساهمة نهائية.</div>
        @endif
        @if ($order->status === 'CANCELLED')
            <div class="alert alert-secondary mb-0 fs-7">هذا الطلب ملغى ولا يدخل في إجماليات الربحية الاعتيادية.</div>
        @endif
    @endif

    {{-- Commercial --}}
    <details class="report-detail" open><summary>بنود الطلب</summary><section aria-labelledby="od-commercial">
        <h2 id="od-commercial" class="report-section-title"><i class="fas fa-file-invoice" aria-hidden="true"></i> التجاري (القيم التاريخية للطلب)</h2>
        <div class="table-responsive">
            <table class="table table-factory align-middle mb-0">
                <thead><tr><th>البند</th><th class="text-end">الكمية</th><th class="text-end">سعر الوحدة</th><th class="text-end">الخصم</th><th class="text-end">إجمالي البند</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>
                                {{ $line->custom_design ? ($line->custom_design_name ?: 'تصميم خاص') : ($line->productModel?->name_ar ?? '—') }}
                                @if ($line->productConfiguration)
                                    <span class="text-muted fs-8">— <span class="ltr-isolate">{{ (float) $line->productConfiguration->width_cm }}×{{ (float) $line->productConfiguration->length_cm }}</span> {{ $line->has_storage ? 'مع تخزين' : 'بدون تخزين' }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $qty($line->quantity) }}</td>
                            <td class="text-end">{{ $money($line->unit_price) }}</td>
                            <td class="text-end">{{ $line->discount_amount > 0 ? $money($line->discount_amount) : '—' }}</td>
                            <td class="text-end fw-semibold">{{ $money($line->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end fw-bold">قيمة الطلب المعتمدة (بعد خصم الطلب)</td><td class="text-end fw-bold">{{ $money($order->total_amount) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </section></details>

    {{-- Manufacturing per production order --}}
    @forelse ($productionOrders as $entry)
        @php $po = $entry['po']; $s = $entry['summary']; @endphp
        <details class="report-detail"><summary>المواد الفعلية والانحراف — <bdi>{{ $po->production_order_number }}</bdi></summary><section aria-labelledby="od-po-{{ $po->id }}">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <h2 id="od-po-{{ $po->id }}" class="report-section-title mb-0">
                    <i class="fas fa-industry" aria-hidden="true"></i>
                    أمر الإنتاج <span class="ltr-isolate">{{ $po->production_order_number }}</span>
                    <span class="report-note fw-normal">— كمية مُطلقة {{ $po->released_quantity }} — {{ $po->status_arabic }}</span>
                </h2>
                <a href="{{ route('reports.production.variance.show', $po) }}" class="btn btn-sm btn-outline-primary report-no-print">تفصيل الانحراف</a>
            </div>
            <dl class="detail-list mb-3">
                <div><dt>الوصفة / النسخة (مخطط)</dt><dd>{{ $po->recipeVersion?->recipe?->name ?? '—' }} {{ $po->recipeVersion ? 'V'.$po->recipeVersion->version_number : '' }}</dd></div>
                <div><dt>التكلفة المخططة / المرجعية</dt><dd>{{ $money($s['planned_bom_cost'] ?: null) }}</dd></div>
                <div><dt>المواد المصروفة</dt><dd>{{ $money($s['total_issued_cost']) }}</dd></div>
                <div><dt>المرتجع القابل للاستخدام</dt><dd>{{ $money($s['total_returned_cost']) }}</dd></div>
                <div><dt>تكلفة المواد الفعلية (صافي)</dt><dd class="fw-bold">{{ $money($s['actual_net_material_cost']) }}</dd></div>
                <div><dt>انحراف التكلفة</dt><dd @class(['text-danger' => $s['cost_variance'] > 0])>{{ $s['planned_bom_cost'] > 0 ? $money($s['cost_variance']) : '—' }}</dd></div>
                <div><dt>منها إعادة عمل (ضمن الفعلية)</dt><dd>{{ $money($s['rework_issued_cost']) }}</dd></div>
                <div><dt>منها هدر (تحليلي، ضمن الفعلية)</dt><dd>{{ $money($s['total_waste_cost']) }}</dd></div>
            </dl>
            <div class="table-responsive">
                <table class="table table-factory table-sticky-first align-middle mb-0 fs-7">
                    <thead><tr>
                        <th>المادة</th><th>الوحدة</th><th class="text-end">مخطط</th><th class="text-end">مصروف</th><th class="text-end">مرتجع</th>
                        <th class="text-end">مستهلك صافي</th><th class="text-end">انحراف الكمية</th><th class="text-end">تكلفة مخططة</th><th class="text-end">تكلفة فعلية</th><th class="text-end">انحراف التكلفة</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($entry['rows'] as $row)
                            <tr @class(['report-row-alert' => $row['is_negative']])>
                                <td>{{ $row['material'] }} @if ($row['outside_bom'])<span class="status-chip status-chip-warning">خارج الوصفة</span>@endif</td>
                                <td>{{ $row['unit'] ?? '—' }}</td>
                                <td class="text-end">{{ $qty($row['planned_qty']) }}</td>
                                <td class="text-end">{{ $qty($row['issued_qty']) }}</td>
                                <td class="text-end">{{ $qty($row['returned_qty']) }}</td>
                                <td class="text-end fw-semibold">{{ $qty($row['consumed_qty']) }}</td>
                                <td class="text-end">{{ $row['unit_mismatch'] ? 'وحدات مختلفة' : $qty($row['variance_qty']) }}</td>
                                <td class="text-end">{{ $money($row['planned_cost']) }}</td>
                                <td class="text-end">{{ $money($row['actual_cost']) }}</td>
                                <td class="text-end">{{ $money($row['cost_variance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section></details>
    @empty
        <div class="card-factory empty-state" role="status"><p class="mb-0">لم تُنشأ أوامر إنتاج لهذا الطلب بعد — لا توجد تكلفة فعلية.</p></div>
    @endforelse

    <x-report.boundary />
</div>
@endsection
