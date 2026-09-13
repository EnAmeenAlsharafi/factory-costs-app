<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أمر شراء - {{ $purchaseOrder->purchase_order_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #fff; color: #000; }
        .print-header { border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .table-bordered th, .table-bordered td { border: 1px solid #000 !important; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="p-4" onload="window.print()">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary">طباعة أمر الشراء</button>
    </div>

    <!-- Header -->
    <div class="print-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-1">مصنع مفروشات سدير</h2>
            <h5 class="text-secondary mb-0">أمر شراء تجاري / Purchase Order</h5>
        </div>
        <div class="text-end">
            <h4 class="fw-bold text-primary mb-1">{{ $purchaseOrder->purchase_order_number }}</h4>
            <div class="small">التاريخ: {{ $purchaseOrder->order_date->format('Y-m-d') }}</div>
        </div>
    </div>

    <!-- Details Info Grid -->
    <div class="row mb-4">
        <div class="col-6">
            <div class="border p-3 rounded">
                <h6 class="fw-bold mb-2">بيانات المورد (Supplier):</h6>
                <div><strong>الاسم:</strong> {{ $purchaseOrder->supplier?->name }}</div>
                <div><strong>رقم الهاتف:</strong> {{ $purchaseOrder->supplier?->phone ?? '-' }}</div>
                <div><strong>مرجع المورد:</strong> {{ $purchaseOrder->supplier_reference ?? '-' }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="border p-3 rounded">
                <h6 class="fw-bold mb-2">بيانات الشحن والتسليم (Delivery):</h6>
                <div><strong>المستودع:</strong> {{ $purchaseOrder->warehouse?->name_ar }}</div>
                <div><strong>تاريخ التسليم المتوقع:</strong> {{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('Y-m-d') : '-' }}</div>
                <div><strong>شروط الدفع:</strong> {{ $purchaseOrder->payment_terms ?? 'نقداً' }}</div>
            </div>
        </div>
    </div>

    <!-- Lines Table -->
    <table class="table table-bordered align-middle text-center mb-4">
        <thead class="bg-light">
            <tr>
                <th width="40">#</th>
                <th class="text-start">وصف الخامة / المادة</th>
                <th>لون القماش</th>
                <th>الكمية المطلوبة</th>
                <th>الوحدة</th>
                <th>سعر الوحدة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->lines as $idx => $line)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td class="text-start fw-bold">{{ $line->material?->name_ar }}</td>
                <td>{{ $line->fabricColor?->color_name_ar ?? '-' }}</td>
                <td class="fw-bold">{{ number_format($line->ordered_quantity, 2) }}</td>
                <td>{{ $line->purchaseUnit?->name_ar }}</td>
                <td>{{ number_format($line->unit_price, 2) }} SAR</td>
                <td class="fw-bold">{{ number_format($line->line_total, 2) }} SAR</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Financial Totals -->
    <div class="row">
        <div class="col-6">
            @if($purchaseOrder->notes)
            <div class="border p-3 rounded small">
                <strong>ملاحظات وتعليمات التوريد:</strong>
                <div>{{ $purchaseOrder->notes }}</div>
            </div>
            @endif
        </div>
        <div class="col-6">
            <table class="table table-bordered">
                <tr>
                    <td>المجموع الفرعي:</td>
                    <td class="fw-bold text-end">{{ number_format($purchaseOrder->subtotal, 2) }} SAR</td>
                </tr>
                @if($purchaseOrder->discount_amount > 0)
                <tr>
                    <td>الخصم:</td>
                    <td class="fw-bold text-end text-danger">-{{ number_format($purchaseOrder->discount_amount, 2) }} SAR</td>
                </tr>
                @endif
                @if($purchaseOrder->shipping_amount > 0)
                <tr>
                    <td>أجور الشحن والنقل:</td>
                    <td class="fw-bold text-end">+{{ number_format($purchaseOrder->shipping_amount, 2) }} SAR</td>
                </tr>
                @endif
                <tr class="table-dark">
                    <td class="fw-bold">الإجمالي النهائي:</td>
                    <td class="fw-bold text-end fs-5">{{ number_format($purchaseOrder->total_amount, 2) }} SAR</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Signatures -->
    <div class="row mt-5 pt-4 text-center">
        <div class="col-4">
            <div class="fw-bold">إعداد المشتريات</div>
            <div class="mt-4 text-muted">{{ $purchaseOrder->createdByUser?->name }}</div>
        </div>
        <div class="col-4">
            <div class="fw-bold">اعتماد الإدارة / المصنع</div>
            <div class="mt-4 text-muted">{{ $purchaseOrder->approvedByUser?->name ?? 'مستند معتمد إلكترونياً' }}</div>
        </div>
        <div class="col-4">
            <div class="fw-bold">موافقة وتوقيع المورد</div>
            <div class="mt-4 text-muted">...............................</div>
        </div>
    </div>

</body>
</html>
