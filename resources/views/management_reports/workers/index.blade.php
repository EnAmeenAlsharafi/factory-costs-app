@extends('layouts.app')

@section('title', 'أداء الموظفين — '.$month->translatedFormat('F Y'))
@section('page-title', 'أداء الموظفين')

@section('content')
@php
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    $maxPoints = max(1, (float) $report['rows']->max('points'));
    $maxPieces = max(1, (float) $report['rows']->max('pieces'));
    $query = array_filter(['month' => $month->format('Y-m'), 'department_id' => $departmentId]);
    $leader = $report['rows']->firstWhere('pieces', '>', 0);
@endphp
<div class="report-workspace d-flex flex-column gap-3">
    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <nav aria-label="مسار التنقل" class="small mb-1"><a href="{{ route('reports.index') }}" class="text-decoration-none">مركز التقارير</a> / الموظفون</nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-users-gear text-primary fs-5" aria-hidden="true"></i> أداء الموظفين ونقاط المكافآت</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">إنجاز كل موظف خلال الشهر من سجل الإنتاج، ونقاطه المكتسبة لاحتساب المكافأة.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('reports.workers.export', $query) }}" class="btn btn-outline-success"><i class="fas fa-file-csv me-1" aria-hidden="true"></i> كشف النقاط (Excel)</a>
            @can('production.manage_routing')
                <a href="{{ route('production.points.index') }}" class="btn btn-outline-secondary"><i class="fas fa-sliders me-1" aria-hidden="true"></i> إعدادات النقاط</a>
            @endcan
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm" aria-label="تصفية التقرير">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label small mb-1" for="month">الشهر</label><input type="month" class="form-control" id="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}"></div>
            <div style="min-width: 200px;"><label class="form-label small mb-1" for="department_id">القسم</label>
                <select class="form-select" id="department_id" name="department_id">
                    @if ($departments->count() > 1)<option value="">كل الأقسام</option>@endif
                    @foreach ($departments as $department)<option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name_ar }}{{ $department->awards_points ? ' ★' : '' }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary"><i class="fas fa-filter me-1" aria-hidden="true"></i> عرض</button>
            <span class="small text-muted ms-md-auto">★ قسم تُحتسب فيه النقاط</span>
        </div>
    </form>

    <div class="report-kpis">
        <x-report.kpi label="مجموع النقاط" :value="$number($report['totals']['points'])" hint="لكل الموظفين خلال الشهر" />
        <x-report.kpi label="القطع المنجزة" :value="$number($report['totals']['pieces'])" hint="مجموع الكميات المسجلة" />
        <x-report.kpi label="موظفون أنجزوا" :value="$report['totals']['active_workers'].' / '.$report['totals']['workers']" hint="من موظفي الأقسام الإنتاجية" />
        <x-report.kpi label="متوسط النقاط للموظف" :value="$number($report['totals']['average_points'])" :hint="$leader ? 'الأعلى: '.$leader['worker']->name : null" />
    </div>

    @if ($report['unclassified_pieces'] > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0" role="status">
            <span><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i> <strong><bdi>{{ $number($report['unclassified_pieces']) }}</bdi> قطعة</strong> أُنجزت في أقسام النقاط دون تصنيف، فلم تُحتسب نقاطها.</span>
            @can('production.manage_routing')<a href="{{ route('production.points.index') }}#unclassified-heading" class="btn btn-sm btn-warning">صنّف الموديلات واحتسب</a>@endcan
        </div>
    @endif

    <section class="table-factory-wrapper" aria-labelledby="workers-table">
        <h2 id="workers-table" class="report-section-title p-3 mb-0"><i class="fas fa-ranking-star" aria-hidden="true"></i> ترتيب الموظفين — {{ $month->translatedFormat('F Y') }}</h2>
        @if ($report['rows']->isEmpty())
            <x-report.empty-state message="لا يوجد موظفون أو إنجاز في هذا الشهر." hint="تأكد من ربط الموظفين بأقسامهم، أو اختر شهراً آخر." class="m-3" />
        @else
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead><tr>
                        <th scope="col" class="text-center">#</th><th scope="col">الموظف</th>
                        <th scope="col" style="min-width: 180px;">النقاط</th><th scope="col" style="min-width: 160px;">القطع المنجزة</th>
                        <th scope="col" class="text-end">أيام العمل</th><th scope="col" class="text-end">قطع / يوم</th><th scope="col" class="text-end">مراحل مكتملة</th>
                        <th scope="col" class="text-end">مفتوحة الآن</th><th scope="col"><span class="visually-hidden">التفاصيل</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr @class(['text-muted' => $row['pieces'] == 0])>
                                <td class="text-center fw-bold">{{ $row['pieces'] > 0 ? $loop->iteration : '—' }}</td>
                                <td><div class="fw-semibold text-dark">{{ $row['worker']->name }}</div><div class="small text-muted">{{ $row['department'] }}</div></td>
                                <td>
                                    <div class="worker-meter"><span class="worker-meter-fill is-points" style="width: {{ round($row['points'] / $maxPoints * 100) }}%"></span></div>
                                    <span class="fw-bold text-dark"><bdi>{{ $number($row['points']) }}</bdi></span> <span class="small text-muted">نقطة</span>
                                    @if ($row['unclassified_pieces'] > 0)<span class="small text-warning-emphasis d-block">+<bdi>{{ $number($row['unclassified_pieces']) }}</bdi> قطعة بلا تصنيف</span>@endif
                                </td>
                                <td>
                                    <div class="worker-meter"><span class="worker-meter-fill" style="width: {{ round(max(0, $row['pieces']) / $maxPieces * 100) }}%"></span></div>
                                    <bdi>{{ $number($row['pieces']) }}</bdi>
                                </td>
                                <td class="text-end">{{ $row['active_days'] }}</td>
                                <td class="text-end">{{ $number($row['per_day']) }}</td>
                                <td class="text-end">{{ $row['operations_finished'] }}</td>
                                <td class="text-end">{{ $row['open_tasks'] }}@if ($row['blocked_tasks'] > 0) <span class="status-chip status-chip-danger ms-1">{{ $row['blocked_tasks'] }} متوقفة</span>@endif</td>
                                <td class="text-end"><a href="{{ route('reports.workers.show', ['worker' => $row['worker'], 'month' => $month->format('Y-m')]) }}" class="btn btn-sm btn-outline-primary text-nowrap">التفاصيل</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="report-note px-3 py-2 mb-0">النقاط = الكمية المنجزة × نقاط تصنيف السرير وقت التسجيل، في الأقسام المفعّل فيها احتساب النقاط. تصحيحات الكميات تُخصم أو تُضاف لنفس الموظف. «مفتوحة الآن» = مهام مسندة لم تكتمل.</p>
    </section>
</div>
@endsection
