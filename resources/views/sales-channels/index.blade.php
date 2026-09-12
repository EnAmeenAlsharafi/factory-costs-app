@extends('layouts.app')

@section('title', 'قنوات البيع - مصنع مفروشات سدير')
@section('page-title', 'إدارة قنوات البيع والتوزيع')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'قنوات البيع (Sales Channels)',
        'subtitle' => 'تحديد مسارات البيع (متجر مفروشات سدير، تجار الجملة، البيع المباشر، والمشاريع الخاصة).',
        'icon' => 'fas fa-store',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'قنوات البيع']
        ],
        'actionUrl' => route('sales-channels.create'),
        'actionText' => 'إضافة قناة بيع جديدة',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'sales_channels.manage'
    ])

    <!-- Channels Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الرمز الكودي</th>
                        <th>اسم القناة (عربي)</th>
                        <th>الاسم الإنجليزي</th>
                        <th>ترتيب العرض</th>
                        <th>عدد العملاء المرتبطين</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($channels as $channel)
                        <tr>
                            <td>
                                <code class="code-badge">{{ $channel->code }}</code>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $channel->name_ar }}</div>
                                @if ($channel->description)
                                    <small class="text-muted fs-8 d-block">{{ $channel->description }}</small>
                                @endif
                            </td>
                            <td>
                                <span dir="ltr" class="text-muted fs-7">{{ $channel->name_en ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $channel->sort_order }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                    <i class="fas fa-users me-1 text-muted"></i> {{ $channel->customers_count }} عميل
                                </span>
                            </td>
                            <td>
                                @if ($channel->is_active)
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
                                    @can('sales_channels.manage')
                                        <a href="{{ route('sales-channels.edit', $channel) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل القناة">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('sales-channels.toggle-status', $channel) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $channel->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $channel->is_active ? 'تعطيل القناة' : 'تفعيل القناة' }}"
                                                    onclick="return confirm('{{ $channel->is_active ? 'هل أنت متأكد من تعطيل هذه القناة؟' : 'هل ترغب في إعادة تفعيل هذه القناة؟' }}');">
                                                <i class="fas {{ $channel->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-store-slash fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد قنوات بيع مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($channels->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $channels->firstItem() }} إلى {{ $channels->lastItem() }} من إجمالي {{ $channels->total() }} قناة
                </div>
                <div>
                    {{ $channels->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
