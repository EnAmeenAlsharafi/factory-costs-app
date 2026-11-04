@extends('layouts.app')

@section('title', 'مطابقة طلبات سلة')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">مطابقة طلبات سلة</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">مطابقة منتجات سلة (SKU / الاسم)</h1>
            <p class="text-muted mb-0 fs-7">ربط رمز SKU أو اسم المنتج في سلة بمنتج جاهز، بكج، أو موديل مصنّع في النظام.</p>
        </div>

        @can('sales_imports.manage')
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addMappingModal">
                <i class="fas fa-plus me-1"></i> إضافة مطابقة جديدة
            </button>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>نوع المطابقة</th>
                            <th>القيمة</th>
                            <th>قناة البيع</th>
                            <th>النوع المستهدف</th>
                            <th>المُستهدَف</th>
                            <th class="text-center">الحالة</th>
                            @can('sales_imports.manage')
                                <th class="text-center">الإجراءات</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mappings as $mapping)
                            <tr>
                                <td>{{ $mapping->match_type === 'SKU' ? 'SKU' : 'الاسم' }}</td>
                                <td class="fw-mono">{{ $mapping->match_value }}</td>
                                <td>{{ $mapping->salesChannel->name_ar ?? '-' }}</td>
                                <td>
                                    @if($mapping->target_type === 'READY_STOCK')
                                        <span class="badge bg-success bg-opacity-10 text-success">منتج جاهز</span>
                                    @elseif($mapping->target_type === 'BUNDLE')
                                        <span class="badge bg-info bg-opacity-10 text-info">بكج</span>
                                    @else
                                        <span class="badge bg-primary bg-opacity-10 text-primary">سرير مصنّع</span>
                                    @endif
                                </td>
                                <td class="fw-bold text-dark">
                                    {{ $mapping->material?->sales_display_name ?? $mapping->material?->name_ar ?? $mapping->bundleDefinition?->name ?? $mapping->productModel?->name_ar ?? '-' }}
                                </td>
                                <td class="text-center">
                                    @if($mapping->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                @can('sales_imports.manage')
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMappingModal{{ $mapping->id }}" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('sales.item-mappings.toggle-status', $mapping) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $mapping->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $mapping->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                    <i class="fas {{ $mapping->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">لا توجد مطابقات مسجلة حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($mappings->hasPages())
            <div class="card-footer bg-white">
                {{ $mappings->links() }}
            </div>
        @endif
    </div>

</div>

<!-- Modal: Add Mapping -->
<div class="modal fade" id="addMappingModal" tabindex="-1" aria-labelledby="addMappingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('sales.item-mappings.store') }}" method="POST" class="mapping-form">
                @csrf
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="addMappingModalLabel"><i class="fas fa-link text-primary me-2"></i>إضافة مطابقة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">قناة البيع <span class="text-danger">*</span></label>
                        <select name="sales_channel_id" class="form-select" required>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">نوع المطابقة <span class="text-danger">*</span></label>
                            <select name="match_type" class="form-select" required>
                                <option value="SKU">SKU</option>
                                <option value="NAME">الاسم</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">القيمة <span class="text-danger">*</span></label>
                            <input type="text" name="match_value" class="form-control" required placeholder="rdy-test-001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">النوع المستهدف <span class="text-danger">*</span></label>
                        <select name="target_type" class="form-select target-type-select" required>
                            <option value="READY_STOCK">منتج جاهز</option>
                            <option value="BUNDLE">بكج</option>
                            <option value="MANUFACTURED">سرير مصنّع</option>
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-READY_STOCK">
                        <label class="form-label fw-semibold">المنتج الجاهز <span class="text-danger">*</span></label>
                        <select name="material_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->sales_display_name ?: $mat->name_ar }} ({{ $mat->sales_sku ?: $mat->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-BUNDLE d-none">
                        <label class="form-label fw-semibold">البكج <span class="text-danger">*</span></label>
                        <select name="bundle_definition_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($bundles as $bundle)
                                <option value="{{ $bundle->id }}">{{ $bundle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-MANUFACTURED d-none">
                        <label class="form-label fw-semibold">الموديل <span class="text-danger">*</span></label>
                        <select name="product_model_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($models as $model)
                                <option value="{{ $model->id }}">{{ $model->name_ar }} ({{ $model->model_code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ المطابقة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals: Edit Mappings -->
@foreach($mappings as $mapping)
<div class="modal fade" id="editMappingModal{{ $mapping->id }}" tabindex="-1" aria-labelledby="editMappingModalLabel{{ $mapping->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('sales.item-mappings.update', $mapping) }}" method="POST" class="mapping-form">
                @csrf
                @method('PUT')
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="editMappingModalLabel{{ $mapping->id }}">
                        <i class="fas fa-edit text-primary me-2"></i>تعديل المطابقة
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">قناة البيع <span class="text-danger">*</span></label>
                        <select name="sales_channel_id" class="form-select" required>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}" {{ $mapping->sales_channel_id == $channel->id ? 'selected' : '' }}>{{ $channel->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">نوع المطابقة <span class="text-danger">*</span></label>
                            <select name="match_type" class="form-select" required>
                                <option value="SKU" {{ $mapping->match_type === 'SKU' ? 'selected' : '' }}>SKU</option>
                                <option value="NAME" {{ $mapping->match_type === 'NAME' ? 'selected' : '' }}>الاسم</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">القيمة <span class="text-danger">*</span></label>
                            <input type="text" name="match_value" class="form-control" value="{{ $mapping->match_value }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">النوع المستهدف <span class="text-danger">*</span></label>
                        <select name="target_type" class="form-select target-type-select" required>
                            <option value="READY_STOCK" {{ $mapping->target_type === 'READY_STOCK' ? 'selected' : '' }}>منتج جاهز</option>
                            <option value="BUNDLE" {{ $mapping->target_type === 'BUNDLE' ? 'selected' : '' }}>بكج</option>
                            <option value="MANUFACTURED" {{ $mapping->target_type === 'MANUFACTURED' ? 'selected' : '' }}>سرير مصنّع</option>
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-READY_STOCK {{ $mapping->target_type !== 'READY_STOCK' ? 'd-none' : '' }}">
                        <label class="form-label fw-semibold">المنتج الجاهز <span class="text-danger">*</span></label>
                        <select name="material_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}" {{ $mapping->material_id == $mat->id ? 'selected' : '' }}>{{ $mat->sales_display_name ?: $mat->name_ar }} ({{ $mat->sales_sku ?: $mat->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-BUNDLE {{ $mapping->target_type !== 'BUNDLE' ? 'd-none' : '' }}">
                        <label class="form-label fw-semibold">البكج <span class="text-danger">*</span></label>
                        <select name="bundle_definition_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($bundles as $bundle)
                                <option value="{{ $bundle->id }}" {{ $mapping->bundle_definition_id == $bundle->id ? 'selected' : '' }}>{{ $bundle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 target-field target-field-MANUFACTURED {{ $mapping->target_type !== 'MANUFACTURED' ? 'd-none' : '' }}">
                        <label class="form-label fw-semibold">الموديل <span class="text-danger">*</span></label>
                        <select name="product_model_id" class="form-select">
                            <option value="">-- اختر --</option>
                            @foreach($models as $model)
                                <option value="{{ $model->id }}" {{ $mapping->product_model_id == $model->id ? 'selected' : '' }}>{{ $model->name_ar }} ({{ $model->model_code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mapping-form').forEach(function (form) {
        const select = form.querySelector('.target-type-select');
        const fields = form.querySelectorAll('.target-field');

        function updateVisibility() {
            fields.forEach(function (field) {
                field.classList.toggle('d-none', !field.classList.contains('target-field-' + select.value));
            });
        }

        select.addEventListener('change', updateVisibility);
        updateVisibility();
    });
});
</script>
@endpush
@endsection
