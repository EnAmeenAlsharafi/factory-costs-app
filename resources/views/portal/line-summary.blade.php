<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>المنتج</th><th>المواصفات</th><th>الكمية</th>@if($showPrices)<th>سعر الوحدة</th><th>خصم البند</th><th>الإجمالي</th>@endif</tr></thead>
    <tbody>@foreach($submission->lines as $line)<tr>
        <td>{{ $line['product_name'] ?? 'منتج' }}<small class="d-block text-muted">{{ $line['notes'] ?? '' }}</small>
            @if(!empty($line['custom_design']))<span class="badge bg-info-subtle text-info">تصميم خاص حسب الطلب</span>@endif
            @if(!empty($line['custom_design_image_path']) && !empty($line['line_key']))
                <a href="{{ route(auth()->user()->isCustomerAccount() ? 'portal.requests.design-image' : 'sales.portal-requests.design-image', [$submission, $line['line_key']]) }}" target="_blank" rel="noopener" class="d-block mt-2">
                    <img src="{{ route(auth()->user()->isCustomerAccount() ? 'portal.requests.design-image' : 'sales.portal-requests.design-image', [$submission, $line['line_key']]) }}" alt="صورة التصميم الخاص" class="img-thumbnail" style="max-width:140px;max-height:120px;object-fit:contain;">
                </a>
            @endif
            @if(!empty($line['requested_fabric']) || !empty($line['requested_color']))
                <small class="d-block mt-2">طلب العميل: {{ $line['requested_fabric'] ?? '' }} / {{ $line['requested_color'] ?? '' }}</small>
            @endif
        </td>
        <td>@if($line['line_type'] === 'MANUFACTURED')<bdi>{{ $line['requested_width_cm'] }} × {{ $line['requested_length_cm'] }}</bdi> سم<br>{{ !empty($line['has_storage']) ? 'مع تخزين' : 'بدون تخزين' }}<br>{{ $line['fabric_name'] ?? 'القماش غير محدد' }} / {{ $line['color_name'] ?? 'اللون غير محدد' }}@else منتج جاهز @endif</td>
        <td>{{ $line['quantity'] }}</td>
        @if($showPrices)<td>{{ number_format($line['unit_price'] ?? 0, 2) }}</td><td>{{ number_format($line['discount_amount'] ?? 0, 2) }}</td><td>{{ number_format(($line['unit_price'] ?? 0) * $line['quantity'] - ($line['discount_amount'] ?? 0), 2) }}</td>@endif
    </tr>@endforeach</tbody>
</table></div>
