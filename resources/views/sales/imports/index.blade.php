@extends('layouts.app')

@section('title', 'استيراد طلبات سلة')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2 fs-8">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">استيراد طلبات سلة</li>
                </ol>
            </nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">سجل استيراد طلبات سلة</h1>
        </div>

        @can('sales_imports.manage')
            <a href="{{ route('sales.imports.create') }}" class="btn btn-factory-warning shadow-sm">
                <i class="fas fa-upload me-1"></i> رفع ملف جديد
            </a>
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
                            <th>الملف</th>
                            <th>قناة البيع</th>
                            <th>رفعه</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">تم إنشاؤه</th>
                            <th class="text-center">تم تجاهله</th>
                            <th class="text-center">فشل/مكرر</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            <tr>
                                <td class="fw-bold text-dark">{{ $batch->file_name }}</td>
                                <td>{{ $batch->salesChannel->name_ar ?? '-' }}</td>
                                <td>{{ $batch->uploadedByUser->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">{{ $batch->status }}</span>
                                </td>
                                <td class="text-center fw-mono text-success">{{ $batch->created_count }}</td>
                                <td class="text-center fw-mono text-muted">{{ $batch->skipped_count }}</td>
                                <td class="text-center fw-mono text-danger">{{ $batch->failed_count }}</td>
                                <td class="text-center">
                                    @if($batch->status === 'UPLOADED')
                                        <a href="{{ route('sales.imports.preview', $batch) }}" class="btn btn-sm btn-outline-primary">معاينة</a>
                                    @else
                                        <a href="{{ route('sales.imports.result', $batch) }}" class="btn btn-sm btn-outline-secondary">النتيجة</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا توجد دفعات استيراد مسجلة حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($batches->hasPages())
            <div class="card-footer bg-white">
                {{ $batches->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
