@extends('layouts.app')

@section('title', 'تاريخ أسعار المورد')
@section('page-title', 'تاريخ الأسعار')

@php use App\Domain\Reports\ReportFormat as F; @endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="تاريخ أسعار المادة لدى الموردين" icon="fa-chart-line"
        subtitle="عروض الأسعار ← أسعار أوامر الشراء ← أسعار الاستلام الفعلي، مع السعر للوحدة الأساسية للمقارنة. بيانات تاريخية فقط." />

    <x-report.period-filter :period="null" :showPeriod="false">
        <div class="col-12 col-md-5">
            <label for="f-material" class="form-label fs-7">المادة</label>
            <select id="f-material" name="material_id" class="form-select" required>
                <option value="">اختر المادة</option>
                @foreach ($materials as $material)<option value="{{ $material->id }}" @selected($materialId === $material->id)>{{ $material->name_ar }} ({{ $material->code }})</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label for="f-supplier" class="form-label fs-7">المورد</label>
            <select id="f-supplier" name="supplier_id" class="form-select"><option value="">كل الموردين</option>
                @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>@endforeach
            </select>
        </div>
    </x-report.period-filter>

    @if ($materialId)
        <div class="table-factory-wrapper">
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead><tr><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>المورد</th><th class="text-end">السعر</th><th>وحدة الشراء</th><th class="text-end">معامل التحويل</th><th class="text-end">السعر للوحدة الأساسية</th></tr></thead>
                    <tbody>
                        @forelse ($history as $row)
                            <tr>
                                <td class="ltr-isolate">{{ $row['date'] }}</td>
                                <td><span class="status-chip status-chip-{{ $row['type']['tone'] }}">{{ $row['type']['label'] }}</span></td>
                                <td class="ltr-isolate">{{ $row['reference'] }}</td><td>{{ $row['supplier'] }}</td>
                                <td class="text-end">{{ F::money($row['unit_price']) }}</td><td>{{ $row['purchase_unit'] ?? '—' }}</td>
                                <td class="text-end">{{ F::quantity($row['conversion_factor']) }}</td><td class="text-end fw-bold">{{ F::money($row['base_unit_price']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">لا توجد أسعار مسجلة لهذه المادة{{ $supplierId ? ' لدى هذا المورد' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card-factory empty-state" role="status"><p class="mb-0">اختر مادة لعرض تاريخ أسعارها.</p></div>
    @endif
</div>
@endsection
