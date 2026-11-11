@extends('layouts.app')

@section('title', 'التأخير حسب القسم')
@section('page-title', 'التأخير حسب القسم')

@section('content')
@php
    $deadlineService = app(\App\Services\StageDeadlineService::class);
    $maxOverdue = max(1, (int) $report['departments']->max('overdue'));
@endphp
<div class="report-workspace d-flex flex-column gap-3">
    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <nav aria-label="مسار التنقل" class="small mb-1"><a href="{{ route('reports.index') }}" class="text-decoration-none">مركز التقارير</a> / الإنتاج</nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-hourglass-half text-primary fs-5" aria-hidden="true"></i> التأخير حسب القسم</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">في أي قسم تتأخر الطلبات، مقارنةً بمدة كل قسم بأيام العمل (الجمعة لا تُحسب، ووقت العوائق لا يُحسب على القسم).</p>
        </div>
        @can('production.manage_routing')
            <a href="{{ route('production.department-times.index') }}" class="btn btn-outline-secondary"><i class="fas fa-stopwatch me-1" aria-hidden="true"></i> مدد الأقسام</a>
        @endcan
    </div>

    <form method="GET" class="card border-0 shadow-sm" aria-label="تصفية التقرير">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label small mb-1" for="month">شهر الإنجاز</label><input type="month" class="form-control" id="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}"></div>
            <div style="min-width: 200px;"><label class="form-label small mb-1" for="department_id">القسم</label>
                <select class="form-select" id="department_id" name="department_id">
                    @if ($departments->count() > 1)<option value="">كل الأقسام</option>@endif
                    @foreach ($departments as $department)<option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name_ar }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary"><i class="fas fa-filter me-1" aria-hidden="true"></i> عرض</button>
        </div>
    </form>

    @if ($report['totals']['unconfigured'] > 0)
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0" role="status">
            <span><i class="fas fa-circle-info me-1" aria-hidden="true"></i> {{ $report['totals']['unconfigured'] }} قسم بلا مدة محددة، فلا تُتابع طلباته في هذا التقرير.</span>
            @can('production.manage_routing')<a href="{{ route('production.department-times.index') }}" class="btn btn-sm btn-outline-primary">حدد المدد</a>@endcan
        </div>
    @endif

    <div class="report-kpis">
        <x-report.kpi label="متأخرة الآن" :value="$report['totals']['overdue']" :alert="$report['totals']['overdue'] > 0" hint="مراحل تجاوزت مدة قسمها" />
        <x-report.kpi label="قاربت الموعد" :value="$report['totals']['warning']" hint="تجاوزت نسبة التنبيه" />
        <x-report.kpi label="في الأقسام الآن" :value="$report['totals']['open']" hint="مراحل لها موعد ولم تنتهِ" />
        <x-report.kpi label="الإنجاز ضمن المدة" :value="$report['totals']['on_time_rate'] !== null ? $report['totals']['on_time_rate'].'%' : null" :hint="'للمراحل المنجزة في '.$month->translatedFormat('F Y')" />
    </div>

    <section class="table-factory-wrapper" aria-labelledby="departments-table">
        <h2 id="departments-table" class="report-section-title p-3 mb-0"><i class="fas fa-building" aria-hidden="true"></i> الأقسام</h2>
        <div class="table-responsive">
            <table class="table table-factory align-middle mb-0">
                <thead><tr>
                    <th scope="col">القسم</th><th scope="col" class="text-end">المدة</th>
                    <th scope="col" style="min-width: 170px;">متأخرة الآن</th><th scope="col" class="text-end">قاربت الموعد</th><th scope="col" class="text-end">في القسم</th>
                    <th scope="col" class="text-end">أُنجزت بالشهر</th><th scope="col" class="text-end">ضمن المدة</th><th scope="col" class="text-end">متوسط المدة الفعلية</th><th scope="col" class="text-end">متوسط التأخير</th>
                </tr></thead>
                <tbody>
                    @foreach ($report['departments'] as $row)
                        <tr @class(['report-row-alert' => $row['overdue'] > 0])>
                            <th scope="row" class="fw-semibold"><a href="{{ route('reports.production.delays', ['month' => $month->format('Y-m'), 'department_id' => $row['department']->id]) }}" class="text-decoration-none">{{ $row['department']->name_ar }}</a></th>
                            <td class="text-end">{{ $row['department']->target_days ? $deadlineService->days($row['department']->target_days) : '—' }}</td>
                            <td>
                                <div class="worker-meter"><span class="worker-meter-fill" style="width: {{ round($row['overdue'] / $maxOverdue * 100) }}%; background: #b91c1c;"></span></div>
                                <strong>{{ $row['overdue'] }}</strong>
                            </td>
                            <td class="text-end">{{ $row['warning'] }}</td>
                            <td class="text-end">{{ $row['open'] }}</td>
                            <td class="text-end">{{ $row['finished'] }}</td>
                            <td class="text-end">@if ($row['on_time_rate'] !== null)<span @class(['fw-bold', 'text-success' => $row['on_time_rate'] >= 80, 'text-danger' => $row['on_time_rate'] < 60])>{{ $row['on_time_rate'] }}%</span>@else — @endif</td>
                            <td class="text-end">{{ $row['average_days'] !== null ? $row['average_days'].' يوم' : '—' }}</td>
                            <td class="text-end">{{ $row['average_delay'] !== null ? $row['average_delay'].' يوم' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="report-note px-3 py-2 mb-0">«ضمن المدة» و«متوسط المدة الفعلية» للمراحل التي أنهاها القسم خلال الشهر المختار. المدة الفعلية من وصول الطلب للقسم حتى إنهائه، بأيام العمل.</p>
    </section>

    <section class="table-factory-wrapper" aria-labelledby="late-table">
        <h2 id="late-table" class="report-section-title p-3 mb-0"><i class="fas fa-hourglass-end" aria-hidden="true"></i> الطلبات المتأخرة الآن ({{ $report['late']->count() }})</h2>
        @if ($report['late']->isEmpty())
            <x-report.empty-state message="لا توجد طلبات متأخرة الآن." hint="كل المراحل الجارية ضمن مدد أقسامها." class="m-3" />
        @else
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead><tr><th scope="col">أمر الإنتاج</th><th scope="col">العميل</th><th scope="col">القسم / المرحلة</th><th scope="col">المسؤول</th><th scope="col">وصل القسم</th><th scope="col">الموعد</th><th scope="col">التأخير</th></tr></thead>
                    <tbody>
                        @foreach ($report['late'] as $row)
                            @php $operation = $row['operation']; $productionOrder = $operation->productionOrder; @endphp
                            <tr>
                                <td><a href="{{ route('production.orders.show', $productionOrder) }}" class="fw-semibold text-decoration-none"><bdi>{{ $productionOrder->production_order_number }}</bdi></a><div class="small text-muted">{{ $productionOrder->display_name }}</div></td>
                                <td>{{ $productionOrder->customerOrder?->customer?->name ?? '—' }}</td>
                                <td>{{ $operation->workCenter?->department?->name_ar }}<div class="small text-muted">{{ $operation->operation_name_snapshot }}</div></td>
                                <td>{{ $operation->assignedUser?->name ?? 'غير مسند' }}</td>
                                <td class="text-nowrap"><bdi>{{ $operation->ready_at?->format('Y-m-d') }}</bdi></td>
                                <td class="text-nowrap"><bdi>{{ $row['deadline']['due_at']?->format('Y-m-d') }}</bdi>@if ($row['deadline']['blocked'])<span class="d-block small text-muted">مُمدد بسبب عائق</span>@endif</td>
                                <td><span class="status-chip status-chip-danger">{{ $row['deadline']['label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
