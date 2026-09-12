@extends('layouts.app')

@section('title', 'تصنيفات العملاء - مصنع مفروشات سدير')
@section('page-title', 'تصنيفات وفئات العملاء')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'تصنيفات العملاء (Customer Types)',
        'subtitle' => 'فئات تصنيف العملاء (عميل مباشر/أفراد، تاجر جملة، متجر مفروشات سدير، شركات ومؤسسات).',
        'icon' => 'fas fa-id-badge',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'تصنيفات العملاء']
        ],
        'actionUrl' => route('customer-types.create'),
        'actionText' => 'إضافة تصنيف جديد',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'customer_types.manage'
    ])

    <!-- Types Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الرمز الكودي</th>
                        <th>مسمى التصنيف (عربي)</th>
                        <th>المسمى الإنجليزي</th>
                        <th>عدد العملاء المرتبطين</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>
                                <code class="code-badge">{{ $type->code }}</code>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $type->name_ar }}</div>
                            </td>
                            <td>
                                <span dir="ltr" class="text-muted fs-7">{{ $type->name_en ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                    <i class="fas fa-users me-1 text-muted"></i> {{ $type->customers_count }} عميل
                                </span>
                            </td>
                            <td>
                                @if ($type->is_active)
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
                                    @can('customer_types.manage')
                                        <a href="{{ route('customer-types.edit', $type) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل التصنيف">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('customer-types.toggle-status', $type) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $type->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $type->is_active ? 'تعطيل التصنيف' : 'تفعيل التصنيف' }}"
                                                    onclick="return confirm('{{ $type->is_active ? 'هل أنت متأكد من تعطيل هذا التصنيف؟' : 'هل ترغب في إعادة تفعيل هذا التصنيف؟' }}');">
                                                <i class="fas {{ $type->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-id-card-alt fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد تصنيفات عملاء مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($types->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $types->firstItem() }} إلى {{ $types->lastItem() }} من إجمالي {{ $types->total() }} تصنيف
                </div>
                <div>
                    {{ $types->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
