@extends('layouts.app')

@section('title', 'تفاصيل الوصفة - ' . $recipe->recipe_code)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Breadcrumb Navigation --}}
    <div class="mb-3">
        <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted fs-7">
            <i class="fas fa-arrow-right me-1"></i> قائمة وصفات التصنيع
        </a>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Recipe Header Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark font-monospace fs-6 px-3 py-2">{{ $recipe->recipe_code }}</span>
                        @if($recipe->target_type === 'PRODUCT_CONFIGURATION')
                            <span class="badge bg-info text-dark">منتج نهائي</span>
                        @else
                            <span class="badge bg-purple text-white" style="background-color: #6f42c1;">مكون نصف مصنع</span>
                        @endif
                    </div>
                    <h2 class="h3 mb-2 text-dark fw-bold">{{ $recipe->name }}</h2>
                    <p class="text-muted mb-0">
                        @if($recipe->target_type === 'PRODUCT_CONFIGURATION' && $recipe->productConfiguration)
                            الهدف: الموديل <strong class="text-dark">{{ $recipe->productConfiguration->productModel?->name_ar }}</strong>
                            ({{ $recipe->productConfiguration->width_cm }} × {{ $recipe->productConfiguration->length_cm }} سم)
                            @if($recipe->productConfiguration->has_storage)
                                <span class="badge bg-warning text-dark me-1">سحارة</span>
                            @endif
                        @elseif($recipe->target_type === 'SEMI_FINISHED_COMPONENT' && $recipe->semiFinishedComponent)
                            الهدف: المكون نصف المصنع <strong class="text-dark">{{ $recipe->semiFinishedComponent->name_ar }}</strong> ({{ $recipe->semiFinishedComponent->component_code }})
                        @endif
                    </p>
                </div>

                <div class="d-flex gap-2">
                    @if($selectedVersion)
                        {{-- Copy Version Button --}}
                        @can('recipes.manage')
                            <form action="{{ route('recipes.versions.copy', [$recipe, $selectedVersion]) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary fw-semibold" title="نسخ مكونات هذا الإصدار في مسودة جديدة V{{ $recipe->versions->max('version_number') + 1 }}">
                                    <i class="fas fa-copy me-1"></i> نسخ كإصدار جديد (New Draft)
                                </button>
                            </form>
                        @endcan

                        {{-- Edit Draft Version Button --}}
                        @if($selectedVersion->status === 'DRAFT')
                            @can('recipes.manage')
                                <a href="{{ route('recipes.versions.edit', [$recipe, $selectedVersion]) }}" class="btn btn-outline-warning fw-semibold">
                                    <i class="fas fa-edit me-1"></i> تعديل المسودة (V{{ $selectedVersion->version_number }})
                                </a>
                            @endcan

                            {{-- Approve Version Button --}}
                            @can('recipes.approve')
                                <form action="{{ route('recipes.versions.approve', [$recipe, $selectedVersion]) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت تأكد من اعتماد هذا الإصدار؟ سيصبح هذا الإصدار هو المعتمد في التصنيع وسيتم إلغاء اعتماد الإصدار السابق تلقائياً.');">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="fas fa-check-circle me-1"></i> اعتماد الإصدار (Approve V{{ $selectedVersion->version_number }})
                                    </button>
                                </form>
                            @endcan
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Versions Tabs --}}
    <div class="mb-4">
        <ul class="nav nav-tabs border-bottom-2">
            @foreach($recipe->versions as $ver)
                <li class="nav-item">
                    <a href="{{ route('recipes.show', [$recipe, 'version_id' => $ver->id]) }}" class="nav-link {{ $selectedVersion && $selectedVersion->id === $ver->id ? 'active fw-bold' : '' }}">
                        الإصدار V{{ $ver->version_number }}
                        @if($ver->status === 'APPROVED')
                            <span class="badge bg-success ms-1">معتمد</span>
                        @elseif($ver->status === 'DRAFT')
                            <span class="badge bg-warning text-dark ms-1">مسودة</span>
                        @elseif($ver->status === 'SUPERSEDED')
                            <span class="badge bg-secondary ms-1">سابق</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    @if($selectedVersion)
        {{-- Version Metadata Bar --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 bg-light rounded-3">
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">حالة الإصدار المختار:</small>
                        @if($selectedVersion->status === 'APPROVED')
                            <span class="badge bg-success fs-7 px-3 py-2"><i class="fas fa-check-circle me-1"></i> إصدار معتمد رسمياً</span>
                        @elseif($selectedVersion->status === 'DRAFT')
                            <span class="badge bg-warning text-dark fs-7 px-3 py-2"><i class="fas fa-edit me-1"></i> مسودة قيد الإعداد</span>
                        @elseif($selectedVersion->status === 'SUPERSEDED')
                            <span class="badge bg-secondary fs-7 px-3 py-2"><i class="fas fa-history me-1"></i> إصدار سابق ملغى الاعتماد</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">المعتمد بواسطة:</small>
                        <span class="fw-semibold text-dark">{{ $selectedVersion->approvedBy ? $selectedVersion->approvedBy->name : 'غير معتمد بعد' }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">تاريخ الاعتماد / الإنشاء:</small>
                        <span class="fw-semibold text-dark">{{ $selectedVersion->approved_at ? $selectedVersion->approved_at->format('Y-m-d H:i') : $selectedVersion->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">ملاحظات الإصدار:</small>
                        <span class="fs-7 text-dark">{{ $selectedVersion->notes ?: 'لا توجد ملاحظات مدونة' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Non-Authoritative Standard Cost Preview Banner --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-primary bg-opacity-10 border-start border-4 border-primary">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="fw-bold mb-1 text-primary">
                            <i class="fas fa-calculator me-1"></i> معاينة التكلفة المعيارية التقديرية (Standard Cost Preview)
                        </h6>
                        <small class="text-muted d-block">
                            ملاحظة: هذه التكلفة معيارية تقريبية بناءً على أحدث أسعار اللوت المتاحة، وتكلفة التصنيع الحقيقية تحسب مستقبلاً عند صرف المواد من المخزن.
                        </small>
                    </div>
                    <div>
                        @if($costPreview && ! $costPreview['has_unavailable_prices'] && $costPreview['total_cost'] > 0)
                            <div class="text-end">
                                <span class="fs-4 fw-bold text-success">{{ number_format($costPreview['total_cost'], 2) }} ر.س</span>
                                <small class="d-block text-muted">إجمالي التكلفة التقديرية للوحدة</small>
                            </div>
                        @else
                            <span class="badge bg-secondary fs-7 px-3 py-2">معاينة التكلفة غير متاحة (تتطلب أسعار استلام لوت متوفرة)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Version Items Table Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="card-title mb-0 text-dark fw-bold">
                    <i class="fas fa-boxes-stacked text-warning me-2"></i> قائمة المواد والمكونات (الإصدار V{{ $selectedVersion->version_number }})
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="ps-3 text-center">#</th>
                                <th>نوع البند</th>
                                <th>المادة الخام / المكون</th>
                                <th>الكمية الصافية Required</th>
                                <th>الوحدة</th>
                                <th>نسبة الهدر Waste</th>
                                <th>الكمية المخططة Planned</th>
                                <th>ملاحظات البند</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($selectedVersion->items as $index => $item)
                                <tr>
                                    <td class="ps-3 text-center text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        @if($item->item_type === 'MATERIAL')
                                            <span class="badge bg-info text-dark">مادة خام</span>
                                        @else
                                            <span class="badge bg-purple text-white" style="background-color: #6f42c1;">مكون نصف مصنع</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->item_type === 'MATERIAL' && $item->material)
                                            <div class="fw-bold text-dark">{{ $item->material->name_ar }}</div>
                                            <small class="text-muted font-monospace">{{ $item->material->code }}</small>
                                        @elseif($item->item_type === 'SEMI_FINISHED_COMPONENT' && $item->semiFinishedComponent)
                                            <div class="fw-bold text-dark">{{ $item->semiFinishedComponent->name_ar }}</div>
                                            <small class="text-muted font-monospace">{{ $item->semiFinishedComponent->component_code }}</small>
                                        @endif
                                    </td>
                                    <td class="font-monospace fw-bold fs-6">{{ number_format($item->quantity, 4) }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $item->unit?->name_ar }} ({{ $item->unit?->code }})</span></td>
                                    <td>
                                        @if($item->waste_percentage > 0)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ number_format($item->waste_percentage, 1) }}%</span>
                                        @else
                                            <span class="text-muted">0%</span>
                                        @endif
                                    </td>
                                    <td class="font-monospace fw-bold text-primary fs-6">{{ number_format($item->planned_quantity, 4) }}</td>
                                    <td class="fs-7 text-muted">{{ $item->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">لا توجد بنود مسجلة في هذا الإصدار.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
