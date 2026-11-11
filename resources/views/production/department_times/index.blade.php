@extends('layouts.app')

@section('title', 'مدد الأقسام')
@section('page-title', 'مدد الأقسام')

@section('content')
<div class="d-flex flex-column gap-3">
    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas fa-stopwatch text-primary fs-5" aria-hidden="true"></i> مدد الأقسام
            </h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">كم يوم عمل يبقى الطلب في كل قسم، لمعرفة في أي مرحلة تأخر وأي قسم يؤخر الطلبات.</p>
        </div>
        @can('reports.production')
            <a href="{{ route('reports.production.delays') }}" class="btn btn-outline-primary"><i class="fas fa-hourglass-half me-1" aria-hidden="true"></i> تقرير التأخير حسب القسم</a>
        @endcan
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card border-0 shadow-sm" aria-labelledby="how-heading">
        <div class="card-body">
            <h2 id="how-heading" class="h6 fw-bold mb-3">كيف تُحسب المدة؟</h2>
            <ol class="points-steps list-unstyled mb-0">
                <li><span class="points-step-no">1</span><div><strong>يبدأ العدّاد عند وصول الطلب للقسم</strong><p>لحظة جاهزية المرحلة، فانتظار الطلب في طابور القسم جزء من مدته.</p></div></li>
                <li><span class="points-step-no">2</span><div><strong>أيام عمل فقط</strong><p>الجمعة لا تُحسب. ووقت توقف المرحلة بعائق مسجّل (مثل نقص المواد) لا يُحسب على القسم.</p></div></li>
                <li><span class="points-step-no">3</span><div><strong>المدة ثابتة لكل طلب</strong><p>تُثبَّت على الطلب لحظة وصوله؛ تغيير مدة القسم لاحقاً لا يغيّر مواعيد الطلبات الموجودة فيه.</p></div></li>
            </ol>
        </div>
    </section>

    <form method="POST" action="{{ route('production.department-times.update') }}" class="table-factory-wrapper">
        @csrf @method('PUT')
        <div class="table-responsive">
            <table class="table table-factory align-middle mb-0">
                <thead><tr>
                    <th scope="col">القسم</th>
                    <th scope="col" style="width: 11rem;">المدة (أيام عمل)</th>
                    <th scope="col" style="width: 11rem;">التنبيه عند مرور</th>
                    <th scope="col" class="text-end">طلبات في القسم الآن</th>
                    <th scope="col" class="text-end">متأخرة الآن</th>
                </tr></thead>
                <tbody>
                    @foreach ($departments as $department)
                        @php $stat = $stats[$department->id]; @endphp
                        <tr>
                            <th scope="row" class="fw-semibold">{{ $department->name_ar }}</th>
                            <td>
                                <label class="visually-hidden" for="days-{{ $department->id }}">مدة {{ $department->name_ar }} بأيام العمل</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" inputmode="numeric" min="1" max="90" class="form-control" id="days-{{ $department->id }}" name="departments[{{ $department->id }}][target_days]" value="{{ old('departments.'.$department->id.'.target_days', $department->target_days) }}" placeholder="بلا مدة">
                                    <span class="input-group-text">يوم</span>
                                </div>
                            </td>
                            <td>
                                <label class="visually-hidden" for="warn-{{ $department->id }}">نسبة التنبيه لقسم {{ $department->name_ar }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" inputmode="numeric" min="10" max="95" step="5" class="form-control" id="warn-{{ $department->id }}" name="departments[{{ $department->id }}][warning_percent]" value="{{ old('departments.'.$department->id.'.warning_percent', $department->warning_percent) }}" required>
                                    <span class="input-group-text">% من المدة</span>
                                </div>
                            </td>
                            <td class="text-end">{{ $stat['open'] }}@if ($stat['without_deadline'] > 0 && $department->target_days)<span class="d-block small text-muted">{{ $stat['without_deadline'] }} بلا موعد</span>@endif</td>
                            <td class="text-end">@if ($stat['overdue'] > 0)<span class="status-chip status-chip-danger">{{ $stat['overdue'] }} متأخرة</span>@else<span class="text-muted">0</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3 d-flex flex-wrap gap-3 align-items-center justify-content-between border-top">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="apply_to_open" name="apply_to_open" value="1">
                <label class="form-check-label" for="apply_to_open">طبّق المدة أيضاً على الطلبات الموجودة الآن في الأقسام بلا موعد (من تاريخ وصولها)</label>
            </div>
            <button class="btn btn-primary"><i class="fas fa-check me-1" aria-hidden="true"></i> حفظ المدد</button>
        </div>
    </form>
</div>
@endsection
