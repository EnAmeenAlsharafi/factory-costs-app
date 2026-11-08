{{-- Mattress production request sent to the supplier (mPDF). --}}
@php
    $lines = $productionRequest->lines;
    $total = (int) $lines->sum('quantity');
    $productOf = fn ($material) => trim(preg_replace('/\s*\|\s*مقاسات متعددة\s*$/u', '', $material?->readyParent?->name_ar ?? $material?->name_ar ?? ''));
@endphp
@include('documents.partials.styles')

<div class="fo">
    @include('documents.partials.header')

    @include('documents.partials.title', [
        'title' => 'طلب إنتاج مراتب',
        'subtitle' => 'يرجى إنتاج الكميات التالية لصالحنا، وتُحفظ لديكم حتى نطلب توريدها.',
        'numberLabel' => 'رقم الطلب',
        'number' => $productionRequest->request_number,
        'facts' => [
            ['إلى المورد', e($productionRequest->supplier?->name), $productionRequest->supplier?->phone ? '<bdo dir="ltr">'.e($productionRequest->supplier->phone).'</bdo>' : null],
            ['تاريخ الطلب', '<bdo dir="ltr">'.$productionRequest->request_date?->format('Y-m-d').'</bdo>'],
            ['الحالة', e(\App\Models\SupplierProductionRequest::STATUS_LABELS[$productionRequest->status][0] ?? $productionRequest->status)],
            ['الإجمالي', $total.' مرتبة', $lines->count().' مقاس'],
        ],
    ])

    <table class="fo-grid" width="100%">
        <thead>
            <tr>
                <th class="c" width="28">#</th>
                <th>نوع المرتبة</th>
                <th class="c">المقاس</th>
                <th class="c">الكمية المطلوب إنتاجها</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $productOf($line->material) }}</td>
                    <td class="c"><strong><bdo dir="ltr">{{ $line->material?->ready_option_label ?: '—' }}</bdo></strong></td>
                    <td class="c"><strong>{{ $line->quantity }}</strong></td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">الإجمالي</td>
                <td class="c">{{ $total }}</td>
            </tr>
        </tbody>
    </table>

    @if ($productionRequest->notes)
        <div class="fo-notes" style="margin-top: 10pt;"><strong>ملاحظات:</strong> {{ $productionRequest->notes }}</div>
    @endif

    @include('documents.partials.footer', ['preparedBy' => $productionRequest->createdByUser?->name])
</div>
