{{-- Mattress supply order sent to the supplier (mPDF). --}}
@php
    $lines = $purchaseOrder->lines;
    $total = (float) $lines->sum('ordered_quantity');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $productOf = fn ($material) => trim(preg_replace('/\s*\|\s*مقاسات متعددة\s*$/u', '', $material?->readyParent?->name_ar ?? $material?->name_ar ?? ''));
@endphp
@include('documents.partials.styles')

<div class="fo">
    @include('documents.partials.header')

    @include('documents.partials.title', [
        'title' => 'طلب توريد مراتب',
        'subtitle' => 'يرجى توريد المراتب التالية حسب النوع والمقاس والكمية الموضحة.',
        'numberLabel' => 'رقم الطلب',
        'number' => $purchaseOrder->purchase_order_number,
        'facts' => [
            ['إلى المورد', e($purchaseOrder->supplier?->name), $purchaseOrder->supplier?->phone ? '<bdo dir="ltr">'.e($purchaseOrder->supplier->phone).'</bdo>' : null],
            ['تاريخ الطلب', '<bdo dir="ltr">'.$purchaseOrder->order_date?->format('Y-m-d').'</bdo>'],
            ['جهة الاستلام', 'المصنع'],
            ['الإجمالي', $qty($total).' مرتبة', $lines->count().' مقاس'],
        ],
    ])

    <table class="fo-grid" width="100%">
        <thead>
            <tr>
                <th class="c" width="28">#</th>
                <th>نوع المرتبة</th>
                <th class="c">المقاس</th>
                <th class="c">عدد الطلبات</th>
                <th class="c">الكمية</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $productOf($line->material) }}</td>
                    <td class="c"><strong><bdo dir="ltr">{{ $line->material?->ready_option_label ?: '—' }}</bdo></strong></td>
                    <td class="c">{{ $line->readyProductAllocations->count() }}</td>
                    <td class="c"><strong>{{ $qty($line->ordered_quantity) }}</strong></td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">الإجمالي</td>
                <td class="c">{{ $qty($total) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($purchaseOrder->notes)
        <div class="fo-notes" style="margin-top: 10pt;"><strong>ملاحظات:</strong> {{ $purchaseOrder->notes }}</div>
    @endif

    @if ($showBreakdown ?? true)
        <div class="fo-section">تفصيل الكمية حسب طلبات العملاء</div>
        <table class="fo-grid" width="100%">
            <thead>
                <tr>
                    <th>المرتبة / المقاس</th>
                    <th>طلب العميل</th>
                    <th>العميل</th>
                    <th class="c">الكمية</th>
                </tr>
            </thead>
            <tbody>
                @php $row = 0; @endphp
                @foreach ($lines as $line)
                    @foreach ($line->readyProductAllocations as $allocation)
                        <tr class="{{ ++$row % 2 === 0 ? 'alt' : '' }}">
                            <td>{{ $productOf($line->material) }} <strong><bdo dir="ltr">{{ $line->material?->ready_option_label }}</bdo></strong></td>
                            <td><bdo dir="ltr">{{ $allocation->customerOrderLine?->customerOrder?->order_number }}</bdo></td>
                            <td>{{ $allocation->customerOrderLine?->customerOrder?->customer?->name }}</td>
                            <td class="c">{{ $qty($allocation->quantity) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    @include('documents.partials.footer', ['preparedBy' => $purchaseOrder->createdByUser?->name])
</div>
