@extends('layouts.app')

@section('title', 'هيكل تكلفة المواد والأقمشة')
@section('page-title', 'هيكل التكلفة')

@php
    use App\Domain\Reports\ReportFormat as F;
    $palette = ['#1d4ed8', '#0e7490', '#15803d', '#b45309', '#7c3aed', '#be123c', '#475569', '#0f766e', '#a16207'];
    $maxCategory = max(0.01, (float) $byCategory->max('actual_cost'));
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="هيكل تكلفة المواد والأقمشة" icon="fa-layer-group" :period="$period" basis="تاريخ إطلاق/اكتمال أمر الإنتاج"
        subtitle="تكلفة المواد الفعلية (مصروف − مرتجع) موزعة حسب فئة المادة، وتركيبة تكلفة المنتج من الأوامر المكتملة فقط، وتحليل الأقمشة."
        :filters="['الفترة' => $period->label()]" />
    <x-report.period-filter :period="$period" />

    <section class="card-factory p-3" aria-labelledby="cs-category">
        <h2 id="cs-category" class="report-section-title"><i class="fas fa-shapes" aria-hidden="true"></i> تكلفة المواد الفعلية حسب الفئة <span class="report-note fw-normal">(أوامر أُطلقت بالفترة)</span></h2>
        @if ($byCategory->isEmpty())
            <p class="text-muted mb-0">لا توجد مواد مصروفة لأوامر إنتاج أُطلقت ضمن الفترة.</p>
        @else
            <div class="report-bars">
                @foreach ($byCategory as $row)
                    <div class="report-bar">
                        <span class="report-bar-label">{{ $row['category'] }}</span>
                        <span class="report-bar-track"><span class="report-bar-fill" style="width: {{ round(max(0, $row['actual_cost']) / $maxCategory * 100, 1) }}%"></span></span>
                        <span class="report-bar-value">{{ F::money($row['actual_cost']) }} <span class="text-muted fw-normal">({{ $row['share_pct'] !== null ? F::percent($row['share_pct']) : '—' }})</span></span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card-factory p-3" aria-labelledby="cs-product">
        <h2 id="cs-product" class="report-section-title"><i class="fas fa-bed" aria-hidden="true"></i> تركيبة تكلفة المنتج <span class="report-note fw-normal">(أوامر مكتملة بالفترة فقط)</span></h2>
        @forelse ($breakdown as $product)
            <div class="border-bottom py-2">
                <div class="d-flex flex-wrap justify-content-between gap-2 fs-7">
                    <strong>{{ $product['product'] }}</strong>
                    <span class="text-muted">{{ $product['po_count'] }} أمر — {{ F::quantity($product['units']) }} وحدة — متوسط {{ $product['cost_per_unit'] !== null ? F::money($product['cost_per_unit']) : '—' }} للوحدة</span>
                </div>
                @if ($product['po_count'] < 2)
                    <div class="report-note">عينة صغيرة (أمر واحد) — للاسترشاد فقط.</div>
                @endif
                <div class="report-stack mt-1" role="img" aria-label="تركيبة تكلفة {{ $product['product'] }}">
                    @foreach ($product['composition'] as $part)
                        <span style="width: {{ max(0, (float) $part['share_pct']) }}%; background: {{ $palette[$loop->index % count($palette)] }};" title="{{ $part['category'] }} {{ F::percent($part['share_pct'] ?? 0) }}"></span>
                    @endforeach
                </div>
                <div class="report-legend">
                    @foreach ($product['composition'] as $part)
                        <span><i style="background: {{ $palette[$loop->index % count($palette)] }}"></i>{{ $part['category'] }} {{ $part['share_pct'] !== null ? F::percent($part['share_pct']) : '—' }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">لا توجد أوامر إنتاج مكتملة بالفترة تكفي لعرض تركيبة التكلفة.</p>
        @endforelse
    </section>

    <section class="card-factory p-0" aria-labelledby="cs-fabric">
        <h2 id="cs-fabric" class="report-section-title p-3 mb-0"><i class="fas fa-scroll" aria-hidden="true"></i> تحليل تكلفة الأقمشة <span class="report-note fw-normal">(أوامر أُطلقت بالفترة)</span></h2>
        <div class="table-responsive">
            <table class="table table-factory table-sticky-first align-middle mb-0">
                <thead><tr><th>القماش</th><th>المورد (من اللوت)</th><th>اللون</th><th class="text-end">الاستهلاك الصافي</th><th class="text-end">التكلفة الفعلية</th><th class="text-end">هدر القماش (كل الألوان)</th><th>الموديلات المستهلكة</th></tr></thead>
                <tbody>
                    @forelse ($fabrics as $fabric)
                        <tr>
                            <td>{{ $fabric['fabric'] }}</td><td>{{ $fabric['supplier'] }}</td>
                            <td class="ltr-isolate">{{ $fabric['color_code'] ?? '—' }}</td>
                            <td class="text-end">{{ F::quantity($fabric['consumed_qty']) }}</td>
                            <td class="text-end">{{ F::money($fabric['actual_cost']) }}</td>
                            <td class="text-end">{{ $fabric['material_waste_qty'] !== null ? F::quantity($fabric['material_waste_qty']).' — '.F::money($fabric['material_waste_cost']) : '—' }}</td>
                            <td class="fs-8">{{ $fabric['models'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا يوجد استهلاك أقمشة مسجل ضمن الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
