@extends('layouts.app')

@section('title', 'تحليل قنوات البيع')
@section('page-title', 'قنوات البيع')

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="تحليل قنوات البيع" icon="fa-store" :period="$period" basis="تاريخ طلب العميل" exportable
        subtitle="لا يشمل هذا التحليل تكاليف الإعلانات أو التسويق." :filters="['الفترة' => $period->label()]" />
    <x-report.period-filter :period="$period" />
    <x-report.table :table="$table" caption="تحليل قنوات البيع" />
    @can('reports.profitability')
        <x-report.boundary />
    @endcan
</div>
@endsection
