@extends('layouts.app')

@section('title', 'صرف مواد - ' . $materialRequest->request_number)
@section('page-title', 'صرف المواد')

@section('content')
@php
    $po = $materialRequest->productionOrder;
    $canViewCost = auth()->user()->can('costing.view');
    $formLines = [];
    $rowIndex = 0;
    $lineViews = [];

    foreach ($materialRequest->lines as $line) {
        $remainingToIssue = max(0, (float) ($line->approved_quantity ?? $line->requested_quantity) - (float) $line->issued_quantity);
        if ($remainingToIssue <= 0) {
            continue;
        }

        $reqColorCode = $line->fabric_color_code ?? $po?->fabric_color_code ?? $line->fabricColor?->color_code;
        $lineLots = $lots->where('material_id', $line->material_id);
        if (! empty($reqColorCode)) {
            $lineLots = $lineLots->filter(function ($lot) use ($reqColorCode) {
                $lotColor = $lot->fabric_color_code ?? $lot->fabricColor?->color_code;

                return empty($lotColor) || $lotColor === $reqColorCode;
            });
        }

        // FIFO pre-allocation: fill the oldest lots first, never exceeding what the line still needs.
        $stillNeeded = $remainingToIssue;
        $lotViews = [];
        foreach ($lineLots->values() as $lot) {
            $suggested = min($stillNeeded, (float) $lot->remaining_quantity);
            $stillNeeded = max(0, $stillNeeded - $suggested);
            $lotViews[] = ['lot' => $lot, 'row' => $rowIndex, 'suggested' => $suggested];
            $formLines[$line->id]['lots'][$rowIndex] = ['on' => $suggested > 0, 'qty' => $suggested > 0 ? $suggested : null, 'max' => (float) $lot->remaining_quantity];
            $rowIndex++;
        }
        $formLines[$line->id]['remaining'] = $remainingToIssue;
        $formLines[$line->id]['lots'] = $formLines[$line->id]['lots'] ?? [];

        $lineViews[] = [
            'line' => $line,
            'remaining' => $remainingToIssue,
            'color' => $reqColorCode,
            'lots' => $lotViews,
            'is_po_fabric' => $po && (int) $po->fabric_material_id === (int) $line->material_id,
        ];
    }
    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
@endphp

