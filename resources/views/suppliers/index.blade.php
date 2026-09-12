@extends('layouts.app')

@section('title', 'إدارة الموردين - مصنع مفروشات سدير')
@section('page-title', 'سجل الموردين ومصادر المواد الخام')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'سجل الموردين (Suppliers)',
        'subtitle' => 'إدارة بيانات موردي الخشب، الإسفنج، الأقمشة، ومستلزمات الإنتاج والتغليف.',
        'icon' => 'fas fa-truck',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'سجل الموردين']
        ],
        'actionUrl' => route('suppliers.create'),
        'actionText' => 'إضافة مورد جديد',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'suppliers.manage'
    ])

    <!-- Search & Filter Card -->
    <div class="card-factory p-3">
        <form action="{{ route('suppliers.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-search"></i></span>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-control border-start-0 ps-2" 
                           placeholder="بحث برمز المورد، الاسم، الجوال، أو السجل التجاري...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">كافة الحالات</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط فقط</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل فقط</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">تصفية</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-light border text-secondary" title="إعادة ضبط">
                        <i class="fas fa-undo"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Suppliers Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>رمز المورد</th>
                        <th>اسم المورد / الشركة</th>
                        <th>جهة الاتصال</th>
                        <th>رقم الجوال</th>
                        <th>المدينة</th>
                        <th>الرقم الضريبي</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>
                                <a href="{{ route('suppliers.show', $supplier) }}" class="text-decoration-none">
                                    <code class="code-badge">{{ $supplier->supplier_code }}</code>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $supplier->name }}</div>
                                @if ($supplier->commercial_name && $supplier->commercial_name !== $supplier->name)
                                    <small class="text-muted fs-8 d-block">{{ $supplier->commercial_name }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $supplier->contact_person ?? '-' }}
                            </td>
                            <td>
                                <span dir="ltr">{{ $supplier->mobile ?? $supplier->phone ?? '-' }}</span>
                            </td>
                            <td>
                                {{ $supplier->city ?? '-' }}
                            </td>
                            <td>
                                @if ($supplier->tax_number)
                                    <span dir="ltr" class="font-monospace fs-8">{{ $supplier->tax_number }}</span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($supplier->is_active)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> نشط
                                    </span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fas fa-ban me-1"></i> معطل
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @can('suppliers.manage')
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل المورد">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('suppliers.toggle-status', $supplier) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $supplier->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $supplier->is_active ? 'تعطيل المورد' : 'تفعيل المورد' }}"
                                                    onclick="return confirm('{{ $supplier->is_active ? 'هل أنت متأكد من تعطيل هذا المورد؟ لن يظهر في القوائم النشطة للمشتريات.' : 'هل ترغب في إعادة تفعيل هذا المورد؟' }}');">
                                                <i class="fas {{ $supplier->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-truck-loading fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد سجلات موردين مطابقة لبحثك.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $suppliers->firstItem() }} إلى {{ $suppliers->lastItem() }} من إجمالي {{ $suppliers->total() }} مورد
                </div>
                <div>
                    {{ $suppliers->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
