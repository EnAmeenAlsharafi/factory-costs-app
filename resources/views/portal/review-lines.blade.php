{{--
    Portal request review: what the customer asked for is shown read-only (only the customer can change it,
    through the portal); the factory fills in the price, discount and the technical stock mapping per line.
--}}
@php
    $modelsById = collect($models)->keyBy('id');
    $readyById = collect($readyProducts)->keyBy('id');
    $fabricsById = $fabrics->keyBy('id');
    $colorsById = $colors->keyBy('id');
    $sizeLabel = fn ($width, $length) => $width && $length ? rtrim(rtrim(number_format((float) $width, 2, '.', ''), '0'), '.').'×'.rtrim(rtrim(number_format((float) $length, 2, '.', ''), '0'), '.').' سم' : null;
@endphp
<input type="hidden" name="revision" value="{{ $submission->revision }}">

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h6 fw-bold mb-0">بيانات الطلب والتسليم</h2>
        <span class="badge rounded-pill text-bg-light border"><i class="fas fa-lock me-1" aria-hidden="true"></i>يعدّلها العميل فقط</span>
    </div>
    <dl class="row g-3 mb-0 portal-review-facts">
        <div class="col-6 col-md-3"><dt>تاريخ التسليم المطلوب</dt><dd>{{ $submission->requested_delivery_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-6 col-md-3"><dt>مدينة التسليم</dt><dd>{{ $submission->delivery_city ?: '—' }}</dd></div>
        <div class="col-12 col-md-6"><dt>عنوان التسليم</dt><dd>{{ $submission->delivery_address ?: '—' }}</dd></div>
        <div class="col-12"><dt>ملاحظات الطلب</dt><dd class="text-pre-line">{{ $submission->customer_notes ?: '—' }}</dd></div>
    </dl>
</div></div>

@foreach($submission->lines ?? [] as $index => $line)
    @php
        $isManufactured = $line['line_type'] === 'MANUFACTURED';
        $isCustomDesign = $isManufactured && ! empty($line['custom_design']);
        $describedFabric = $isManufactured && ($line['fabric_source'] ?? null) === 'CUSTOM';
        $imageUrl = match (true) {
            ! $isManufactured => $readyById->get($line['material_id'] ?? null)['image_url'] ?? null,
            $isCustomDesign => ! empty($line['custom_design_image_path']) ? route('sales.portal-requests.design-image', [$submission, $line['line_key']]) : null,
            default => $modelsById->get($line['product_model_id'] ?? null)['image_url'] ?? null,
        };
        $catalogFabric = $fabricsById->get($line['fabric_material_id'] ?? null)?->name_ar;
        $catalogColor = $colorsById->get($line['fabric_color_id'] ?? null);
        $modelConfigurations = collect($configurations)->where('product_model_id', $line['product_model_id'] ?? null)->where('has_storage', ! empty($line['has_storage']));
        $price = old("lines.$index.unit_price", $line['unit_price'] ?? '');
        $discount = old("lines.$index.discount_amount", $line['discount_amount'] ?? 0);
    @endphp
    <section class="card border-0 shadow-sm mb-3" aria-labelledby="line-title-{{ $index }}"
             x-data="{ price: @js($price), discount: @js($discount), quantity: {{ (int) $line['quantity'] }}, fabric: @js((string) old("lines.$index.fabric_material_id", $line['fabric_material_id'] ?? '')), color: @js((string) old("lines.$index.fabric_color_id", $line['fabric_color_id'] ?? '')), colors: @js($describedFabric ? $colors->values() : []) }">
        <div class="card-body">
            <input type="hidden" name="lines[{{ $index }}][line_key]" value="{{ $line['line_key'] }}">

            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="order-line-image" aria-hidden="true">
                    @if($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy">@else<i class="fas {{ $isManufactured ? 'fa-bed' : 'fa-box' }}"></i>@endif
                </span>
                <div class="flex-grow-1 min-w-0">
                    <h3 id="line-title-{{ $index }}" class="h6 fw-bold mb-1">{{ $index + 1 }}. {{ $line['product_name'] ?? 'منتج' }}</h3>
                    <small class="text-muted">{{ ! $isManufactured ? 'منتج جاهز' : ($isCustomDesign ? 'تصميم خاص' : 'يُصنّع حسب الطلب') }} — الكمية <bdi>{{ $line['quantity'] }}</bdi></small>
                </div>
                @if($isCustomDesign && $imageUrl)<a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="{{ $imageUrl }}" target="_blank" rel="noopener">صورة التصميم</a>@endif
            </div>

            <dl class="row g-3 portal-review-facts">
                @if($isManufactured)
                    <div class="col-6 col-md-3"><dt>المقاس</dt><dd><bdi>{{ $sizeLabel($line['requested_width_cm'] ?? null, $line['requested_length_cm'] ?? null) ?? '—' }}</bdi></dd></div>
                    <div class="col-6 col-md-3"><dt>التخزين</dt><dd>{{ ! empty($line['has_storage']) ? 'مع تخزين' : 'بدون تخزين' }}</dd></div>
                    @if($describedFabric)
                        <div class="col-6 col-md-3"><dt>القماش كما وصفه العميل</dt><dd>{{ $line['requested_fabric'] ?: '—' }}</dd></div>
                        <div class="col-6 col-md-3"><dt>اللون كما وصفه العميل</dt><dd>{{ $line['requested_color'] ?: '—' }}</dd></div>
                    @else
                        <div class="col-6 col-md-3"><dt>القماش</dt><dd>{{ $catalogFabric ?? '—' }}</dd></div>
                        <div class="col-6 col-md-3"><dt>اللون</dt><dd>{{ $catalogColor ? ($catalogColor->color_name_ar ?: $catalogColor->color_code).' — '.$catalogColor->color_code : '—' }}</dd></div>
                    @endif
                @endif
                <div class="col-12"><dt>ملاحظات المنتج</dt><dd>{{ $line['notes'] ?? '' ?: '—' }}</dd></div>
            </dl>

            <div class="portal-review-staff rounded-3 p-3 mt-3">
                <p class="small fw-bold text-secondary mb-2"><i class="fas fa-industry me-1" aria-hidden="true"></i>يحدده المصنع</p>
                <div class="row g-3">
                    @if($isManufactured && ! $isCustomDesign)
                        <div class="col-md-6">
                            <label class="form-label" for="configuration-{{ $index }}">التكوين المرجعي للتصنيع</label>
                            <select class="form-select" id="configuration-{{ $index }}" name="lines[{{ $index }}][product_configuration_id]" aria-describedby="configuration-help-{{ $index }}">
                                <option value="">بدون تكوين مرجعي</option>
                                @foreach($modelConfigurations as $configuration)
                                    <option value="{{ $configuration['id'] }}" @selected((string) old("lines.$index.product_configuration_id", $line['product_configuration_id'] ?? '') === (string) $configuration['id'])>{{ $configuration['name'] }}</option>
                                @endforeach
                            </select>
                            <small id="configuration-help-{{ $index }}" class="text-muted">يربط البند بالوصفة المعتمدة دون تغيير مقاس العميل.</small>
                        </div>
                    @endif
                    @if($describedFabric)
                        <div class="col-md-6">
                            <label class="form-label" for="fabric-{{ $index }}">القماش المعتمد من المخزون</label>
                            <select class="form-select" id="fabric-{{ $index }}" name="lines[{{ $index }}][fabric_material_id]" x-model="fabric" @change="color = ''">
                                <option value="">لاحقاً، قبل صرف القماش</option>
                                @foreach($fabrics as $fabric)<option value="{{ $fabric->id }}">{{ $fabric->name_ar }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="color-{{ $index }}">اللون المعتمد</label>
                            <select class="form-select" id="color-{{ $index }}" name="lines[{{ $index }}][fabric_color_id]" x-model="color" :disabled="!fabric">
                                <option value="">اختر اللون</option>
                                <template x-for="option in colors.filter(c => String(c.material_id) === String(fabric))" :key="option.id">
                                    <option :value="String(option.id)" x-text="(option.color_name_ar || option.color_code) + ' — ' + option.color_code" :selected="String(option.id) === color"></option>
                                </template>
                            </select>
                        </div>
                    @endif
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="price-{{ $index }}">سعر الوحدة (ر.س)</label>
                        <input type="number" inputmode="decimal" min="0" max="9999999" step="0.01" class="form-control @error("lines.$index.unit_price") is-invalid @enderror" id="price-{{ $index }}" name="lines[{{ $index }}][unit_price]" x-model="price" required>
                        @error("lines.$index.unit_price")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="discount-{{ $index }}">خصم البند (ر.س)</label>
                        <input type="number" inputmode="decimal" min="0" step="0.01" class="form-control" id="discount-{{ $index }}" name="lines[{{ $index }}][discount_amount]" x-model="discount">
                    </div>
                    <div class="col-12 col-md-6 d-flex align-items-end">
                        <p class="mb-0 small text-muted">إجمالي البند: <strong class="text-dark" x-text="(Math.max(0, (Number(price) || 0) * quantity - (Number(discount) || 0))).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ر.س'"></strong></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endforeach

<div class="alert alert-light border d-flex gap-2 align-items-start" role="note">
    <i class="fas fa-circle-info text-primary mt-1" aria-hidden="true"></i>
    <div>بيانات العميل والمنتجات والكميات والمقاسات لا تُعدّل من هنا. إن احتاجت تصحيحًا فاستخدم <a href="#return-for-changes">«إعادة للعميل للتعديل»</a> واكتب له المطلوب، فيعدّلها بنفسه ويعيد إرسالها.</div>
</div>
