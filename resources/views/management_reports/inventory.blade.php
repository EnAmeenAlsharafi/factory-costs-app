@extends('layouts.app')

@section('title', $title)
@section('page-title', 'تقارير المخزون')

@php use App\Domain\Reports\ReportFormat as F; @endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="$title" icon="fa-boxes-stacked" basis="الوضع الحالي لدفعات المخزون النشطة" exportable
        subtitle="الأرصدة من دفعات المخزون النشطة (المتبقي). القيمة = المتبقي × تكلفة الدفعة نفسها — قيمة تشغيلية وليست تقييماً محاسبياً. الوارد بأوامر الشراء لا يُحسب ضمن الرصيد الفعلي." />

    <nav class="segment-tabs report-no-print" aria-label="أقسام تقرير المخزون">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('reports.inventory', ['tab' => $key]) }}" class="segment-tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <x-report.period-filter :period="null" :showPeriod="false" :keep="['tab' => $tab]">
        @if (in_array($tab, ['stock', 'valuation'], true))
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="f-search" class="form-label fs-7">بحث</label>
                <input type="search" id="f-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="اسم المادة أو الكود" inputmode="search">
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-cat" class="form-label fs-7">الفئة</label>
                <select id="f-cat" name="material_category_id" class="form-select"><option value="">الكل</option>
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(request('material_category_id') == $category->id)>{{ $category->name_ar }}</option>@endforeach
                </select>
            </div>
            @if ($tab === 'stock')
                <div class="col-6 col-lg-2">
                    <label for="f-state" class="form-label fs-7">الحالة</label>
                    <select id="f-state" name="state" class="form-select">
                        <option value="">الكل</option>
                        <option value="below_reorder" @selected(request('state') === 'below_reorder')>عند/دون حد إعادة الطلب</option>
                        <option value="below_minimum" @selected(request('state') === 'below_minimum')>دون الحد الأدنى</option>
                    </select>
                </div>
            @endif
        @elseif ($tab === 'fabric')
            <div class="col-6 col-lg-3">
                <label for="f-fabric" class="form-label fs-7">القماش</label>
                <select id="f-fabric" name="material_id" class="form-select"><option value="">الكل</option>
                    @foreach ($fabricMaterials as $material)<option value="{{ $material->id }}" @selected(request('material_id') == $material->id)>{{ $material->name_ar }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-supplier" class="form-label fs-7">المورد</label>
                <select id="f-supplier" name="supplier_id" class="form-select"><option value="">الكل</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="f-color" class="form-label fs-7">اللون</label>
                <input type="search" id="f-color" name="color" class="form-control" value="{{ request('color') }}" placeholder="مثال: 204" inputmode="search">
            </div>
        @elseif ($tab === 'slow')
            <div class="col-6 col-lg-2">
                <label for="f-days" class="form-label fs-7">بدون صرف منذ</label>
                <select id="f-days" name="days" class="form-select">
                    @foreach ([30, 60, 90] as $days)<option value="{{ $days }}" @selected($threshold === $days)>{{ $days }} يوماً</option>@endforeach
                </select>
            </div>
        @endif
    </x-report.period-filter>

    <div class="report-kpis">
        <x-report.kpi label="سجلات المخزون المطابقة" :value="number_format($table->paginator?->total() ?? count($table->rows))" hint="حسب التبويب والفلاتر الحالية" />
        <x-report.kpi label="المعروض في هذه الصفحة" :value="number_format(count($table->rows))" hint="تفاصيل المواد والدفعات أدناه" />
    </div>
    @if ($tab === 'valuation')
        <div class="report-kpis">
            <x-report.kpi label="القيمة التشغيلية الإجمالية للمخزون" :value="F::money($totalValue)" icon="fa-coins" tone="primary" hint="جميع المواد قبل التصفية — ليست تقييماً محاسبياً" />
        </div>
    @endif

    @if ($tab === 'fabric' && $fabricSummary->isNotEmpty())
        <section class="card-factory p-3" aria-labelledby="fabric-summary">
            <h2 id="fabric-summary" class="h6 fw-bold">المتاح حسب القماش والمورد واللون</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>القماش</th><th>المورد</th><th>اللون</th><th class="text-end">المتاح</th><th class="text-end">الدفعات</th></tr></thead>
                    <tbody>
                        @foreach ($fabricSummary as $row)
                            <tr><td>{{ $row->fabric }}</td><td>{{ $row->supplier ?? 'غير محدد' }}</td><td class="ltr-isolate">{{ $row->color_code ?? '—' }}</td>
                                <td class="text-end fw-bold">{{ F::quantity($row->available_qty) }} {{ $row->unit }}</td><td class="text-end">{{ $row->lot_count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($tab === 'slow')
        <p class="report-note mb-0">مواد برصيد موجب لم يُصرف منها شيء خلال {{ $threshold }} يوماً. "بطيئة الحركة" لا تعني أنها متقادمة أو تالفة.</p>
    @endif

    <x-report.table :table="$table" :caption="$title" :primary="$tab === 'fabric' ? ['fabric', 'supplier', 'color_code', 'available_qty', 'unit', 'lot'] : ($tab === 'stock' ? ['material', 'category', 'stock_qty', 'unit', 'incoming_qty', 'state'] : [])" />
</div>
@endsection
