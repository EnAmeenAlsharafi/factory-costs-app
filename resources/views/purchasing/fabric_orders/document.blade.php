{{--
    Fabric order sheet. One template for the on-screen preview and the mPDF download,
    so it uses table layout and plain inline-able CSS (mPDF has no flex, grid or CSS variables).
--}}
@php
    $forPdf = $forPdf ?? false;
    $primary = $company->primary_color ?: '#1d4ed8';
    $lines = $purchaseOrder->lines;
    $bedsOf = fn ($allocation) => (int) ($allocation->productionOrder?->released_quantity ?: $allocation->productionOrder?->ordered_quantity);
    $totalMeters = (float) $lines->sum('ordered_quantity');
    $totalBeds = $lines->sum(fn ($line) => $line->fabricAllocations->sum($bedsOf));
    $meters = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp
@include('documents.partials.styles')

<div class="fo">
    @include('documents.partials.header')

    {{-- 2. Title and document number --}}
    <table width="100%" style="margin-bottom: 10pt;">
        <tr>
            <td style="vertical-align: middle;">
                <div class="fo-title">طلب توريد قماش</div>
                <div style="font-size: 9pt; color: #475569;">يرجى توريد الأقمشة التالية حسب رقم الكتالوج ورقم اللون والكمية الموضحة.</div>
            </td>
            <td width="150" style="vertical-align: middle;">
                <table width="100%"><tr><td class="fo-docno">
                    <div style="font-size: 8pt; color: #64748b;">رقم الطلب</div>
                    <div style="font-size: 11.5pt; font-weight: bold; color: {{ $primary }};"><bdo dir="ltr">{{ $purchaseOrder->purchase_order_number }}</bdo></div>
                </td></tr></table>
            </td>
        </tr>
    </table>

    {{-- 3. Order facts --}}
    <table class="fo-info" width="100%" style="margin-bottom: 12pt;">
        <tr>
            <td><div style="font-size: 8pt; color: #64748b; padding-bottom: 2pt;">إلى المورد</div><div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">{{ $purchaseOrder->supplier?->name }}</div>@if ($purchaseOrder->supplier?->phone)<div style="font-size: 8.5pt; color: #475569;"><bdo dir="ltr">{{ $purchaseOrder->supplier->phone }}</bdo></div>@endif</td>
            <td><div style="font-size: 8pt; color: #64748b; padding-bottom: 2pt;">تاريخ الطلب</div><div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;"><bdo dir="ltr">{{ $purchaseOrder->order_date?->format('Y-m-d') }}</bdo></div></td>
            <td><div style="font-size: 8pt; color: #64748b; padding-bottom: 2pt;">جهة الاستلام</div><div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">المصنع</div></td>
            <td><div style="font-size: 8pt; color: #64748b; padding-bottom: 2pt;">الإجمالي</div><div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">{{ $meters($totalMeters) }} متر</div><div style="font-size: 8.5pt; color: #475569;">لـ {{ $totalBeds }} سرير</div></td>
        </tr>
    </table>

    {{-- 4. What to supply --}}
    <table class="fo-grid" width="100%">
        <thead>
            <tr>
                <th class="c" width="28">#</th>
                <th>رقم الكتالوج</th>
                <th>القماش</th>
                <th class="c">رقم اللون</th>
                <th>اللون</th>
                <th class="c">الأسرّة</th>
                <th class="c">الكمية (متر)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php
                    $spec = $line->material?->fabricSpec;
                    $catalogName = $spec?->catalog_name && $spec->catalog_name !== $line->material?->name_ar ? $spec->catalog_name : null;
                @endphp
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="c">{{ $loop->iteration }}</td>
                    <td><strong class="ltr">{{ $spec?->catalog_number ?: '—' }}</strong>@if ($catalogName)<div class="muted">{{ $catalogName }}</div>@endif</td>
                    <td>{{ $line->material?->name_ar }}</td>
                    <td class="c ltr"><strong>{{ $line->fabric_supplier_color_code ?: $line->fabric_color_code ?: '—' }}</strong></td>
                    <td>{{ $line->fabricColor?->color_name_ar ?? '—' }}</td>
                    <td class="c">{{ $line->fabricAllocations->sum($bedsOf) }}</td>
                    <td class="c"><strong>{{ $meters($line->ordered_quantity) }}</strong></td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5">الإجمالي</td>
                <td class="c">{{ $totalBeds }}</td>
                <td class="c">{{ $meters($totalMeters) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($purchaseOrder->notes)
        <div class="fo-notes" style="margin-top: 10pt;"><strong>ملاحظات:</strong> {{ $purchaseOrder->notes }}</div>
    @endif

    {{-- 5. Optional breakdown per production order (internal) --}}
    @if ($showBreakdown)
        <div class="fo-section">تفصيل الكمية حسب الأسرّة</div>
        <table class="fo-grid" width="100%">
            <thead>
                <tr>
                    <th>الكتالوج / اللون</th>
                    <th>أمر الإنتاج</th>
                    <th>المنتج</th>
                    <th>العميل</th>
                    <th class="c">الأسرّة</th>
                    <th class="c">متر</th>
                </tr>
            </thead>
            <tbody>
                @php $row = 0; @endphp
                @foreach ($lines as $line)
                    @foreach ($line->fabricAllocations as $allocation)
                        <tr class="{{ ++$row % 2 === 0 ? 'alt' : '' }}">
                            <td class="ltr" style="text-align: right;">{{ $line->material?->fabricSpec?->catalog_number }} / <strong>{{ $line->fabric_supplier_color_code ?: $line->fabric_color_code }}</strong></td>
                            <td class="ltr" style="text-align: right;">{{ $allocation->productionOrder?->production_order_number }}</td>
                            <td>{{ $allocation->productionOrder?->display_name }}</td>
                            <td>{{ $allocation->productionOrder?->customerOrder?->customer?->name }}</td>
                            <td class="c">{{ $bedsOf($allocation) }}</td>
                            <td class="c">{{ $meters($allocation->meters) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    @include('documents.partials.footer', ['preparedBy' => $purchaseOrder->createdByUser?->name])
</div>
