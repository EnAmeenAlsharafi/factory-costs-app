@extends('layouts.app')

@section('title', 'المنتجات الجاهزة للبيع')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">المنتجات الجاهزة للبيع</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">توفر المنتجات الجاهزة للبيع (Ready-Stock Products)</h1>
            <p class="text-muted mb-0 fs-7">قائمة المواد المُمَيَّزة للبيع المباشر للعميل مع كمياتها المتاحة بالمستودع.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.ready-stock-products.index') }}" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="بحث بالاسم، SKU، أو الكود...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button>
                </div>
                @if(request()->filled('search'))
                    <div class="col-md-2">
                        <a href="{{ route('sales.ready-stock-products.index') }}" class="btn btn-outline-secondary w-100">إعادة ضبط</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>SKU</th>
                            <th>الاسم المعروض</th>
                            <th>التصنيف</th>
                            <th class="text-center">بالمستودع</th>
                            <th class="text-center">محجوز</th>
                            <th class="text-center">صافي المتاح</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            <tr>
                                <td class="fw-mono fw-bold text-primary">{{ $material->sales_sku ?: '-' }}</td>
                                <td class="fw-bold text-dark">
                                    {{ $material->sales_display_name ?: $material->name_ar }}
                                    <div class="text-muted fs-8">{{ $material->code }}</div>
                                </td>
                                <td>{{ $material->category->name_ar ?? '-' }}</td>
                                <td class="text-center fw-mono">{{ number_format($material->availability['on_hand'], 2) }}</td>
                                <td class="text-center fw-mono text-muted">{{ number_format($material->availability['committed'], 2) }}</td>
                                <td class="text-center">
                                    @if($material->availability['available'] > 0)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">{{ number_format($material->availability['available'], 2) }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-1">0.00</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @can('materials.manage')
                                        <a href="{{ route('materials.edit', $material) }}" class="btn btn-sm btn-outline-primary" title="تعديل بيانات المادة">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    لا توجد مواد مُمَيَّزة كمنتجات جاهزة للبيع حتى الآن.
                                    @can('materials.manage')
                                        <br><a href="{{ route('materials.index') }}" class="text-decoration-none">افتح كتالوج المواد لتمييز مادة كمنتج جاهز</a>.
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($materials->hasPages())
            <div class="card-footer bg-white">
                {{ $materials->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
