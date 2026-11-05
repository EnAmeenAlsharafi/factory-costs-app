{{--
    Phone (< 768px): each line becomes a stacked card via `.table-stack-sm` — same DOM, no duplicated markup.
    `.stack-only` content shows only in the mobile card header; `.stack-hide-sm` hides the cell it duplicates.
--}}
<div class="table-responsive">
    <table class="table table-stack-sm align-middle mb-0">
        <thead>
            <tr>
                <th>المنتج</th>
                <th>المواصفات</th>
                <th>الكمية</th>
                @if($showPrices)<th>سعر الوحدة</th><th>خصم البند</th><th>الإجمالي</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($submission->lines as $line)
                @php($lineTotal = ($line['unit_price'] ?? 0) * $line['quantity'] - ($line['discount_amount'] ?? 0))
                <tr>
                    <td class="stack-head">
                        <span class="min-w-0">{{ $line['product_name'] ?? 'منتج' }}</span>
                        @if($showPrices)
                            <span class="stack-only fw-bold flex-shrink-0">{{ number_format($lineTotal, 2) }} ر.س</span>
                        @endif
                    </td>
                    <td data-label="المواصفات">
                        @if($line['line_type'] === 'MANUFACTURED')
                            <span class="ltr-isolate">{{ $line['requested_width_cm'] }} × {{ $line['requested_length_cm'] }}</span> سم
                            <br>{{ ! empty($line['has_storage']) ? 'مع تخزين' : 'بدون تخزين' }}
                            <br>{{ $line['fabric_name'] ?? 'القماش غير محدد' }} / {{ $line['color_name'] ?? 'اللون غير محدد' }}
                        @else
                            منتج جاهز
                        @endif
                        @if(! empty($line['notes']))
                            <small class="d-block text-muted mt-1">{{ $line['notes'] }}</small>
                        @endif
                    </td>
                    <td data-label="الكمية" class="stack-half">{{ $line['quantity'] }}</td>
                    @if($showPrices)
                        <td data-label="سعر الوحدة" class="stack-half">{{ number_format($line['unit_price'] ?? 0, 2) }}</td>
                        <td data-label="خصم البند" class="stack-half">{{ number_format($line['discount_amount'] ?? 0, 2) }}</td>
                        <td data-label="إجمالي البند" class="stack-hide-sm">{{ number_format($lineTotal, 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
