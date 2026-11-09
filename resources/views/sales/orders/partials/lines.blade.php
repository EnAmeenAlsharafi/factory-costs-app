{{--
    Sales order lines as cards: what the customer ordered (image, model or design, fabric), what
    production uses (reference size, recipe), quantities released to production, and the money.
--}}
@php
    $lineTypeLabels = ['READY_STOCK' => ['منتج جاهز', 'success'], 'BUNDLE' => ['بكج', 'info'], 'CUSTOM' => ['تصميم خاص', 'warning'], 'MANUFACTURED' => ['يُصنّع حسب الطلب', 'primary']];
    $size = fn ($width, $length) => $width && $length ? rtrim(rtrim(number_format((float) $width, 2, '.', ''), '0'), '.').' × '.rtrim(rtrim(number_format((float) $length, 2, '.', ''), '0'), '.').' سم' : null;
    $canRelease = $order->status === 'APPROVED_FOR_PRODUCTION' && auth()->user()->can('production.release');
@endphp
<section class="card border-0 shadow-sm mb-4 rounded-3" aria-labelledby="order-lines-heading">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 id="order-lines-heading" class="h6 card-title mb-0 fw-bold text-dark"><i class="fas fa-list text-primary me-2" aria-hidden="true"></i>بنود الطلب والمواصفات ({{ $order->lines->count() }})</h2>
        @if ($canSeeCommercial)
            <span class="fw-bold text-primary">الإجمالي: <bdi>{{ number_format($order->total_amount, 2) }}</bdi> ر.س</span>
        @endif
    </div>
    <ul class="list-unstyled mb-0 sales-line-list">
        @foreach ($order->lines as $index => $line)
            @php
                $typeKey = $line->line_type === 'MANUFACTURED' && $line->custom_design ? 'CUSTOM' : $line->line_type;
                [$typeLabel, $typeTone] = $lineTypeLabels[$typeKey] ?? $lineTypeLabels['MANUFACTURED'];
                $imageUrl = match (true) {
                    $line->line_type === 'MANUFACTURED' && $line->custom_design => $line->custom_design_image_path ? route('sales.orders.design-image', [$order, $line]) : null,
                    $line->line_type === 'MANUFACTURED' => $line->productModel?->image_url,
                    default => $line->material?->sales_image_url,
                };
                $released = (int) $line->productionOrders->where('status', '!=', 'CANCELLED')->sum('released_quantity');
                $remaining = max(0, (int) $line->quantity - $released);
                $hasFabric = $line->fabricMaterial || $line->fabricSupplier || $line->fabric_color_code || $line->fabric_supplier_color_code;
            @endphp
            <li class="sales-line">
                <div class="sales-line-main">
                    <span class="order-line-image" aria-hidden="true">
                        @if ($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy">@else<i class="fas {{ $line->line_type === 'MANUFACTURED' ? 'fa-bed' : 'fa-box' }}"></i>@endif
                    </span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="status-chip status-chip-{{ $typeTone }}">{{ $typeLabel }}</span>
                            <span class="text-muted small">بند {{ $index + 1 }}</span>
                        </div>
                        <h3 class="h6 fw-bold mb-1">{{ $line->display_name }}</h3>
                        <p class="small text-muted mb-0">
                            @if ($line->line_type === 'READY_STOCK')
                                SKU: <bdi>{{ $line->external_sku ?: ($line->material?->sales_sku ?? '—') }}</bdi>
                            @elseif ($line->line_type === 'MANUFACTURED' && ! $line->custom_design && $line->productModel?->model_code)
                                كود الموديل: <bdi>{{ $line->productModel->model_code }}</bdi>
                            @endif
                        </p>
                        @if ($line->custom_design && $imageUrl)
                            <a href="{{ $imageUrl }}" target="_blank" rel="noopener" class="small">عرض صورة التصميم</a>
                        @endif
                    </div>
                    <div class="sales-line-money">
                        <div class="small text-muted">الكمية</div>
                        <div class="fs-5 fw-bold"><bdi>{{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}</bdi></div>
                        @if ($canSeeCommercial)
                            <div class="small text-muted mt-1"><bdi>{{ number_format($line->unit_price, 2) }}</bdi> ر.س للوحدة</div>
                            <div class="fw-bold text-primary"><bdi>{{ number_format($line->line_total, 2) }}</bdi> ر.س</div>
                        @endif
                    </div>
                </div>

                @if ($line->line_type === 'MANUFACTURED')
                    <dl class="row g-3 mb-0 mt-1 portal-review-facts">
                        <div class="col-6 col-md-3"><dt>مقاس العميل</dt><dd><bdi>{{ $size($line->requested_width_cm, $line->requested_length_cm) ?? 'حسب التكوين' }}</bdi></dd></div>
                        <div class="col-6 col-md-3"><dt>المقاس المرجعي للإنتاج</dt><dd>@if ($ref = $size($line->reference_width_cm, $line->reference_length_cm))<bdi>{{ $ref }}</bdi>@else<span class="text-muted">يحدد في المراجعة</span>@endif</dd></div>
                        <div class="col-12 col-md-6"><dt>وصفة التصنيع</dt><dd>@if ($line->recipeVersion)<i class="fas fa-scroll text-success me-1" aria-hidden="true"></i>{{ $line->recipeVersion->recipe?->name }} <bdi>(V{{ $line->recipeVersion->version_number }})</bdi>@else<span class="text-warning-emphasis"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>غير مرتبطة بوصفة</span>@endif</dd></div>
                        @if ($hasFabric)
                            <div class="col-6 col-md-3"><dt>القماش</dt><dd>{{ $line->fabricMaterial?->name_ar ?? 'غير محدد' }}@if ($line->fabricMaterial?->fabricSpec?->catalog_number) <span class="text-muted small">— كتالوج <bdi>#{{ $line->fabricMaterial->fabricSpec->catalog_number }}</bdi></span>@endif</dd></div>
                            <div class="col-6 col-md-3"><dt>المورد</dt><dd>{{ $line->fabricSupplier?->name ?? $line->fabricMaterial?->fabricSpec?->supplier?->name ?? 'غير محدد' }}</dd></div>
                            <div class="col-6 col-md-3"><dt>رقم اللون الداخلي</dt><dd><bdi>{{ $line->fabric_color_code ?? 'غير محدد' }}</bdi></dd></div>
                            <div class="col-6 col-md-3"><dt>كود لون المورد</dt><dd><bdi>{{ $line->fabric_supplier_color_code ?: '—' }}</bdi></dd></div>
                        @endif
                    </dl>
                @endif

                @if ($line->notes)
                    <p class="small text-muted mb-0 mt-2"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>{{ $line->notes }}</p>
                @endif

                @if ($line->line_type === 'MANUFACTURED' && $order->status === 'APPROVED_FOR_PRODUCTION')
                    <div class="sales-line-release">
                        @if ($remaining === 0)
                            <span class="text-success fw-semibold"><i class="fas fa-circle-check me-1" aria-hidden="true"></i>أُطلقت كامل الكمية للإنتاج</span>
                        @else
                            <span><i class="fas fa-industry text-primary me-1" aria-hidden="true"></i>أُطلق للإنتاج <bdi>{{ $released }}</bdi> من <bdi>{{ (int) $line->quantity }}</bdi> — المتبقي <strong><bdi>{{ $remaining }}</bdi></strong></span>
                            @if ($canRelease)
                                <a href="{{ route('production.orders.create', ['line_id' => $line->id]) }}" class="btn btn-sm btn-outline-primary">إطلاق هذا البند</a>
                            @endif
                        @endif
                    </div>
                @elseif ($line->line_type === 'READY_STOCK' && in_array($order->status, ['APPROVED_FOR_PRODUCTION', 'APPROVED_FOR_FULFILLMENT'], true))
                    <div class="sales-line-release"><span><i class="fas fa-warehouse text-success me-1" aria-hidden="true"></i>يُسلّم من مستودع المنتجات الجاهزة دون تصنيع</span></div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
