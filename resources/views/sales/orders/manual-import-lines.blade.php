@if($order->manual_import_lines)
<div class="alert alert-warning">
    <h2 class="h6 fw-bold">بنود سلة المطلوب إضافتها يدويًا</h2>
    <p>أُضيفت المنتجات البسيطة المطابقة فقط. أضف البنود التالية بمقاساتها ومواصفاتها الصحيحة، ثم راجع إجمالي الطلب والدفعات. الإجمالي الحالي لا يشمل هذه البنود.</p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>المنتج في سلة</th><th>SKU</th><th>الكمية</th>@can('orders.update')<th>سعر الوحدة قبل الخصم</th>@endcan</tr></thead><tbody>
    @foreach($order->manual_import_lines as $pendingLine)
        <tr><td>{{ $pendingLine['product_name'] ?? 'غير محدد' }}</td><td>{{ $pendingLine['sku'] ?? '—' }}</td><td>{{ $pendingLine['quantity'] ?? '—' }}</td>@can('orders.update')<td>{{ $pendingLine['unit_price'] ?? '—' }}</td>@endcan</tr>
    @endforeach
    </tbody></table></div>
</div>
@endif
