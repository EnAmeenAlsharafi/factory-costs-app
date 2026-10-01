@extends('layouts.app')

@section('title', 'أداء الأقسام والاختناقات')
@section('page-title', 'أداء الأقسام')

@php use App\Domain\Reports\ReportFormat as F; @endphp

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="أداء الأقسام والاختناقات" icon="fa-diagram-project" :period="$period" basis="تاريخ اكتمال العملية / تسجيل الحادثة"
        subtitle="مؤشرات تشغيلية للأقسام فقط — لا توجد تقييمات أو ترتيب للموظفين. لا يُحسب متوسط زمن الانتظار لعدم تسجيل وقت دخول الطابور."
        :filters="['الفترة' => $period->label()]" />
    <x-report.period-filter :period="$period" />

    <section class="table-factory-wrapper" aria-labelledby="dept-table">
        <h2 id="dept-table" class="report-section-title p-3 mb-0"><i class="fas fa-building" aria-hidden="true"></i> الأقسام الإنتاجية</h2>
        <div class="table-responsive">
            <table class="table table-factory table-sticky-first align-middle mb-0">
                <thead><tr>
                    <th>القسم</th><th class="text-end">عمليات مفتوحة (الآن)</th><th class="text-end">الكمية بالطابور</th>
                    <th class="text-end">عمليات أُنجزت بالفترة</th><th class="text-end">الكمية المنجزة بالفترة</th>
                    <th class="text-end">إعادة عمل مسندة مفتوحة</th><th class="text-end">حوادث اكتشفها</th><th class="text-end">حوادث مسؤول عنها</th>
                </tr></thead>
                <tbody>
                    @forelse ($departments as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['department'] }}</td>
                            <td class="text-end">{{ $row['open_ops'] }}</td><td class="text-end">{{ F::quantity($row['open_qty']) }}</td>
                            <td class="text-end">{{ $row['completed_ops'] }}</td><td class="text-end">{{ F::quantity($row['processed_qty']) }}</td>
                            <td class="text-end">{{ $row['open_rework'] }}</td><td class="text-end">{{ $row['incidents_detected'] }}</td><td class="text-end fw-bold">{{ $row['incidents_responsible'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">لا توجد أقسام إنتاجية نشطة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-factory-wrapper" aria-labelledby="bottleneck-table">
        <h2 id="bottleneck-table" class="report-section-title p-3 mb-0"><i class="fas fa-traffic-light" aria-hidden="true"></i> طوابير مراكز العمل (الوضع الحالي)</h2>
        <div class="table-responsive">
            <table class="table table-factory align-middle mb-0">
                <thead><tr><th>مركز العمل</th><th>القسم</th><th class="text-end">عمليات بالطابور</th><th class="text-end">الكمية المتبقية</th><th class="text-end">أوامر إنتاج</th><th>أقدم عملية مفتوحة منذ</th><th class="text-end">أيام</th></tr></thead>
                <tbody>
                    @forelse ($bottlenecks as $row)
                        <tr @class(['report-row-alert' => ($row['oldest_days'] ?? 0) > 7])>
                            <td class="fw-semibold">{{ $row['work_center'] }}</td><td>{{ $row['department'] }}</td>
                            <td class="text-end">{{ $row['queue_size'] }}</td><td class="text-end">{{ F::quantity($row['queue_qty']) }}</td><td class="text-end">{{ $row['active_pos'] }}</td>
                            <td class="ltr-isolate">{{ $row['oldest_since'] ?? '—' }}</td><td class="text-end">{{ $row['oldest_days'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد عمليات مفتوحة حالياً.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="report-note px-3 py-2 mb-0">"منذ" = بدء العملية أو إنشاؤها (أيهما متاح) — تاريخ موثوق، وليس زمن انتظار محسوباً.</p>
    </section>

    <section class="card-factory p-3" aria-labelledby="lead-time">
        <h2 id="lead-time" class="report-section-title"><i class="fas fa-stopwatch" aria-hidden="true"></i> مدة الإنتاج: الإطلاق ← الاكتمال <span class="report-note fw-normal">(أوامر مكتملة بالفترة فقط)</span></h2>
        @if ($leadTimes['overall']['count'] === 0)
            <p class="text-muted mb-0">لا توجد أوامر إنتاج مكتملة بالفترة لحساب المدة.</p>
        @else
            <p class="fs-7">الإجمالي: متوسط <strong>{{ F::quantity($leadTimes['overall']['average']) }}</strong> يوم — الوسيط <strong>{{ F::quantity($leadTimes['overall']['median']) }}</strong> يوم ({{ $leadTimes['overall']['count'] }} أمر)</p>
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>الموديل</th><th class="text-end">عدد الأوامر</th><th class="text-end">المتوسط (يوم)</th><th class="text-end">الوسيط (يوم)</th></tr></thead>
                <tbody>
                    @foreach ($leadTimes['rows'] as $row)
                        <tr><td>{{ $row['model'] }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end">{{ F::quantity($row['average']) }}</td><td class="text-end">{{ F::quantity($row['median']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
