@extends('layouts.app')

@section('title', 'دقة وصفات التصنيع')
@section('page-title', 'دقة الوصفات')

@php
    $qty = fn ($value) => $value === null ? '—' : \App\Domain\Reports\ReportFormat::quantity($value);
    $money = fn ($value) => $value === null ? '—' : \App\Domain\Reports\ReportFormat::money($value);
@endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="دقة وصفات التصنيع" icon="fa-scroll" :period="$period" basis="تاريخ اكتمال أمر الإنتاج" exportable
        subtitle="الكمية المخططة من الوصفة مقابل المستهلكة فعلياً (مصروف − مرتجع) في الأوامر المكتملة. للقراءة فقط — لا يتم تعديل أي وصفة تلقائياً."
        :filters="['الفترة' => $period->label()]" />
    <x-report.period-filter :period="$period" />
    <x-report.table :table="$table" caption="دقة وصفات التصنيع" />

    <section class="card-factory p-3" aria-labelledby="recipe-compare">
        <h2 id="recipe-compare" class="report-section-title"><i class="fas fa-code-compare" aria-hidden="true"></i> مقارنة نسخ الوصفة (للقراءة فقط)</h2>
        <form method="GET" class="row g-2 align-items-end report-no-print" x-data="{ recipe: @js((string) request('recipe_id')), versions: @js($recipes->mapWithKeys(fn ($r) => [$r->id => $r->versions->map(fn ($v) => ['id' => $v->id, 'label' => 'V'.$v->version_number.' — '.$v->status])])) }">
            @foreach ($period->toQuery() as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <div class="col-12 col-md-4">
                <label for="cmp-recipe" class="form-label fs-7">الوصفة</label>
                <select id="cmp-recipe" name="recipe_id" class="form-select" x-model="recipe">
                    <option value="">اختر الوصفة</option>
                    @foreach ($recipes as $recipe)
                        <option value="{{ $recipe->id }}">{{ $recipe->name }} ({{ $recipe->versions->count() }} نسخ)</option>
                    @endforeach
                </select>
            </div>
            @foreach (['version_a' => 'النسخة الأولى', 'version_b' => 'النسخة الثانية'] as $field => $label)
                <div class="col-6 col-md-3">
                    <label for="cmp-{{ $field }}" class="form-label fs-7">{{ $label }}</label>
                    <select id="cmp-{{ $field }}" name="{{ $field }}" class="form-select">
                        <template x-for="version in (versions[recipe] || [])" :key="version.id">
                            <option :value="version.id" x-text="version.label" :selected="String(version.id) === @js((string) request($field))"></option>
                        </template>
                    </select>
                </div>
            @endforeach
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-factory-primary w-100">مقارنة</button></div>
        </form>

        @if ($comparison)
            <div class="table-responsive mt-3">
                <table class="table table-factory align-middle mb-0">
                    <thead><tr>
                        <th>المادة</th><th>الوحدة</th>
                        <th class="text-end">V{{ $comparison['a']->version_number }} كمية</th><th class="text-end">هدر %</th>
                        <th class="text-end">V{{ $comparison['b']->version_number }} كمية</th><th class="text-end">هدر %</th>
                        <th>التغيير</th>
                        @if ($showCost)<th class="text-end">تكلفة مرجعية V{{ $comparison['a']->version_number }}</th><th class="text-end">تكلفة مرجعية V{{ $comparison['b']->version_number }}</th>@endif
                    </tr></thead>
                    <tbody>
                        @foreach ($comparison['rows'] as $row)
                            <tr @class(['report-row-alert' => $row['change'] !== 'بدون تغيير'])>
                                <td>{{ $row['material'] }}</td><td>{{ $row['unit'] ?? '—' }}</td>
                                <td class="text-end">{{ $qty($row['qty_a']) }}</td><td class="text-end">{{ $qty($row['waste_a']) }}</td>
                                <td class="text-end">{{ $qty($row['qty_b']) }}</td><td class="text-end">{{ $qty($row['waste_b']) }}</td>
                                <td>{{ $row['change'] }}</td>
                                @if ($showCost)<td class="text-end">{{ $money($row['planned_cost_a']) }}</td><td class="text-end">{{ $money($row['planned_cost_b']) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($showCost)
                        <tfoot><tr>
                            <td colspan="7" class="text-end fw-bold">إجمالي التكلفة المرجعية للوحدة</td>
                            <td class="text-end fw-bold">{{ $money($comparison['rows']->sum('planned_cost_a')) }}</td>
                            <td class="text-end fw-bold">{{ $money($comparison['rows']->sum('planned_cost_b')) }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
            <p class="report-note mt-2 mb-0">التكلفة المرجعية = الكمية × (1 + نسبة الهدر) × متوسط تكلفة اللوتات المتاحة حالياً — للمقارنة فقط.</p>
        @endif
    </section>
</div>
@endsection
