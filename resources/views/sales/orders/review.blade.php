@extends('layouts.app')

@section('title', 'المراجعة الفنية لطلب العميل ' . $order->order_number)
@section('page-title', 'مراجعة الإنتاج')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Identity + back --}}
    <div class="record-header record-header-sticky mb-3">
        <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-light border record-header-back" aria-label="التراجع والعودة للطلب" title="التراجع والعودة للطلب">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">المراجعة الفنية والاعتماد للإنتاج</h1>
            <div class="fs-8 text-muted">{{ $order->customer?->name }} &bull; <span class="ltr-isolate">{{ $order->order_number }}</span> &bull; {{ $order->lines->count() }} بند</div>
        </div>
    </div>

    {{-- Order summary + payment gate --}}
    <div class="card-factory p-3 mb-3">
        <dl class="detail-list">
            <div><dt>العميل</dt><dd>{{ $order->customer?->name }}</dd></div>
            <div><dt>قناة البيع</dt><dd>{{ $order->salesChannel?->name_ar ?? '—' }}</dd></div>
            <div><dt>تاريخ الطلب</dt><dd class="ltr-isolate">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt>التسليم المطلوب</dt><dd class="ltr-isolate">{{ $order->requested_delivery_date?->format('Y-m-d') ?? '—' }}</dd></div>
        </dl>
        <div class="alert {{ $productionGate['eligible'] ? 'alert-success' : 'alert-warning' }} mb-0 mt-3 py-2 d-flex gap-2 align-items-start" role="status">
            <i class="fas {{ $productionGate['eligible'] ? 'fa-circle-check' : 'fa-hourglass-half' }} mt-1" aria-hidden="true"></i>
            <div>
                <div class="fw-bold">{{ $productionGate['eligible'] ? 'أهلية السداد: مسموح ببدء الإنتاج' : 'أهلية السداد: الإنتاج بانتظار استيفاء شرط السداد' }}</div>
                @if ($productionGate['detail'])
                    <div class="fs-7">{{ $productionGate['detail'] }}</div>
                @endif
            </div>
        </div>
    </div>

    <form action="{{ route('sales.orders.approve-production', $order) }}" method="POST"
          data-confirm="اعتماد هذا الطلب نهائياً للإنتاج؟">
        @csrf

        <div class="d-flex flex-column gap-3 mb-3">
            @foreach ($order->lines as $index => $line)
                @php
                    $colorCode = $line->fabric_color_code ?? $line->fabricColor?->color_code;
                    $recipeVersion = $line->recipe_version;
                @endphp
                <section class="card-factory p-3" aria-labelledby="review-line-{{ $line->id }}">
                    {{-- 1. Identity --}}
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div class="min-w-0">
                            <h2 id="review-line-{{ $line->id }}" class="h6 fw-bold text-dark mb-1">
                                البند {{ $index + 1 }} —
                                {{ $line->custom_design ? ($line->custom_design_name ?? 'تصميم خاص') : ($line->productModel?->name_ar ?? 'موديل غير محدد') }}
                            </h2>
                            <div class="fs-8 text-muted">
                                @if ($line->custom_design)
                                    <span class="status-chip status-chip-primary"><i class="fas fa-pen-ruler" aria-hidden="true"></i> تصميم خاص</span>
                                @else
                                    <span class="ltr-isolate">{{ $line->productModel?->model_code }}</span>
                                @endif
                                @if ($line->productConfiguration)
                                    &bull; التكوين: <span class="ltr-isolate">{{ (int) $line->productConfiguration->width_cm }}×{{ (int) $line->productConfiguration->length_cm }}</span>
                                    {{ $line->productConfiguration->has_storage ? '— مع تخزين' : '— بدون تخزين' }}
                                @endif
                            </div>
                        </div>
                        <div class="text-center flex-shrink-0">
                            <div class="fs-8 text-muted">الكمية</div>
                            <div class="fs-4 fw-bold">{{ (float) $line->quantity }}</div>
                        </div>
                    </div>

                    {{-- 2. Dimensions: requested vs manufacturing reference --}}
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="fs-8 text-muted mb-1">المقاس المطلوب من العميل</div>
                            <div class="fw-bold"><span class="ltr-isolate">{{ $line->requested_width_cm ?? '—' }} × {{ $line->requested_length_cm ?? '—' }}</span> سم</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="ref-width-{{ $line->id }}" class="form-label fs-8 mb-1">العرض المرجعي للتصنيع (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" inputmode="decimal" id="ref-width-{{ $line->id }}" name="lines[{{ $line->id }}][reference_width_cm]"
                                   class="form-control" value="{{ old('lines.'.$line->id.'.reference_width_cm', $line->reference_width_cm ?? $line->requested_width_cm) }}" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="ref-length-{{ $line->id }}" class="form-label fs-8 mb-1">الطول المرجعي للتصنيع (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" inputmode="decimal" id="ref-length-{{ $line->id }}" name="lines[{{ $line->id }}][reference_length_cm]"
                                   class="form-control" value="{{ old('lines.'.$line->id.'.reference_length_cm', $line->reference_length_cm ?? $line->requested_length_cm) }}" required>
                        </div>
                    </div>

                    {{-- 3. Fabric --}}
                    <dl class="detail-list mb-3 p-2 rounded-3" style="background: #f8fafc;">
                        <div><dt>مورد القماش</dt><dd>{{ $line->fabricSupplier?->name ?? '—' }}</dd></div>
                        <div><dt>خامة القماش</dt><dd>{{ $line->fabricMaterial?->name_ar ?? '—' }}</dd></div>
                        <div>
                            <dt>اللون</dt>
                            <dd>
                                @if ($colorCode)
                                    <span class="color-dot" style="background: {{ $line->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span>
                                    <span class="ltr-isolate">{{ $colorCode }}</span>
                                    @if ($line->fabric_supplier_color_code) <span class="fs-8 text-muted">(مورد: <span class="ltr-isolate">{{ $line->fabric_supplier_color_code }}</span>)</span> @endif
                                @else
                                    <span class="text-danger">غير محدد</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    {{-- 4. Recipe / BOM --}}
                    <div class="mb-2 fs-7">
                        @if ($recipeVersion)
                            <span class="status-chip status-chip-success"><i class="fas fa-scroll" aria-hidden="true"></i> وصفة معتمدة: {{ $recipeVersion->recipe?->name }} (V{{ $recipeVersion->version_number }})</span>
                        @else
                            <span class="status-chip status-chip-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> لا توجد وصفة معتمدة لهذا التكوين</span>
                        @endif
                    </div>
                    <label for="recipe-{{ $line->id }}" class="form-label fs-8 mb-1">ربط وصفة التصنيع (BOM)</label>
                    <select id="recipe-{{ $line->id }}" name="lines[{{ $line->id }}][recipe_version_id]" class="form-select">
                        <option value="">-- بدون ربط وصفة حالياً --</option>
                        @foreach ($recipeVersions as $version)
                            <option value="{{ $version->id }}" @selected(old('lines.'.$line->id.'.recipe_version_id', $line->recipe_version_id) == $version->id)>
                                {{ $version->recipe?->name }} (V{{ $version->version_number }}) - {{ $version->version_code }}
                            </option>
                        @endforeach
                    </select>

                    @if ($line->notes)
                        <div class="fs-7 text-muted mt-2"><i class="fas fa-circle-info" aria-hidden="true"></i> {{ $line->notes }}</div>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="card-factory p-3 mb-3">
            <label for="review-notes" class="form-label fw-semibold text-dark">ملاحظات المراجعة الفنية والإنتاج</label>
            <textarea id="review-notes" name="review_notes" class="form-control" rows="2" placeholder="أدخل أي ملاحظات فنية أو توصيات لقسم التصنيع...">{{ old('review_notes') }}</textarea>
        </div>

        <x-mobile-action-bar>
            <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-light border px-4">إلغاء</a>
            <button type="submit" class="btn btn-success px-5 fw-bold">
                <i class="fas fa-check-double" aria-hidden="true"></i> اعتماد الطلب للإنتاج
            </button>
        </x-mobile-action-bar>
    </form>
</div>
@endsection
