<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب قماش {{ $purchaseOrder->purchase_order_number }} - {{ $purchaseOrder->supplier?->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="doc-body" style="background: #eef2f7;">
    <div class="doc-toolbar">
        <a href="{{ route('purchasing.fabric-orders.index') }}" class="btn btn-light border"><i class="fas fa-arrow-right me-1" aria-hidden="true"></i> عودة</a>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ request()->fullUrlWithQuery(['details' => $showBreakdown ? 0 : 1]) }}" class="btn btn-outline-secondary">
                {{ $showBreakdown ? 'إخفاء تفصيل الأسرّة' : 'إظهار تفصيل الأسرّة' }}
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i> طباعة</button>
            <a href="{{ route('purchasing.fabric-orders.pdf', ['purchaseOrder' => $purchaseOrder, 'details' => $showBreakdown ? 1 : 0]) }}" class="btn btn-primary fw-bold">
                <i class="fas fa-file-pdf me-1" aria-hidden="true"></i> تنزيل PDF
            </a>
        </div>
    </div>

    <main class="doc-page shadow-sm">
        @include('purchasing.fabric_orders.document')
    </main>
</body>
</html>
