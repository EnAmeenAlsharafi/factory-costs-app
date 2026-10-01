@extends('layouts.app')

@section('title', 'تحليل العملاء')
@section('page-title', 'تحليل العملاء')

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="تحليل العملاء" icon="fa-users" :period="$period" basis="تاريخ طلب العميل (القيمة والمساهمة) — الأرصدة بالوضع الحالي" exportable
        subtitle="الأعمدة الحساسة (التكلفة، المساهمة، الأرصدة والائتمان) تظهر حسب الصلاحية فقط." :filters="['الفترة' => $period->label()]" />

    <x-report.period-filter :period="$period">
        <div class="col-6 col-lg-2">
            <label for="f-type" class="form-label fs-7">نوع العميل</label>
            <select id="f-type" name="customer_type_id" class="form-select">
                <option value="">الكل</option>
                @foreach ($customerTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('customer_type_id') == $type->id)>{{ $type->name_ar }}</option>
                @endforeach
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

    <x-report.table :table="$table" caption="تحليل العملاء" :primary="['customer', 'order_count', 'commercial_value', 'contribution', 'outstanding', 'overdue']" mobile-cards />
    @can('reports.profitability')
        <x-report.boundary />
    @endcan
</div>
@endsection
