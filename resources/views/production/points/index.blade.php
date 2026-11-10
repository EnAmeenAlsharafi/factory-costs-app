@extends('layouts.app')

@section('title', 'نقاط الإنتاج والمكافآت')
@section('page-title', 'نقاط الإنتاج والمكافآت')

@section('content')
@php $pointsLabel = fn ($points) => rtrim(rtrim(number_format((float) $points, 2, '.', ''), '0'), '.'); @endphp
<div class="d-flex flex-column gap-3">
    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas fa-medal text-warning fs-5" aria-hidden="true"></i> نقاط الإنتاج والمكافآت
            </h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">كل نوع سرير يأخذ وقتاً مختلفاً، فيُكافأ العامل بالنقاط لا بعدد القطع.</p>
        </div>
        @can('reports.production')
            <a href="{{ route('reports.workers.index') }}" class="btn btn-outline-primary"><i class="fas fa-users-gear me-1" aria-hidden="true"></i> تقرير أداء الموظفين</a>
        @endcan
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card border-0 shadow-sm" aria-labelledby="how-heading">
        <div class="card-body">
            <h2 id="how-heading" class="h6 fw-bold mb-3">كيف تُحتسب النقاط؟</h2>
            <ol class="points-steps list-unstyled mb-0">
                <li><span class="points-step-no">1</span><div><strong>تصنيف السرير</strong><p>لكل موديل تصنيف (مثل سرير مضخم). الطلبات الخاصة والمعارض يُحدد تصنيفها في المراجعة الفنية للطلب.</p></div></li>
                <li><span class="points-step-no">2</span><div><strong>تسجيل الإنجاز</strong><p>عند تسجيل العامل كمية منجزة في قسم تُحتسب فيه النقاط، يُضاف له: الكمية × نقاط التصنيف.</p></div></li>
                <li><span class="points-step-no">3</span><div><strong>المكافأة الشهرية</strong><p>يجمع تقرير أداء الموظفين نقاط كل عامل خلال الشهر لاحتساب مكافأته. تعديل النقاط لا يغيّر ما سُجّل سابقاً.</p></div></li>
            </ol>
        </div>
    </section>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="categories-heading">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 id="categories-heading" class="h6 fw-bold mb-0">تصنيفات الأسرة ونقاطها ({{ $categories->count() }})</h2>
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-category" aria-expanded="false" aria-controls="new-category"><i class="fas fa-plus me-1" aria-hidden="true"></i> تصنيف جديد</button>
                </div>
                <div class="card-body">
                    <form id="new-category" class="collapse mb-3 p-3 bg-light rounded-3 border" method="POST" action="{{ route('production.points.store') }}">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4"><label class="form-label small mb-1" for="new_name">اسم التصنيف</label><input class="form-control" id="new_name" name="name_ar" required maxlength="255" placeholder="مثال: سرير أطفال"></div>
                            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="new_points">النقاط للقطعة</label><input class="form-control" id="new_points" name="points" type="number" inputmode="decimal" step="0.01" min="0" max="99" required></div>
                            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="new_order">الترتيب</label><input class="form-control" id="new_order" name="sort_order" type="number" min="0" value="{{ $categories->max('sort_order') + 1 }}"></div>
                            <div class="col-md-4"><label class="form-label small mb-1" for="new_desc">وصف مختصر</label><input class="form-control" id="new_desc" name="description" maxlength="500"></div>
                            <div class="col-12"><button class="btn btn-primary">إضافة التصنيف</button></div>
                        </div>
                    </form>

                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                        @foreach ($categories as $category)
                            <li class="points-category {{ $category->is_active ? '' : 'is-inactive' }}" x-data="{ editing: false }">
                                <div class="d-flex flex-wrap align-items-center gap-3" x-show="!editing">
                                    <span class="points-badge" aria-label="{{ $pointsLabel($category->points) }} نقطة"><bdi>{{ $pointsLabel($category->points) }}</bdi><small>نقطة</small></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold">{{ $category->name_ar }} @unless($category->is_active)<span class="status-chip status-chip-secondary ms-1">معطل</span>@endunless</div>
                                        <div class="small text-muted">{{ $category->description ?: '—' }} · {{ $category->product_models_count }} موديل</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="editing = true">تعديل</button>
                                </div>
                                <form x-show="editing" x-cloak method="POST" action="{{ route('production.points.update', $category) }}" class="row g-2 align-items-end">
                                    @csrf @method('PUT')
                                    <div class="col-md-4"><label class="form-label small mb-1" for="name-{{ $category->id }}">الاسم</label><input class="form-control" id="name-{{ $category->id }}" name="name_ar" value="{{ $category->name_ar }}" required></div>
                                    <div class="col-4 col-md-2"><label class="form-label small mb-1" for="points-{{ $category->id }}">النقاط</label><input class="form-control" id="points-{{ $category->id }}" name="points" type="number" inputmode="decimal" step="0.01" min="0" max="99" value="{{ $pointsLabel($category->points) }}" required></div>
                                    <div class="col-4 col-md-2"><label class="form-label small mb-1" for="order-{{ $category->id }}">الترتيب</label><input class="form-control" id="order-{{ $category->id }}" name="sort_order" type="number" min="0" value="{{ $category->sort_order }}"></div>
                                    <div class="col-4 col-md-4 d-flex align-items-center pb-2"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="active-{{ $category->id }}" name="is_active" value="1" @checked($category->is_active)><label class="form-check-label" for="active-{{ $category->id }}">نشط</label></div></div>
                                    <div class="col-12"><label class="form-label small mb-1" for="desc-{{ $category->id }}">الوصف</label><input class="form-control" id="desc-{{ $category->id }}" name="description" value="{{ $category->description }}" maxlength="500"></div>
                                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm">حفظ</button><button type="button" class="btn btn-light btn-sm" @click="editing = false">إلغاء</button></div>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-3">
            <section class="card border-0 shadow-sm" aria-labelledby="departments-heading">
                <div class="card-body">
                    <h2 id="departments-heading" class="h6 fw-bold mb-1">الأقسام التي تُحتسب فيها النقاط</h2>
                    <p class="small text-muted">النقاط تُكتسب من الإنجاز في هذه الأقسام فقط.</p>
                    <form method="POST" action="{{ route('production.points.departments') }}">
                        @csrf @method('PUT')
                        @foreach ($departments as $department)
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="dept-{{ $department->id }}" name="departments[]" value="{{ $department->id }}" @checked($department->awards_points)>
                                <label class="form-check-label" for="dept-{{ $department->id }}">{{ $department->name_ar }}</label>
                            </div>
                        @endforeach
                        <button class="btn btn-outline-primary btn-sm mt-1">حفظ الأقسام</button>
                    </form>
                </div>
            </section>

            <section class="card border-0 shadow-sm" aria-labelledby="credit-heading">
                <div class="card-body">
                    <h2 id="credit-heading" class="h6 fw-bold mb-1">احتساب أعمال بلا تصنيف</h2>
                    <p class="small text-muted">إن سُجّل إنجاز قبل تصنيف السرير، صنّفه ثم احتسب نقاط ذلك الشهر هنا.</p>
                    <form method="POST" action="{{ route('production.points.credit-unclassified') }}" class="d-flex gap-2 align-items-end">
                        @csrf
                        <div class="flex-grow-1"><label class="form-label small mb-1" for="credit_month">الشهر</label><input class="form-control" type="month" id="credit_month" name="month" value="{{ now()->format('Y-m') }}" required></div>
                        <button class="btn btn-outline-primary">احتساب</button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm" aria-labelledby="unclassified-heading">
        <div class="card-header bg-white py-3">
            <h2 id="unclassified-heading" class="h6 fw-bold mb-0">موديلات بلا تصنيف ({{ $unclassifiedModels->count() }})</h2>
        </div>
        <div class="card-body">
            @if ($unclassifiedModels->isEmpty())
                <p class="text-success mb-0"><i class="fas fa-circle-check me-1" aria-hidden="true"></i> كل الموديلات النشطة مصنفة.</p>
            @else
                <p class="small text-muted">إنجاز هذه الموديلات في أقسام النقاط لا يكسب نقاطاً حتى تُصنّف. يمكن أيضاً تصنيف الموديل من صفحة تعديله.</p>
                <form method="POST" action="{{ route('production.points.models') }}">
                    @csrf @method('PUT')
                    <div class="row g-2">
                        @foreach ($unclassifiedModels as $model)
                            <div class="col-md-6 col-xl-4">
                                <label class="form-label small mb-1" for="model-{{ $model->id }}">{{ $model->name_ar }} <span class="text-muted"><bdi>{{ $model->model_code }}</bdi></span></label>
                                <select class="form-select" id="model-{{ $model->id }}" name="models[{{ $model->id }}]">
                                    <option value="">— بلا تصنيف —</option>
                                    @foreach ($categories->where('is_active', true) as $category)<option value="{{ $category->id }}">{{ $category->label }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-primary mt-3">حفظ التصنيفات</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
