@extends('layouts.app')

@section('title', 'وحدات القياس - مصنع مفروشات سدير')
@section('page-title', 'إدارة وحدات القياس والتحويل')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'وحدات القياس (Units of Measure)',
        'subtitle' => 'تعريف وحدات قياس المواد الخام ومستلزمات الإنتاج (أطوال، أوزان، ألواح، حبات، رولات، كراتين).',
        'icon' => 'fas fa-ruler-combined',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'وحدات القياس']
        ],
        'actionUrl' => route('units.create'),
        'actionText' => 'إضافة وحدة قياس جديدة',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'units.manage'
    ])

    <!-- Units Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الرمز الكودي</th>
                        <th>اسم الوحدة (عربي)</th>
                        <th>الاسم الإنجليزي</th>
                        <th>الرمز المختصر</th>
                        <th>نوع الوحدة</th>
                        <th>الكسور العشرية</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr>
                            <td>
                                <code class="code-badge">{{ $unit->code }}</code>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $unit->name_ar }}</div>
                            </td>
                            <td>
                                <span dir="ltr" class="text-muted fs-7">{{ $unit->name_en ?? '-' }}</span>
                            </td>
                            <td>
                                @if ($unit->symbol)
                                    <span class="badge bg-light text-dark border font-monospace">{{ $unit->symbol }}</span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                    {{ $unit->unit_type ?? 'عام' }}
                                </span>
                            </td>
                            <td>
                                @if ($unit->allows_decimal)
                                    <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25">
                                        يقبل كسور ({{ $unit->decimal_precision }} خانات)
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">أعداد صحيحة فقط</span>
                                @endif
                            </td>
                            <td>
                                @if ($unit->is_active)
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
                                    @can('units.manage')
                                        <a href="{{ route('units.edit', $unit) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل الوحدة">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('units.toggle-status', $unit) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $unit->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $unit->is_active ? 'تعطيل الوحدة' : 'تفعيل الوحدة' }}"
                                                    onclick="return confirm('{{ $unit->is_active ? 'هل أنت متأكد من تعطيل وحدة القياس هذه؟' : 'هل ترغب في إعادة تفعيل وحدة القياس هذه؟' }}');">
                                                <i class="fas {{ $unit->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-ruler fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد وحدات قياس مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($units->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $units->firstItem() }} إلى {{ $units->lastItem() }} من إجمالي {{ $units->total() }} وحدة
                </div>
                <div>
                    {{ $units->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
