<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال استلام دفعة - {{ $payment->payment_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #fff; color: #1e293b; font-size: 14px; }
        .receipt-card { max-width: 800px; margin: 20px auto; border: 2px solid #cbd5e1; border-radius: 12px; padding: 30px; }
        .disclaimer-box { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 8px; padding: 12px; font-size: 12px; color: #64748b; }
        @media print {
            .no-print { display: none !important; }
            .receipt-card { border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-warning fw-bold px-4">
            طباعة الإيصال
        </button>
        <button onclick="window.close()" class="btn btn-secondary px-4 ms-2">إغلاق</button>
    </div>

    <div class="receipt-card">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-1">مصنع مفروشات سدير</h2>
                <div class="text-muted small">إدارة الإنتاج والتحصيل الميداني</div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-warning mb-1">إيصال استلام دفعة</h4>
                <div class="font-monospace fw-bold fs-5 text-dark">{{ $payment->payment_number }}</div>
                <div class="text-muted small">تاريخ الإيصال: {{ $payment->payment_date->format('Y-m-d') }}</div>
            </div>
        </div>

        {{-- Main Details --}}
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="p-3 bg-light rounded-3">
                    <div class="text-muted small mb-1">اسم العميل:</div>
                    <div class="fw-bold fs-5 text-dark">{{ $payment->customer->name ?? '-' }}</div>
                    <div class="small text-muted font-monospace">{{ $payment->customer->customer_code ?? '' }}</div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 bg-light rounded-3">
                    <div class="text-muted small mb-1">المبلغ المقبوض:</div>
                    <div class="fw-bold fs-4 text-success">{{ number_format($payment->amount, 2) }} ر.س</div>
                    <div class="small text-muted">طريقة الدفع: 
                        @switch($payment->payment_method)
                            @case('CASH') نقداً @break
                            @case('BANK_TRANSFER') تحويل بنكي @break
                            @case('CARD') بطاقة شبكة @break
                            @case('CHEQUE') شيك مصرفي @break
                            @default {{ $payment->payment_method }}
                        @endswitch
                    </div>
                </div>
            </div>
        </div>

        {{-- Reference info --}}
        <table class="table table-bordered align-middle mb-4">
            <tr>
                <th class="bg-light text-muted w-25">المرجع / رقم العملية:</th>
                <td class="font-monospace fw-bold">{{ $payment->reference_number ?: '-' }}</td>
                <th class="bg-light text-muted w-25">البنك / الحساب:</th>
                <td>{{ $payment->bank_reference ?: '-' }}</td>
            </tr>
            <tr>
                <th class="bg-light text-muted">مستلم الدفعة:</th>
                <td>{{ $payment->createdBy->name ?? '-' }}</td>
                <th class="bg-light text-muted">حالة الدفعة:</th>
                <td>
                    @if ($payment->status === 'CONFIRMED')
                        <span class="badge bg-success">مؤكدة</span>
                    @else
                        <span class="badge bg-warning text-dark">قيد التأكيد</span>
                    @endif
                </td>
            </tr>
        </table>

        {{-- Linked Orders --}}
        <h6 class="fw-bold text-dark mb-2">تخصيص الدفعة على الطلبات:</h6>
        <table class="table table-sm table-bordered align-middle mb-4">
            <thead class="bg-light">
                <tr>
                    <th>رقم الطلب</th>
                    <th>نوع التخصيص</th>
                    <th class="text-end">المبلغ المخصص</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payment->allocations as $alloc)
                    <tr>
                        <td class="font-monospace fw-bold">{{ $alloc->order ? $alloc->order->order_number : '#'.$alloc->customer_order_id }}</td>
                        <td>{{ $alloc->allocation_type }}</td>
                        <td class="text-end fw-bold">{{ number_format($alloc->allocated_amount, 2) }} ر.س</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">لم يتم تخصيص الدفعة على طلب محدد حتى الآن (رصيد متاح في حساب العميل).</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Signatures --}}
        <div class="row text-center mt-5 pt-3">
            <div class="col-6">
                <div class="fw-bold mb-4">المستلم / أمين التحصيل</div>
                <div class="text-muted">_______________________</div>
            </div>
            <div class="col-6">
                <div class="fw-bold mb-4">توقيع العميل (اختياري)</div>
                <div class="text-muted">_______________________</div>
            </div>
        </div>

        {{-- Mandatory Operational Disclaimer --}}
        <div class="disclaimer-box text-center mt-5">
            <strong>ملاحظة هامة:</strong> هذا الإيصال تشغيلي مخصص لإثبات المقبوضات التجارية داخل مصنع مفروشات سدير، ولا يُعد فاتورة ضريبية رسمية.
        </div>
    </div>
</body>
</html>
