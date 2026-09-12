@extends('layouts.app')

@section('title', 'أعمال الأقسام (WIP) - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-tasks text-primary me-2"></i>أعمال الأقسام وتشغيل الورش (WIP Queue)</h4>
            <p class="text-muted mb-0">قائمة العمليات المتاحة والتنفيذية لكل قسم مصنعي لتحديث كميات الإنجاز</p>
        </div>
        <a href="{{ route('production.board.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-chart-kanban me-1"></i> لوحة متابعة الإنتاج
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('production.queue.index') }}" method="GET" class="row g-3">
                @if(auth()->user()->isAdministrator() || auth()->user()->hasRole('production_manager'))
                    <div class="col-md-6">
                        <select name="department_id" class="form-select">
                            <option value="">جميع الأقسام التشغيلية</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                    قسم {{ $dept->name_ar }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-md-6">
                        <div class="form-control bg-light text-muted">
                            <i class="fas fa-building me-1"></i> قسمك التشغيلي: <strong>{{ auth()->user()->department?->name_ar ?? 'غير محدد' }}</strong>
                        </div>
                    </div>
                @endif
                <div class="col-md-4">
                    <select name="priority" class="form-select">
                        <option value="">جميع الأولويات</option>
                        <option value="NORMAL" {{ request('priority') === 'NORMAL' ? 'selected' : '' }}>عادي</option>
                        <option value="URGENT" {{ request('priority') === 'URGENT' ? 'selected' : '' }}>عاجل</option>
                        <option value="VIP" {{ request('priority') === 'VIP' ? 'selected' : '' }}>VIP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> تصفية</button>
                    <a href="{{ route('production.queue.index') }}" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Operations Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>المرحلة / العملية</th>
                            <th>القسم التشغيلي</th>
                            <th>المنتج والمواصفات</th>
                            <th>القماش واللون</th>
                            <th class="text-center">المطلوب / المنجز</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراء السريع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($operations as $op)
                            @php
                                $po = $op->productionOrder;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('production.orders.show', $po) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                        {{ $po->production_order_number }}
                                    </a>
                                    <small class="text-muted d-block">{{ $po->customerOrder?->customer?->name_ar }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $op->operation_name_snapshot }}</div>
                                    <small class="text-muted">تسلسل #{{ $op->sequence_number }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-building me-1"></i>{{ $op->workCenter?->department?->name_ar }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $po->is_custom_design ? $po->custom_design_name : $po->productModel?->name_ar }}</span>
                                    <small class="text-muted d-block">{{ (int)$po->requested_width_cm }}×{{ (int)$po->requested_length_cm }} سم {{ $po->has_storage ? '(سحارة)' : '' }}</small>
                                </td>
                                <td>
                                    <small class="d-block text-dark">{{ $po->fabricMaterial?->name_ar ?? '-' }}</small>
                                    @if($po->fabricColor)
                                        <small class="text-muted"><i class="fas fa-circle me-1" style="color: {{ $po->fabricColor->hex_code ?? '#ccc' }};"></i>{{ $po->fabricColor->color_name_ar }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-success fs-6">{{ $op->completed_quantity }}</span>
                                    <span class="text-muted fs-7">/ {{ $op->required_quantity }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $op->status_badge_class }}">{{ $op->status_arabic }}</span>
                                </td>
                                <td class="text-end">
                                    @can('production.update_progress')
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#queueProgressModal-{{ $op->id }}">
                                            <i class="fas fa-check-circle me-1"></i> تسجيل إنجاز
                                        </button>

                                        <!-- Progress Modal -->
                                        <div class="modal fade text-start" id="queueProgressModal-{{ $op->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('production.operations.progress', $op) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">تسجيل إنجاز: {{ $op->operation_name_snapshot }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block">أمر الإنتاج:</small>
                                                                <span class="fw-bold text-primary font-monospace">{{ $po->production_order_number }}</span>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">المنجز المكتمل حالياً:</label>
                                                                <div class="fw-bold text-success fs-5">{{ $op->completed_quantity }} من أصل {{ $op->required_quantity }} قطعة</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label required">الكمية المنجزة إضافياً الآن</label>
                                                                <input type="number" name="added_quantity" class="form-control form-control-lg" min="1" max="{{ $op->required_quantity - $op->completed_quantity }}" value="1" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">ملاحظات التشغيل</label>
                                                                <input type="text" name="notes" class="form-control" placeholder="أي ملاحظات فنية...">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                                            <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> تأكيد الإنجاز</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-check-double fa-2x mb-2 d-block text-success"></i>
                                    لا توجد عمليات معلقة أو قيد الانتظار لهذا القسم حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($operations->hasPages())
            <div class="card-footer bg-white">
                {{ $operations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
