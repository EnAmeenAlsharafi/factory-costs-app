@extends('layouts.app')

@section('title', 'صرف خامات إنتاج من المستودع - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">صرف خامات مخزنية لطلب: <span class="font-monospace text-primary">{{ $materialRequest->request_number }}</span></h4>
            <p class="text-muted mb-0 mt-1">المستودع: <strong>{{ $materialRequest->warehouse?->name_ar }}</strong> &bull; أمر الإنتاج: <span class="font-monospace fw-bold">{{ $materialRequest->productionOrder->production_order_number }}</span></p>
        </div>
        <a href="{{ route('production.material-requests.show', $materialRequest) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> إلغاء وعودة
        </a>
    </div>

    <form action="{{ route('production.material-requests.fulfill', $materialRequest) }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-success"><i class="fas fa-dolly me-1"></i> اختيار دفعات (Lots) الخامات المخزنية المتوفرة بالصرف (FIFO)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>البند المطلوب</th>
                                <th>الكمية المتبقية للصرف</th>
                                <th>دفعة المخزون (Lot Code)</th>
                                <th>الرصيد المتاح بالدفعة</th>
                                <th style="width: 200px;">الكمية المصروفة الآن</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $fulfillmentIndex = 0; @endphp
                            @foreach($materialRequest->lines as $line)
                                @php
                                    $remainingToIssue = max(0, ($line->approved_quantity ?? $line->requested_quantity) - $line->issued_quantity);
                                    $lineLots = $lots->where('material_id', $line->material_id);
                                @endphp

                                @if($remainingToIssue > 0)
                                    @forelse($lineLots as $lot)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="fulfillments[{{ $fulfillmentIndex }}][request_line_id]" value="{{ $line->id }}">
                                                <div class="fw-bold">{{ $line->material?->name_ar }}</div>
                                                <small class="text-muted">{{ $line->request_reason }}</small>
                                            </td>
                                            <td class="fw-bold text-primary">{{ (float)$remainingToIssue }} {{ $line->baseUnit?->name_ar }}</td>
                                            <td>
                                                <input type="hidden" name="fulfillments[{{ $fulfillmentIndex }}][inventory_lot_id]" value="{{ $lot->id }}">
                                                <span class="badge bg-light text-dark border font-monospace fs-7"><i class="fas fa-box me-1"></i>{{ $lot->lot_code }}</span>
                                                @if($lot->fabricColor)
                                                    <span class="badge bg-light text-dark border ms-1">{{ $lot->fabricColor->color_name_ar }}</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-success fs-7">{{ (float)$lot->remaining_quantity }} {{ $lot->baseUnit?->name_ar }}</span></td>
                                            <td>
                                                @php
                                                    $suggestedQty = min($remainingToIssue, $lot->remaining_quantity);
                                                @endphp
                                                <input type="number" step="0.0001" name="fulfillments[{{ $fulfillmentIndex }}][quantity]" class="form-control form-control-sm fw-bold" value="{{ (float)$suggestedQty }}" max="{{ (float)$lot->remaining_quantity }}" min="0.0001" required>
                                            </td>
                                            <td>
                                                <input type="text" name="fulfillments[{{ $fulfillmentIndex }}][notes]" class="form-control form-control-sm" placeholder="ملاحظات اختيارية">
                                            </td>
                                        </tr>
                                        @php $fulfillmentIndex++; @endphp
                                    @empty
                                        <tr class="table-warning">
                                            <td>
                                                <div class="fw-bold">{{ $line->material?->name_ar }}</div>
                                            </td>
                                            <td class="fw-bold text-danger">{{ (float)$remainingToIssue }} {{ $line->baseUnit?->name_ar }}</td>
                                            <td colspan="4" class="text-danger fw-bold">
                                                <i class="fas fa-exclamation-triangle me-1"></i> لا تتوفر دفعات active رصيدها أكبر من 0 في هذا المستودع!
                                            </td>
                                        </tr>
                                    @endforelse
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-check-circle me-1"></i> تأكيد صرف الخامات وإنشاء سند الصرف المخزني
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
