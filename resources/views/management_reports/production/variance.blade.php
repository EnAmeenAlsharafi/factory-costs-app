@extends('layouts.app')

@section('title', 'انحراف تكلفة الإنتاج')
@section('page-title', 'انحراف تكلفة الإنتاج')

@php
    $money = fn ($value) => $value === null ? null : \App\Domain\Reports\ReportFormat::money($value);
    $basis = $filters['scope'] === 'completed' ? 'تاريخ اكتمال أمر الإنتاج' : 'تاريخ إطلاق أمر الإنتاج';
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="انحراف تكلفة الإنتاج" icon="fa-scale-unbalanced" :period="$period" :basis="$basis" exportable
        subtitle="التكلفة المخططة = كميات الوصفة المخططة × التكلفة المرجعية (متوسط تكلفة اللوتات المتاحة). الفعلية = مصروف مرحّل − مرتجع قابل للاستخدام. كل أمر إنتاج بكميته المُطلقة الخاصة."
        :filters="['الفترة' => $period->label()]" />

    <x-report.period-filter :period="$period">
        <div class="col-6 col-lg-2">
            <label for="f-scope" class="form-label fs-7">أوامر الإنتاج</label>
            <select id="f-scope" name="scope" class="form-select">
                <option value="completed" @selected($filters['scope'] === 'completed')>المكتملة</option>
                <option value="active" @selected($filters['scope'] === 'active')>النشطة (تكلفة حتى تاريخه)</option>
                <option value="all" @selected($filters['scope'] === 'all')>الكل</option>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-model" class="form-label fs-7">الموديل</label>
            <select id="f-model" name="product_model_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($models as $model)
                    <option value="{{ $model->id }}" @selected(request('product_model_id') == $model->id)>{{ $model->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-sort" class="form-label fs-7">الترتيب</label>
            <select id="f-sort" name="sort" class="form-select">
                <option value="variance" @selected(request('sort', 'variance') === 'variance')>الأعلى انحرافاً</option>
                <option value="date" @selected(request('sort') === 'date')>الأحدث</option>
            </select>
        </div>
    </x-report.period-filter>

    <div class="report-kpis">
        <x-report.kpi label="أوامر إنتاج في التقرير" :value="number_format($totals['po_count'])" icon="fa-industry" tone="info" />
        <x-report.kpi label="التكلفة المخططة (المكتملة)" :value="$money($totals['planned_cost'])" icon="fa-clipboard-list" tone="secondary" />
        <x-report.kpi label="تكلفة المواد الفعلية (المكتملة)" :value="$money($totals['actual_cost'])" icon="fa-boxes-packing" tone="primary" />
        <x-report.kpi label="إجمالي الانحراف" :value="$money($totals['cost_variance']).($totals['variance_pct'] !== null ? ' ('.\App\Domain\Reports\ReportFormat::percent($totals['variance_pct']).')' : '')" icon="fa-arrow-trend-up" tone="warning" :alert="$totals['cost_variance'] > 0" hint="موجب = استهلاك أعلى من المخطط" />
        <x-report.kpi label="أوامر تجاوزت التكلفة المخططة" :value="number_format($totals['over_count'])" icon="fa-triangle-exclamation" tone="danger" :alert="$totals['over_count'] > 0" />
    </div>

    <x-report.table :table="$table" caption="انحراف تكلفة الإنتاج" />
    <p class="report-note mb-0">الهدر وإعادة العمل معروضان كجزء تحليلي من التكلفة الفعلية ولا يُضافان إليها. الأوامر النشطة تُعرض بتكلفتها حتى تاريخه دون انحراف نهائي.</p>
</div>
@endsection
