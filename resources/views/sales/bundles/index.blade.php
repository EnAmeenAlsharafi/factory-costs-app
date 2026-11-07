@extends('layouts.app')

@section('title', 'بكجات المبيعات')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2 fs-8">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">بكجات المبيعات</li>
                </ol>
            </nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">بكجات المبيعات</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">تعريف بكجات تجمع بنداً أو أكثر من الأسرة المصنّعة والمنتجات الجاهزة، تُستخدم عند مطابقة طلبات سلة.</p>
        </div>

        @can('sales_imports.manage')
            <a href="{{ route('sales.bundles.create') }}" class="btn btn-factory-warning shadow-sm">
                <i class="fas fa-plus me-1"></i> إنشاء بكج جديد
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>اسم البكج</th>
                            <th>SKU</th>
                            <th>عدد المكونات</th>
                            <th class="text-center">الحالة</th>
                            @can('sales_imports.manage')
                                <th class="text-center">الإجراءات</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bundles as $bundle)
                            <tr>
                                <td class="fw-bold text-dark">{{ $bundle->name }}</td>
                                <td class="fw-mono">{{ $bundle->sales_sku ?: '-' }}</td>
                                <td class="text-center">{{ $bundle->components->count() }}</td>
                                <td class="text-center">
                                    @if($bundle->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                @can('sales_imports.manage')
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="{{ route('sales.bundles.edit', $bundle) }}" class="btn btn-sm btn-outline-primary" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('sales.bundles.toggle-status', $bundle) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $bundle->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $bundle->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                    <i class="fas {{ $bundle->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">لا توجد بكجات مسجلة حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($bundles->hasPages())
            <div class="card-footer bg-white">
                {{ $bundles->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
