@extends('layouts.app')

@section('title', 'الهدر وإعادة العمل والجودة')
@section('page-title', 'الهدر والجودة')

@php
    use App\Domain\Reports\ReportFormat as F;
    $tabs = ['waste' => 'تحليل الهدر', 'rework' => 'تحليل إعادة العمل', 'quality' => 'تحليل الجودة'];
    $basis = ['waste' => 'تاريخ تسجيل الهدر', 'rework' => 'تاريخ إنشاء إعادة العمل', 'quality' => 'تاريخ تسجيل الحادثة'][$tab];
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="$tabs[$tab]" icon="fa-recycle" :period="$period" :basis="$basis" :exportable="$tab !== 'quality'"
        subtitle="الهدر ومواد إعادة العمل جزء من تكلفة المواد الفعلية ويُعرضان تحليلياً فقط. قسم الاكتشاف ≠ القسم المسؤول. لا تُحتسب تكلفة عمالة لإعادة العمل."
        :filters="['الفترة' => $period->label()]" />

    <nav class="segment-tabs report-no-print" aria-label="نوع التحليل">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('reports.production.quality', $period->toQuery() + ['tab' => $key]) }}" class="segment-tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'waste')
        <x-report.period-filter :period="$period" :keep="['tab' => 'waste', 'group' => $groupBy]">
            <div class="col-6 col-lg-2">
                <label for="f-reason" class="form-label fs-7">السبب</label>
                <select id="f-reason" name="waste_reason_id" class="form-select"><option value="">الكل</option>
                    @foreach ($reasons as $reason)<option value="{{ $reason->id }}" @selected(request('waste_reason_id') == $reason->id)>{{ $reason->name_ar }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-dept" class="form-label fs-7">القسم المسؤول</label>
                <select id="f-dept" name="department_id" class="form-select"><option value="">الكل</option>
                    @foreach ($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name_ar }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-cat" class="form-label fs-7">فئة المادة</label>
                <select id="f-cat" name="material_category_id" class="form-select"><option value="">الكل</option>
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(request('material_category_id') == $category->id)>{{ $category->name_ar }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-model" class="form-label fs-7">الموديل</label>
                <select id="f-model" name="product_model_id" class="form-select"><option value="">الكل</option>
                    @foreach ($models as $model)<option value="{{ $model->id }}" @selected(request('product_model_id') == $model->id)>{{ $model->name_ar }}</option>@endforeach
                </select>
            </div>
        </x-report.period-filter>

        <div class="report-kpis">
            @if ($showCost)<x-report.kpi label="إجمالي تكلفة الهدر (تحليلية)" :value="F::money($grouped->sum('waste_cost'))" hint="ضمن تكلفة المواد الفعلية — لا تُضاف مرة أخرى" />@endif
            <x-report.kpi label="سجلات الهدر" :value="number_format($grouped->sum('records'))" />
            <x-report.kpi :label="'الأعلى '.(\App\Domain\Reports\QualityAnalyticsService::WASTE_GROUPS[$groupBy] ?? '')" :value="$grouped->first()['label'] ?? null" :hint="$showCost ? 'الترتيب حسب تكلفة الهدر' : 'الترتيب الحالي للتجميع'" />
        </div>
        <section class="card-factory p-3" aria-labelledby="waste-grouped">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 id="waste-grouped" class="h6 fw-bold mb-0">تجميع الهدر</h2>
                <nav class="segment-tabs report-no-print" aria-label="تجميع الهدر">
                    @foreach (\App\Domain\Reports\QualityAnalyticsService::WASTE_GROUPS as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['group' => $key, 'page' => null]) }}" class="segment-tab {{ $groupBy === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @php $maxWaste = max(0.01, (float) $grouped->max(fn ($row) => $showCost ? $row['waste_cost'] : $row['records'])); @endphp
            <div class="report-bars">
                @forelse ($grouped as $row)
                    @php $value = $showCost ? $row['waste_cost'] : $row['records']; @endphp
                    <div class="report-bar">
                        <span class="report-bar-label">{{ $row['label'] }}</span>
                        <span class="report-bar-track"><span class="report-bar-fill is-negative" style="width: {{ round($value / $maxWaste * 100, 1) }}%"></span></span>
                        <span class="report-bar-value">
                            {{ $showCost ? F::money($row['waste_cost']) : $row['records'].' سجل' }}
                            @if ($row['quantity'] !== null)<span class="text-muted fw-normal">— {{ F::quantity($row['quantity']) }} {{ $row['unit'] }}</span>@endif
                        </span>
                    </div>
                @empty
                    <p class="text-muted mb-0">لا يوجد هدر مسجل ضمن الفترة والفلاتر.</p>
                @endforelse
            </div>
        </section>

        <x-report.table :table="$table" caption="سجلات الهدر" />
    @elseif ($tab === 'rework')
        <x-report.period-filter :period="$period" :keep="['tab' => 'rework']">
            <div class="col-6 col-lg-2">
                <label for="f-status" class="form-label fs-7">الحالة</label>
                <select id="f-status" name="status" class="form-select">
                    <option value="">الكل</option>
                    <option value="open" @selected(request('status') === 'open')>مفتوحة</option>
                    <option value="completed" @selected(request('status') === 'completed')>مكتملة</option>
                </select>
            </div>
        </x-report.period-filter>
        <div class="report-kpis">
            <x-report.kpi label="حالات إعادة العمل" :value="number_format($totals['rework_count'])" icon="fa-rotate" tone="warning" />
            <x-report.kpi label="الكمية المتأثرة" :value="number_format($totals['affected_qty'])" icon="fa-cubes" tone="secondary" />
            @if ($showCost)
                <x-report.kpi label="مواد إعادة العمل الإضافية" :value="F::money($totals['rework_material_cost'])" icon="fa-coins" tone="danger" hint="على مستوى أوامر الإنتاج — ضمن التكلفة الفعلية" />
            @endif
        </div>
        <x-report.table :table="$table" caption="إعادة العمل" />
    @else
        <x-report.period-filter :period="$period" :keep="['tab' => 'quality']" />
        <div class="report-kpis">
            <x-report.kpi label="حوادث الجودة بالفترة" :value="number_format($summary['total'])" icon="fa-triangle-exclamation" tone="warning" />
            <x-report.kpi label="مفتوحة / مغلقة" :value="$summary['open'].' / '.$summary['closed']" icon="fa-folder-open" tone="secondary" />
            <x-report.kpi label="تتطلب إعادة عمل/إصلاح" :value="number_format($summary['rework_required'])" icon="fa-rotate" tone="danger" />
            <x-report.kpi label="الكمية المتأثرة" :value="number_format($summary['affected'])" icon="fa-cubes" tone="secondary" />
            <x-report.kpi label="متوسط أيام المعالجة" :value="$summary['avg_resolution_days'] !== null ? F::quantity($summary['avg_resolution_days']).' يوم' : null" icon="fa-stopwatch" tone="info" :hint="$summary['resolved_sample'] ? 'من '.$summary['resolved_sample'].' حادثة مغلقة' : 'لا توجد حوادث مغلقة كافية'" />
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <section class="card-factory p-3 h-100" aria-labelledby="q-dept">
                    <h2 id="q-dept" class="h6 fw-bold">حسب القسم — الاكتشاف مقابل المسؤولية</h2>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>القسم</th><th class="text-end">اكتشف</th><th class="text-end">مسؤول عن</th></tr></thead>
                        <tbody>
                            @forelse ($summary['by_department'] as $row)
                                <tr><td>{{ $row['department'] }}</td><td class="text-end">{{ $row['detected'] }}</td><td class="text-end fw-bold">{{ $row['responsible'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">لا توجد حوادث بالفترة.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="card-factory p-3 h-100" aria-labelledby="q-sev">
                    <h2 id="q-sev" class="h6 fw-bold">حسب الخطورة ونوع الحادثة</h2>
                    <table class="table table-sm align-middle mb-3">
                        <thead><tr><th>الخطورة</th><th class="text-end">الحوادث</th><th class="text-end">الكمية</th></tr></thead>
                        <tbody>@foreach ($summary['by_severity'] as $row)<tr><td>{{ $row['label'] }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end">{{ $row['quantity'] }}</td></tr>@endforeach</tbody>
                    </table>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>النوع</th><th class="text-end">الحوادث</th><th class="text-end">الكمية</th></tr></thead>
                        <tbody>@foreach ($summary['by_type'] as $row)<tr><td class="ltr-isolate">{{ $row['label'] }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end">{{ $row['quantity'] }}</td></tr>@endforeach</tbody>
                    </table>
                </section>
            </div>
        </div>
    @endif
</div>
@endsection
