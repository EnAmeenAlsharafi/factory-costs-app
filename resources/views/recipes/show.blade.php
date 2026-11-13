@extends('layouts.app')

@section('title', 'الوصفة ' . $recipe->recipe_code)

@section('content')
@php
    $versionStates = [
        'APPROVED' => ['معتمد', 'success', 'fa-circle-check'],
        'DRAFT' => ['مسودة', 'warning', 'fa-file-pen'],
        'SUPERSEDED' => ['سابق', 'secondary', 'fa-clock-rotate-left'],
    ];
    $isDraft = $selectedVersion?->status === 'DRAFT';
    $nextVersionNumber = $recipe->versions->max('version_number') + 1;
    $configuration = $recipe->productConfiguration;
    $formatQuantity = fn ($value): string => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
@endphp
<div class="container-fluid px-4 py-4">

    {{-- Flash messages are rendered once by the layout (partials.flash). --}}

    <div class="page-header-card mb-4">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0 fs-8 text-muted">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="{{ route('recipes.index') }}" class="text-decoration-none text-secondary">وصفات التصنيع</a></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $recipe->recipe_code }}</li>
            </ol>
        </nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="code-chip code-chip-lg">{{ $recipe->recipe_code }}</span>
                    @include('recipes.partials.kind-chip', ['type' => $recipe->target_type === 'PRODUCT_CONFIGURATION' ? 'PRODUCT' : 'SEMI_FINISHED_COMPONENT'])
                    @include('recipes.partials.active-chip', ['active' => $recipe->is_active])
                </div>
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1">{{ $recipe->name }}</h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">
                    @if($recipe->target_type === 'PRODUCT_CONFIGURATION' && $configuration)
                        لموديل <strong class="text-dark">{{ $configuration->productModel?->name_ar }}</strong>
                        بمقاس <span class="qty">{{ (float) $configuration->width_cm }} × {{ (float) $configuration->length_cm }}</span> سم
                        — {{ $configuration->has_storage ? 'مع تخزين' : 'بدون تخزين' }}
                    @elseif($recipe->target_type === 'SEMI_FINISHED_COMPONENT' && $recipe->semiFinishedComponent)
                        للمكون <strong class="text-dark">{{ $recipe->semiFinishedComponent->name_ar }}</strong>
                        <span class="code-chip fs-8">{{ $recipe->semiFinishedComponent->component_code }}</span>
                    @endif
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                @if ($selectedVersion && $isDraft)
                    @can('recipes.manage')
                        <a href="{{ route('recipes.versions.edit', [$recipe, $selectedVersion]) }}" class="btn btn-outline-primary fw-semibold">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i> تعديل بنود V{{ $selectedVersion->version_number }}
                        </a>
                    @endcan
                    @can('recipes.approve')
                        <form action="{{ route('recipes.versions.approve', [$recipe, $selectedVersion]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('اعتماد V{{ $selectedVersion->version_number }}؟ سيستخدمه الإنتاج من الآن، ويصبح الإصدار المعتمد الحالي «سابقاً».');">
                            @csrf
                            <button type="submit" class="btn btn-success fw-bold">
                                <i class="fas fa-circle-check" aria-hidden="true"></i> اعتماد V{{ $selectedVersion->version_number }}
                            </button>
                        </form>
                    @endcan
                @elseif ($selectedVersion)
                    @can('recipes.manage')
                        <form action="{{ route('recipes.versions.copy', [$recipe, $selectedVersion]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary fw-semibold" title="ينسخ بنود V{{ $selectedVersion->version_number }} إلى مسودة جديدة V{{ $nextVersionNumber }} لتعديلها">
                                <i class="fas fa-copy" aria-hidden="true"></i> إصدار جديد من V{{ $selectedVersion->version_number }}
                            </button>
                        </form>
                    @endcan
                @endif

                @can('recipes.manage')
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات أخرى">
                            <i class="fas fa-ellipsis" aria-hidden="true"></i> المزيد
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="{{ route('recipes.edit', $recipe) }}"><i class="fas fa-pen fa-fw" aria-hidden="true"></i> تعديل بيانات الوصفة</a></li>
                            @if ($selectedVersion && $isDraft)
                                <li>
                                    <form action="{{ route('recipes.versions.copy', [$recipe, $selectedVersion]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="fas fa-copy fa-fw" aria-hidden="true"></i> نسخ كمسودة V{{ $nextVersionNumber }}</button>
                                    </form>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            @if ($selectedVersion && $isDraft && $recipe->versions->count() > 1)
                                <li>
                                    <form action="{{ route('recipes.versions.destroy', [$recipe, $selectedVersion]) }}" method="POST" onsubmit="return confirm('حذف المسودة V{{ $selectedVersion->version_number }}؟ لا يمكن التراجع.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash fa-fw" aria-hidden="true"></i> حذف المسودة V{{ $selectedVersion->version_number }}</button>
                                    </form>
                                </li>
                            @endif
                            <li>
                                <form action="{{ route('recipes.destroy', $recipe) }}" method="POST" onsubmit="return confirm('حذف الوصفة {{ $recipe->recipe_code }} وجميع إصداراتها؟ لا يمكن التراجع.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-alt fa-fw" aria-hidden="true"></i> حذف الوصفة بالكامل</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endcan
            </div>
        </div>
    </div>

    {{-- Versions --}}
    <nav class="version-tabs mb-3" aria-label="إصدارات الوصفة">
        @foreach($recipe->versions as $ver)
            @php $state = $versionStates[$ver->status] ?? [$ver->status, 'secondary', 'fa-circle-info']; @endphp
            <a href="{{ route('recipes.show', [$recipe, 'version_id' => $ver->id]) }}" @class(['version-tab', 'is-active' => $selectedVersion && $selectedVersion->id === $ver->id])
               @if ($selectedVersion && $selectedVersion->id === $ver->id) aria-current="page" @endif>
                <span class="ltr-isolate">V{{ $ver->version_number }}</span>
                <span class="status-chip status-chip-{{ $state[1] }}"><i class="fas {{ $state[2] }}" aria-hidden="true"></i> {{ $state[0] }}</span>
            </a>
        @endforeach
    </nav>

    @if($selectedVersion)
        @if ($isDraft)
            <div class="alert alert-warning border-0 shadow-sm d-flex gap-2 align-items-start" role="status">
                <i class="fas fa-file-pen mt-1" aria-hidden="true"></i>
                <div>
                    <strong>V{{ $selectedVersion->version_number }} مسودة:</strong> لا يستخدمها الإنتاج حتى تُعتمد.
                    @if ($recipe->currentApprovedVersion)
                        الإنتاج يعمل حالياً على
                        <a href="{{ route('recipes.show', [$recipe, 'version_id' => $recipe->currentApprovedVersion->id]) }}" class="alert-link">V{{ $recipe->currentApprovedVersion->version_number }}</a>.
                    @else
                        لا يوجد إصدار معتمد لهذه الوصفة بعد.
                    @endif
                </div>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <section class="card border-0 shadow-sm rounded-3 h-100" aria-label="بيانات الإصدار">
                    <div class="card-body">
                        <dl class="spec-meta">
                            <div>
                                <dt>حالة الإصدار</dt>
                                @php $state = $versionStates[$selectedVersion->status] ?? [$selectedVersion->status, 'secondary', 'fa-circle-info']; @endphp
                                <dd><span class="status-chip status-chip-{{ $state[1] }}"><i class="fas {{ $state[2] }}" aria-hidden="true"></i> {{ $state[0] }}</span></dd>
                            </div>
                            <div>
                                <dt>{{ $selectedVersion->approved_at ? 'اعتُمد بواسطة' : 'الاعتماد' }}</dt>
                                <dd>{{ $selectedVersion->approvedBy?->name ?? 'لم يُعتمد بعد' }}</dd>
                            </div>
                            <div>
                                <dt>{{ $selectedVersion->approved_at ? 'تاريخ الاعتماد' : 'تاريخ الإنشاء' }}</dt>
                                <dd><span class="ltr-isolate">{{ ($selectedVersion->approved_at ?? $selectedVersion->created_at)->format('Y-m-d H:i') }}</span></dd>
                            </div>
                            <div>
                                <dt>ملاحظات الإصدار</dt>
                                <dd class="fw-normal fs-7">{{ $selectedVersion->notes ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card border-0 shadow-sm rounded-3 h-100" aria-labelledby="cost-heading">
                    <div class="card-body">
                        <h2 id="cost-heading" class="fs-7 fw-bold text-muted mb-2"><i class="fas fa-calculator" aria-hidden="true"></i> التكلفة المعيارية التقديرية للوحدة</h2>
                        @if($costPreview && ! $costPreview['has_unavailable_prices'] && $costPreview['total_cost'] > 0)
                            <div class="fs-3 fw-bold text-success"><span class="qty">{{ number_format($costPreview['total_cost'], 2) }}</span> <span class="fs-6">ر.س</span></div>
                        @else
                            <div class="fw-bold text-dark mb-1">غير متاحة</div>
                            <p class="fs-8 text-muted mb-0">بعض البنود بلا سعر استلام في المخزون بعد.</p>
                        @endif
                        <p class="fs-8 text-muted mb-0 mt-2">تقدير من أحدث أسعار الدفعات. التكلفة الفعلية تُحسب عند صرف المواد.</p>
                    </div>
                </section>
            </div>
        </div>

        <section class="card border-0 shadow-sm rounded-3 mb-4" aria-labelledby="version-items-heading">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 id="version-items-heading" class="h6 fw-bold text-dark mb-0">
                    <i class="fas fa-boxes-stacked text-warning me-2" aria-hidden="true"></i>قائمة المواد — V{{ $selectedVersion->version_number }}
                </h2>
                <span class="status-chip status-chip-secondary">{{ $selectedVersion->items->count() }} بند</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">المادة / المكون</th>
                                <th>النوع</th>
                                <th class="text-end">الكمية الصافية</th>
                                <th>الوحدة</th>
                                <th class="text-end">الهدر</th>
                                <th class="text-end">الكمية المخططة</th>
                                <th class="pe-3">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($selectedVersion->items as $item)
                                @php
                                    $source = $item->item_type === 'MATERIAL' ? $item->material : $item->semiFinishedComponent;
                                    $sourceCode = $item->item_type === 'MATERIAL' ? $item->material?->code : $item->semiFinishedComponent?->component_code;
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $source?->name_ar ?? 'عنصر محذوف' }}</div>
                                        @if ($sourceCode)<span class="code-chip fs-8">{{ $sourceCode }}</span>@endif
                                    </td>
                                    <td>@include('recipes.partials.kind-chip', ['type' => $item->item_type])</td>
                                    <td class="text-end"><span class="qty">{{ $formatQuantity($item->quantity) }}</span></td>
                                    <td class="fs-7">{{ $item->unit?->name_ar ?? '—' }}</td>
                                    <td class="text-end">
                                        @if($item->waste_percentage > 0)
                                            <span class="status-chip status-chip-danger"><span class="qty">{{ number_format($item->waste_percentage, 1) }}%</span></span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end"><span class="qty qty-planned">{{ $formatQuantity($item->planned_quantity) }}</span></td>
                                    <td class="pe-3 fs-7 text-muted">{{ $item->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr class="table-stack-empty">
                                    <td colspan="7" class="empty-state" role="status">
                                        <p class="mb-0">لا توجد بنود في هذا الإصدار.</p>
                                        @if ($isDraft)
                                            @can('recipes.manage')
                                                <div class="empty-state-cta"><a href="{{ route('recipes.versions.edit', [$recipe, $selectedVersion]) }}" class="btn btn-outline-primary">إضافة بنود</a></div>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @else
        <div class="card-factory empty-state" role="status">
            <p class="mb-0">لا توجد إصدارات لهذه الوصفة.</p>
        </div>
    @endif

</div>
@endsection