<div class="d-flex flex-column gap-3 mx-auto" style="max-width: 960px;">
    <div class="record-header record-header-sticky">
        <a href="{{ route('production.material-requests.show', $materialRequest) }}" class="btn btn-light border record-header-back" aria-label="رجوع لطلب المواد">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">صرف مواد — {{ $po?->productModel?->name_ar ?? 'أمر إنتاج' }}</h1>
            <div class="fs-8 text-muted">
                طلب <span class="ltr-isolate">{{ $materialRequest->request_number }}</span> &bull;
                أمر إنتاج <span class="ltr-isolate">{{ $po?->production_order_number }}</span> &bull;
                {{ $materialRequest->warehouse?->name_ar }}
            </div>
        </div>
    </div>

    @if (empty($lineViews))
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold">تم صرف جميع بنود هذا الطلب — لا توجد كميات متبقية للصرف.</p>
        </div>
    @else
    <form action="{{ route('production.material-requests.fulfill', $materialRequest) }}" method="POST"
          x-data="{ lines: @js($formLines),
                    total(lineId) { return Object.values(this.lines[lineId].lots).reduce((sum, lot) => sum + (lot.on ? (Number(lot.qty) || 0) : 0), 0); },
                    over(lineId) { return this.total(lineId) - this.lines[lineId].remaining > 0.00005; },
                    lotOver(lineId, row) { const lot = this.lines[lineId].lots[row]; return lot.on && Number(lot.qty) - lot.max > 0.00005; },
                    get anySelected() { return Object.values(this.lines).some(line => Object.values(line.lots).some(lot => lot.on && Number(lot.qty) > 0)); },
                    get hasErrors() { return Object.keys(this.lines).some(id => this.over(id) || Object.keys(this.lines[id].lots).some(row => this.lotOver(id, row))); } }"
          data-confirm="تأكيد صرف المواد المحددة؟ سيتم خصم الكميات من المخزون وإنشاء سند صرف.">
        @csrf

        <div class="d-flex flex-column gap-3">
            @foreach ($lineViews as $lineView)
                @php
                    $line = $lineView['line'];
                    $unit = $line->baseUnit?->name_ar ?? $line->material?->baseUnit?->name_ar;
                @endphp
                <section class="card-factory p-3" aria-labelledby="mr-line-{{ $line->id }}">
                    {{-- Warehouse identity: material, colour, supplier requirement, quantity --}}
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="min-w-0">
                            <h2 id="mr-line-{{ $line->id }}" class="h6 fw-bold text-dark mb-1">{{ $line->material?->name_ar }}</h2>
                            <div class="d-flex flex-wrap gap-2 fs-7">
                                @if ($lineView['color'])
                                    <span class="status-chip status-chip-primary"><i class="fas fa-palette" aria-hidden="true"></i> اللون المطلوب: <span class="ltr-isolate">{{ $lineView['color'] }}</span></span>
                                @endif
                                @if ($lineView['is_po_fabric'] && $po?->fabricSupplier)
                                    <span class="status-chip status-chip-secondary"><i class="fas fa-truck" aria-hidden="true"></i> المورد: {{ $po->fabricSupplier->name }}</span>
                                @endif
                                <span class="text-muted ltr-isolate fs-8">{{ $line->material?->code }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="qty-trio mb-3">
                        <div><small>المطلوب / المعتمد</small><strong>{{ $fmt($line->approved_quantity ?? $line->requested_quantity) }}</strong></div>
                        <div><small>المصروف سابقاً</small><strong>{{ $fmt($line->issued_quantity) }}</strong></div>
                        <div class="is-remaining"><small>المتبقي ({{ $unit }})</small><strong>{{ $fmt($lineView['remaining']) }}</strong></div>
                    </div>

                    @if (empty($lineView['lots']))
                        <div class="alert alert-warning mb-0" role="alert">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            لا توجد دفعات متاحة لهذه المادة{{ $lineView['color'] ? ' باللون المطلوب' : '' }} في هذا المستودع. استلم المادة أولاً أو أبلغ المشتريات.
                        </div>
                    @else
                        <fieldset>
                            <legend class="fs-7 fw-bold text-secondary mb-2">اختر الدفعات (الأقدم أولاً — FIFO)</legend>
                            <div class="d-grid gap-2">
                                @foreach ($lineView['lots'] as $lotView)
                                    @php
                                        $lot = $lotView['lot'];
                                        $row = $lotView['row'];
                                        $lotColor = $lot->fabric_color_code ?? $lot->fabricColor?->color_code;
                                    @endphp
                                    <div class="border rounded-3 p-2" :class="lines[{{ $line->id }}].lots[{{ $row }}].on ? 'border-success bg-success-subtle' : 'bg-white'">
                                        <input type="hidden" name="fulfillments[{{ $row }}][request_line_id]" value="{{ $line->id }}" :disabled="!lines[{{ $line->id }}].lots[{{ $row }}].on">
                                        <input type="hidden" name="fulfillments[{{ $row }}][inventory_lot_id]" value="{{ $lot->id }}" :disabled="!lines[{{ $line->id }}].lots[{{ $row }}].on">
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" id="lot-pick-{{ $row }}" x-model="lines[{{ $line->id }}].lots[{{ $row }}].on"
                                                   @change="if ($event.target.checked && !lines[{{ $line->id }}].lots[{{ $row }}].qty) { lines[{{ $line->id }}].lots[{{ $row }}].qty = Math.min({{ (float) $lot->remaining_quantity }}, Math.max(0, lines[{{ $line->id }}].remaining - total({{ $line->id }}))) || null }">
                                            <label class="form-check-label w-100" for="lot-pick-{{ $row }}">
                                                <span class="d-flex flex-wrap justify-content-between gap-2">
                                                    <span class="fw-bold">
                                                        متاح: {{ $fmt($lot->remaining_quantity) }} {{ $lot->baseUnit?->name_ar ?? $unit }}
                                                    </span>
                                                    @if ($lotColor)
                                                        <span class="fs-7"><i class="fas fa-palette text-primary" aria-hidden="true"></i> لون <strong class="ltr-isolate">{{ $lotColor }}</strong>@if ($lot->fabricColor?->color_name_ar) ({{ $lot->fabricColor->color_name_ar }})@endif</span>
                                                    @endif
                                                </span>
                                                <span class="d-flex flex-wrap gap-3 fs-8 text-muted">
                                                    <span><i class="fas fa-truck" aria-hidden="true"></i> {{ $lot->supplier?->name ?? 'مورد غير محدد' }}</span>
                                                    <span><i class="far fa-calendar" aria-hidden="true"></i> <span class="ltr-isolate">{{ $lot->received_date?->format('Y-m-d') }}</span></span>
                                                    @if ($lot->receiptLine?->receipt)
                                                        <span>سند <span class="ltr-isolate">{{ $lot->receiptLine->receipt->receipt_number }}</span></span>
                                                    @endif
                                                    <span>دفعة <span class="ltr-isolate">{{ $lot->lot_code }}</span></span>
                                                    @if ($canViewCost)
                                                        <span>تكلفة الوحدة {{ number_format($lot->unit_cost, 2) }} ر.س</span>
                                                    @endif
                                                </span>
                                            </label>
                                        </div>
                                        <div class="row g-2 align-items-end" x-show="lines[{{ $line->id }}].lots[{{ $row }}].on" x-cloak>
                                            <div class="col-6 col-md-4">
                                                <label for="lot-qty-{{ $row }}" class="form-label fs-8 mb-1">كمية الصرف</label>
                                                <input type="number" id="lot-qty-{{ $row }}" name="fulfillments[{{ $row }}][quantity]"
                                                       class="form-control fw-bold" inputmode="decimal" step="0.0001" min="0.0001" max="{{ (float) $lot->remaining_quantity }}"
                                                       x-model="lines[{{ $line->id }}].lots[{{ $row }}].qty"
                                                       :disabled="!lines[{{ $line->id }}].lots[{{ $row }}].on" required>
                                            </div>
                                            <div class="col-6 col-md-8">
                                                <label for="lot-notes-{{ $row }}" class="form-label fs-8 mb-1">ملاحظة</label>
                                                <input type="text" id="lot-notes-{{ $row }}" name="fulfillments[{{ $row }}][notes]" class="form-control" maxlength="500" autocomplete="off"
                                                       :disabled="!lines[{{ $line->id }}].lots[{{ $row }}].on">
                                            </div>
                                            <p class="col-12 text-danger fs-7 mb-0" x-show="lotOver({{ $line->id }}, {{ $row }})" x-cloak role="alert">الكمية تتجاوز رصيد هذه الدفعة.</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="d-flex justify-content-between align-items-center mt-2 fs-7">
                            <span>إجمالي الصرف لهذا البند: <strong x-text="total({{ $line->id }}).toLocaleString('en-US', { maximumFractionDigits: 4 })"></strong> من {{ $fmt($lineView['remaining']) }}</span>
                            <span class="text-danger fw-bold" x-show="over({{ $line->id }})" x-cloak role="alert">يتجاوز الكمية المتبقية!</span>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <x-mobile-action-bar>
            <a href="{{ route('production.material-requests.show', $materialRequest) }}" class="btn btn-light border">إلغاء</a>
            <button type="submit" class="btn btn-success fw-bold" :disabled="!anySelected || hasErrors">
                <i class="fas fa-dolly" aria-hidden="true"></i> صرف المواد
            </button>
        </x-mobile-action-bar>
    </form>
    @endif
</div>
@endsection
