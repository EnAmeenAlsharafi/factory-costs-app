@extends('layouts.app')

@section('title', 'ربحية الموديلات والمقاسات')
@section('page-title', 'ربحية الموديلات')

@php
    use App\Domain\Reports\ReportFormat as F;
    $sorts = ['value' => 'أعلى قيمة مبيعات', 'quantity' => 'أعلى كمية', 'contribution' => 'أعلى مساهمة (إجمالي)', 'contribution_pct' => 'أعلى هامش مساهمة %', 'cost' => 'أعلى تكلفة مواد فعلية', 'rework' => 'أعلى تكلفة مواد إعادة عمل', 'waste' => 'أعلى هدر'];
    if (! $canSeeContribution) {
        $sorts = array_intersect_key($sorts, array_flip(['value', 'quantity']));
    }
    $metric = match ($sort) {
        'quantity' => ['quantity', fn ($v) => F::quantity($v)],
        'contribution' => ['contribution', fn ($v) => F::money($v)],
        'contribution_pct' => ['contribution_pct', fn ($v) => F::percent($v)],
        'cost' => ['actual_cost_to_date', fn ($v) => F::money($v)],
        'rework' => ['rework_cost', fn ($v) => F::money($v)],
        'waste' => ['waste_cost', fn ($v) => F::money($v)],
        default => ['commercial_value', fn ($v) => F::money($v)],
    };
    $maxAbs = max(0.0001, (float) $chart->max(fn ($row) => abs((float) ($row[$metric[0]] ?? 0))));
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header :title="$group === 'model' ? 'ربحية الموديلات' : 'ربحية التكوينات (الموديل × المقاس × السحارة)'" icon="fa-bed" :period="$period" basis="تاريخ طلب العميل" exportable
        subtitle="التحليل حسب الموديل الداخلي (أسماء العملاء البديلة لا تُجزئ التحليل). التصاميم الخاصة فئة مستقلة. المساهمة للبنود المكتملة تكلفةً فقط."
        :filters="['الفترة' => $period->label(), 'التجميع' => $group === 'model' ? 'الموديل' : 'التكوين']" />

    <x-report.period-filter :period="$period" :keep="['group' => $group]">
        <div class="col-6 col-lg-2">
            <label for="f-sort" class="form-label fs-7">الترتيب</label>
            <select id="f-sort" name="sort" class="form-select">
                @foreach ($sorts as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
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
            <label for="f-storage" class="form-label fs-7">السحارة</label>
            <select id="f-storage" name="has_storage" class="form-select">
                <option value="">الكل</option>
                <option value="1" @selected(request('has_storage') === '1')>بسحارة</option>
                <option value="0" @selected(request('has_storage') === '0')>بدون سحارة</option>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="f-channel" class="form-label fs-7">قناة البيع</label>
            <select id="f-channel" name="sales_channel_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}" @selected(request('sales_channel_id') == $channel->id)>{{ $channel->name_ar }}</option>
                @endforeach
            </select>
        </div>
    </x-report.period-filter>

    <nav class="segment-tabs report-no-print" aria-label="مستوى التجميع">
        <a href="{{ route('reports.profitability.products', array_merge(request()->except(['group', 'page']), ['group' => 'model'])) }}" class="segment-tab {{ $group === 'model' ? 'active' : '' }}">حسب الموديل</a>
        <a href="{{ route('reports.profitability.products', array_merge(request()->except(['group', 'page']), ['group' => 'configuration'])) }}" class="segment-tab {{ $group === 'configuration' ? 'active' : '' }}">حسب التكوين (مقاس + سحارة)</a>
    </nav>

    @if ($chart->isNotEmpty())
        <section class="card-factory p-3" aria-labelledby="products-chart">
            <h2 id="products-chart" class="h6 fw-bold mb-3">الترتيب: {{ $sorts[$sort] ?? $sorts['value'] }} (أعلى 10 في الصفحة)</h2>
            <div class="report-bars">
                @foreach ($chart as $row)
                    @php $value = $row[$metric[0]] ?? null; @endphp
                    <div class="report-bar">
                        <span class="report-bar-label">{{ is_array($row['product']) ? $row['product']['text'] : $row['product'] }}</span>
                        <span class="report-bar-track"><span @class(['report-bar-fill', 'is-negative' => is_numeric($value) && $value < 0, 'tone-muted' => $value === null]) style="width: {{ $value === null ? 0 : round(abs((float) $value) / $maxAbs * 100, 1) }}%"></span></span>
                        <span class="report-bar-value">{{ $value === null ? '—' : $metric[1]($value) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <x-report.table :table="$table" caption="ربحية الموديلات" :primary="['product', 'quantity', 'commercial_value', 'actual_cost_to_date', 'contribution', 'contribution_pct']" />
    @if ($canSeeContribution)
        <x-report.boundary />
    @endif
</div>
@endsection
