<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>المنتج</th><th>المواصفات</th><th>الكمية</th>@if($showPrices)<th>سعر الوحدة</th><th>خصم البند</th><th>الإجمالي</th>@endif</tr></thead>
    <tbody>@foreach($submission->lines as $line)<tr>
        <td>{{ $line['product_name'] ?? 'منتج' }}<small class="d-block text-muted">{{ $line['notes'] ?? '' }}</small></td>
        <td>@if($line['line_type'] === 'MANUFACTURED')<bdi>{{ $line['requested_width_cm'] }} × {{ $line['requested_length_cm'] }}</bdi> سم<br>{{ !empty($line['has_storage']) ? 'مع تخزين' : 'بدون تخزين' }}<br>{{ $line['fabric_name'] ?? 'القماش غير محدد' }} / {{ $line['color_name'] ?? 'اللون غير محدد' }}@else منتج جاهز @endif</td>
        <td>{{ $line['quantity'] }}</td>
        @if($showPrices)<td>{{ number_format($line['unit_price'] ?? 0, 2) }}</td><td>{{ number_format($line['discount_amount'] ?? 0, 2) }}</td><td>{{ number_format(($line['unit_price'] ?? 0) * $line['quantity'] - ($line['discount_amount'] ?? 0), 2) }}</td>@endif
    </tr>@endforeach</tbody>
</table></div>
