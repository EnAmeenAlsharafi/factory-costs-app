@php
    $defaultLine = ['line_type' => 'MANUFACTURED', 'product_model_id' => '', 'product_configuration_id' => '', 'material_id' => '', 'requested_width_cm' => '', 'requested_length_cm' => '', 'has_storage' => 0, 'fabric_material_id' => '', 'fabric_color_id' => '', 'quantity' => 1, 'notes' => '', 'unit_price' => '', 'discount_amount' => 0];
    $initialLines = old('lines', $submission->lines ?: [$defaultLine]);
    $defaultLine = array_merge($defaultLine, ['line_key' => '', 'custom_design' => false, 'custom_design_name' => '', 'requested_fabric' => '', 'requested_color' => '', 'custom_design_image_url' => null]);
    $initialLines = collect($initialLines)->map(function ($line) use ($defaultLine, $submission, $isReview) {
        $existing = collect($submission->lines ?? [])->firstWhere('line_key', $line['line_key'] ?? '') ?? [];
        $line = array_merge($defaultLine, $line);
        $line['custom_design'] = !empty($line['custom_design']);
        $line['fabric_selection'] = ($line['fabric_source'] ?? null) === 'CUSTOM' || (empty($line['fabric_material_id']) && (!empty($line['requested_fabric']) || !empty($line['requested_color'])))
            ? 'CUSTOM' : (string) ($line['fabric_material_id'] ?? '');
        $line['custom_design_image_url'] = !empty($existing['custom_design_image_path'])
            ? route($isReview ? 'sales.portal-requests.design-image' : 'portal.requests.design-image', [$submission, $existing['line_key']]) : null;
        return $line;
    })->all();
@endphp
@if($submission->exists)<input type="hidden" name="revision" value="{{ $submission->revision }}">@endif
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h6 fw-bold mb-3">بيانات الطلب والتسليم</h2>
    <div class="row g-3">
        <div class="col-md-6"><label for="requested_delivery_date" class="form-label">تاريخ التسليم المطلوب</label><input type="date" class="form-control" id="requested_delivery_date" name="requested_delivery_date" value="{{ old('requested_delivery_date', $submission->requested_delivery_date?->toDateString()) }}"></div>
        <div class="col-md-4"><label for="delivery_city" class="form-label">مدينة التسليم</label><input class="form-control" id="delivery_city" name="delivery_city" maxlength="255" value="{{ old('delivery_city', $submission->delivery_city) }}" autocomplete="address-level2"></div>
        <div class="col-md-8"><label for="delivery_address" class="form-label">عنوان التسليم</label><input class="form-control" id="delivery_address" name="delivery_address" maxlength="3000" value="{{ old('delivery_address', $submission->delivery_address) }}" autocomplete="street-address"></div>
        <div class="col-12"><label for="customer_notes" class="form-label">ملاحظات الطلب</label><textarea class="form-control" id="customer_notes" name="customer_notes" rows="2" maxlength="5000">{{ old('customer_notes', $submission->customer_notes) }}</textarea></div>
    </div>
</div></div>
<div x-data="{ lines: (@js($initialLines)).map(line => ({...line, _key: crypto.randomUUID()})), blank: {...@js($defaultLine), fabric_selection: ''}, models: @js($models), ready: @js($readyProducts), fabrics: @js($fabrics), colors: @js($colors), configurations: @js($configurations), imageFor(line, uploadedPreviewUrl) { if (line.line_type === 'READY_STOCK') { return this.ready.find(product => product.id == line.material_id)?.image_url || null; } if (line.custom_design) { return uploadedPreviewUrl || line.custom_design_image_url || null; } return this.models.find(model => model.id == line.product_model_id)?.image_url || null; } }">
    <template x-for="(line, index) in lines" :key="line._key">
        <section class="card border-0 shadow-sm mb-3" x-data="{ previewUrl: null }"><div class="card-body">
            <input type="hidden" :name="'lines['+index+'][line_key]'" x-model="line.line_key">
            <input type="hidden" :name="'lines['+index+'][custom_design]'" :value="line.line_type === 'MANUFACTURED' && line.custom_design ? 1 : 0">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div class="flex-grow-1 min-w-0">
                    <span class="d-block fw-bold">المنتج <span x-text="index + 1"></span></span>
                    <small class="d-block text-muted" x-text="(line.line_type === 'READY_STOCK' ? 'منتج جاهز' : (line.custom_design ? 'تصميم خاص' : 'يُصنّع حسب الطلب')) + ' — الكمية ' + (line.quantity || 0)"></small>
                </div>
                <button class="btn btn-outline-danger flex-shrink-0" type="button" @click="lines.splice(index, 1)" :disabled="lines.length === 1" :aria-label="'حذف المنتج ' + (index + 1)"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" :for="'type-'+index">نوع المنتج</label><select class="form-select" :id="'type-'+index" :name="'lines['+index+'][line_type]'" x-model="line.line_type"><option value="MANUFACTURED">منتج يُصنّع حسب الطلب</option><option value="READY_STOCK">منتج جاهز</option></select></div>
                <div class="col-md-8" x-show="line.line_type === 'MANUFACTURED' && !line.custom_design">
                    <label class="form-label" :for="'model-'+index">الموديل</label>
                    <div class="typeahead-container" @click.outside="close()"
                         x-data="localPicker({ items: () => models, get: () => line.product_model_id, set: (id) => { if (String(id) !== String(line.product_model_id)) { line.product_configuration_id = ''; line.has_storage = 0; } line.product_model_id = id; } })"
                         x-effect="$refs.box.setCustomValidity(line.line_type === 'MANUFACTURED' && !line.custom_design && !line.product_model_id ? 'اختر الموديل من القائمة.' : '')">
                        <input type="hidden" :name="'lines['+index+'][product_model_id]'" :value="line.product_model_id" :disabled="line.line_type !== 'MANUFACTURED' || line.custom_design">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-magnifying-glass text-muted" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" :id="'model-'+index" x-ref="box" x-model="query"
                                   role="combobox" aria-autocomplete="list" autocomplete="off" inputmode="search" enterkeyhint="search"
                                   :aria-expanded="isOpen.toString()" :aria-controls="listboxId" :aria-activedescendant="isOpen ? listboxId + '-' + highlighted : null"
                                   placeholder="ابحث باسم الموديل..."
                                   @focus="open(); $event.target.select()" @input="open()"
                                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                   @keydown.enter.prevent="pickHighlighted()" @keydown.escape="close()" @keydown.tab="close()">
                            <button type="button" class="btn btn-outline-secondary" x-show="line.product_model_id" @click="clear()" aria-label="مسح الموديل المختار"><i class="fas fa-times" aria-hidden="true"></i></button>
                        </div>
                        <div class="typeahead-dropdown portal-model-list" x-show="isOpen" x-cloak :id="listboxId" role="listbox" aria-label="الموديلات" @mousedown.prevent>
                            <template x-for="(model, i) in matches" :key="model.id">
                                <div class="typeahead-item" role="option" :id="listboxId + '-' + i" :aria-selected="(String(model.id) === String(line.product_model_id)).toString()"
                                     :class="{ 'active': i === highlighted }" @mouseenter="highlighted = i" @click="choose(model)">
                                    <span class="portal-model-thumb" aria-hidden="true"><template x-if="model.image_url"><img :src="model.image_url" alt="" loading="lazy"></template><template x-if="!model.image_url"><i class="fas fa-bed"></i></template></span>
                                    <span class="flex-grow-1 text-truncate" x-text="model.name"></span>
                                    <i class="fas fa-check text-primary" x-show="String(model.id) === String(line.product_model_id)" aria-hidden="true"></i>
                                </div>
                            </template>
                            <div class="typeahead-status" x-show="matches.length === 0">لا توجد موديلات مطابقة. جرّب جزءاً آخر من الاسم.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8" x-show="line.line_type === 'READY_STOCK'"><label class="form-label" :for="'material-'+index">المنتج الجاهز</label><select class="form-select" :id="'material-'+index" :name="'lines['+index+'][material_id]'" x-model="line.material_id" :required="line.line_type === 'READY_STOCK'"><option value="">اختر المنتج</option><template x-for="product in ready" :key="product.id"><option :value="product.id" x-text="product.name"></option></template></select></div>
                <div class="col-12" x-show="line.line_type === 'MANUFACTURED'">
                    <div class="form-check"><input type="checkbox" class="form-check-input" :id="'custom-'+index" x-model="line.custom_design" @change="line.product_configuration_id = ''; line.has_storage = 0"><label class="form-check-label" :for="'custom-'+index">تصميم خاص حسب الطلب</label></div>
                    <div class="row g-3 mt-1" x-show="line.custom_design">
                        <div class="col-md-6"><label class="form-label" :for="'custom-name-'+index">اسم التصميم الخاص</label><input class="form-control" :id="'custom-name-'+index" :name="'lines['+index+'][custom_design_name]'" x-model="line.custom_design_name" maxlength="255" :disabled="!line.custom_design || line.line_type !== 'MANUFACTURED'" :required="line.custom_design && line.line_type === 'MANUFACTURED'"></div>
                        <div class="col-md-6"><label class="form-label" :for="'custom-image-'+index">صورة المنتج / التصميم الخاص</label><input type="file" class="form-control" :id="'custom-image-'+index" :name="'lines['+index+'][custom_design_image]'" accept="image/jpeg,image/png,image/webp" :disabled="!line.custom_design || line.line_type !== 'MANUFACTURED'" @change="if(previewUrl) URL.revokeObjectURL(previewUrl); previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"><small class="text-muted">JPG، PNG، WebP — حتى 5 ميجابايت. اتركه فارغًا للاحتفاظ بالصورة الحالية.</small></div>
                    </div>
                </div>
                <div class="col-12" x-show="imageFor(line, previewUrl)"><img class="portal-line-preview" :src="imageFor(line, previewUrl)" alt="صورة المنتج المختار"></div>
                <div class="col-12" x-show="line.line_type === 'MANUFACTURED' && !line.custom_design"><label class="form-label" :for="'size-'+index">التكوين / المقاس</label><select class="form-select" :id="'size-'+index" :name="'lines['+index+'][product_configuration_id]'" x-model="line.product_configuration_id" :disabled="line.line_type !== 'MANUFACTURED' || line.custom_design" @change="const selected = configurations.find(c => c.id == line.product_configuration_id); if(selected) { line.requested_width_cm = selected.width_cm; line.requested_length_cm = selected.length_cm; line.has_storage = selected.has_storage ? 1 : 0; } else { line.has_storage = 0; }"><option value="">مقاس خاص / أدخل العرض والطول</option><template x-for="configuration in configurations.filter(c => c.product_model_id == line.product_model_id)" :key="configuration.id"><option :value="configuration.id" x-text="configuration.name"></option></template></select></div>
                <div class="col-6" :class="(line.custom_design || @js($isReview)) ? 'col-md-4' : 'col-md-6'" x-show="line.line_type === 'MANUFACTURED'"><label class="form-label" :for="'width-'+index">العرض المطلوب (سم)</label><input type="number" inputmode="decimal" min="1" max="1000" step="0.01" class="form-control" :id="'width-'+index" :name="'lines['+index+'][requested_width_cm]'" x-model="line.requested_width_cm" :required="line.line_type === 'MANUFACTURED'"></div>
                <div class="col-6" :class="(line.custom_design || @js($isReview)) ? 'col-md-4' : 'col-md-6'" x-show="line.line_type === 'MANUFACTURED'"><label class="form-label" :for="'length-'+index">الطول المطلوب (سم)</label><input type="number" inputmode="decimal" min="1" max="1000" step="0.01" class="form-control" :id="'length-'+index" :name="'lines['+index+'][requested_length_cm]'" x-model="line.requested_length_cm" :required="line.line_type === 'MANUFACTURED'"></div>
                <div class="col-md-4" x-show="line.line_type === 'MANUFACTURED' && (@js($isReview) || line.custom_design)"><label class="form-label" :for="'storage-'+index">التخزين</label><select class="form-select" :id="'storage-'+index" :name="'lines['+index+'][has_storage]'" x-model="line.has_storage"><option value="0">بدون تخزين</option><option value="1">مع تخزين</option></select></div>
                <div class="col-md-6" x-show="line.line_type === 'MANUFACTURED'"><label class="form-label" :for="'fabric-'+index">القماش</label>
                    @if($isReview)
                        <select class="form-select" :id="'fabric-'+index" :name="'lines['+index+'][fabric_material_id]'" x-model="line.fabric_material_id" @change="line.fabric_color_id = ''"><option value="">اختر القماش المعتمد من المخزون</option><template x-for="fabric in fabrics" :key="fabric.id"><option :value="fabric.id" x-text="fabric.name_ar"></option></template></select>
                    @else
                        <input type="hidden" :name="'lines['+index+'][fabric_source]'" :value="line.fabric_selection === 'CUSTOM' ? 'CUSTOM' : 'CATALOG'">
                        <input type="hidden" :name="'lines['+index+'][fabric_material_id]'" x-model="line.fabric_material_id">
                        <select class="form-select" :id="'fabric-'+index" x-model="line.fabric_selection" @change="line.fabric_color_id = ''; line.fabric_material_id = line.fabric_selection === 'CUSTOM' ? '' : line.fabric_selection; if(line.fabric_selection !== 'CUSTOM') { line.requested_fabric = ''; line.requested_color = ''; }"><option value="">اختر القماش أو الكتالوج</option><option value="CUSTOM">كتالوج خاص / قماش خاص</option><template x-for="fabric in fabrics" :key="fabric.id"><option :value="fabric.id" x-text="fabric.name_ar"></option></template></select>
                    @endif
                </div>
                <div class="col-md-6" x-show="line.line_type === 'MANUFACTURED' && (@js($isReview) || line.fabric_selection !== 'CUSTOM')"><label class="form-label" :for="'color-'+index">اللون</label><select class="form-select" :id="'color-'+index" :name="'lines['+index+'][fabric_color_id]'" x-model="line.fabric_color_id" :disabled="!@js($isReview) && line.fabric_selection === 'CUSTOM'"><option value="">اختر اللون</option><template x-for="color in colors.filter(c => c.material_id == line.fabric_material_id)" :key="color.id"><option :value="color.id" x-text="(color.color_name_ar || color.color_code) + ' — ' + color.color_code"></option></template></select></div>
                <div class="col-6 col-md-4"><label class="form-label" :for="'qty-'+index">الكمية</label><input type="number" inputmode="numeric" min="1" max="10000" step="1" class="form-control" :id="'qty-'+index" :name="'lines['+index+'][quantity]'" x-model="line.quantity" required></div>
                <div class="col-md-6" x-show="line.line_type === 'MANUFACTURED' && (@js($isReview) ? Boolean(line.requested_fabric || line.requested_color) : line.fabric_selection === 'CUSTOM')"><label class="form-label" :for="'requested-fabric-'+index">وصف القماش الخاص المطلوب</label><input class="form-control" :id="'requested-fabric-'+index" :name="'lines['+index+'][requested_fabric]'" x-model="line.requested_fabric" maxlength="1000" @readonly($isReview) :disabled="!@js($isReview) && line.fabric_selection !== 'CUSTOM'" placeholder="اسم القماش أو خامته أو رقم كتالوجك"></div>
                <div class="col-md-6" x-show="line.line_type === 'MANUFACTURED' && (@js($isReview) ? Boolean(line.requested_fabric || line.requested_color) : line.fabric_selection === 'CUSTOM')"><label class="form-label" :for="'requested-color-'+index">اللون الخاص المطلوب</label><input class="form-control" :id="'requested-color-'+index" :name="'lines['+index+'][requested_color]'" x-model="line.requested_color" maxlength="500" @readonly($isReview) :disabled="!@js($isReview) && line.fabric_selection !== 'CUSTOM'" placeholder="اسم اللون أو رمزه أو وصفه"></div>
                <p class="text-muted mb-0" x-show="line.line_type === 'MANUFACTURED' && (@js($isReview) ? Boolean(line.requested_fabric || line.requested_color) : line.fabric_selection === 'CUSTOM')">{{ $isReview ? 'طلب العميل الأصلي محفوظ أعلاه. ربط القماش واللون بالمخزون اختياري لإرسال العرض، ويلزم قبل صرف القماش وبدء التنجيد.' : 'يمكنك وصف قماش ولون خارج الكتالوج؛ ستراجع خدمة العملاء طلبك وتحدد القماش واللون المتاحين لتأكيدهما معك.' }}</p>
                @if($isReview)
                    <div class="col-6 col-md-4"><label class="form-label" :for="'price-'+index">سعر الوحدة (ر.س)</label><input type="number" inputmode="decimal" min="0" max="9999999" step="0.01" class="form-control" :id="'price-'+index" :name="'lines['+index+'][unit_price]'" x-model="line.unit_price" required></div>
                    <div class="col-6 col-md-4"><label class="form-label" :for="'discount-'+index">خصم البند (ر.س)</label><input type="number" inputmode="decimal" min="0" step="0.01" class="form-control" :id="'discount-'+index" :name="'lines['+index+'][discount_amount]'" x-model="line.discount_amount"></div>
                @endif
                <div class="col-12"><label class="form-label" :for="'note-'+index">ملاحظات المنتج</label><input class="form-control" :id="'note-'+index" :name="'lines['+index+'][notes]'" x-model="line.notes" maxlength="2000"></div>
            </div>
        </div></section>
    </template>
    <div class="d-grid d-md-flex flex-md-wrap gap-2">
        <button class="btn btn-outline-primary" type="button" @click="lines.push({...blank, _key: crypto.randomUUID()})" :disabled="lines.length >= 100">إضافة منتج يُصنّع حسب الطلب</button>
        <button class="btn btn-outline-success" type="button" @click="lines.push({...blank, line_type: 'READY_STOCK', _key: crypto.randomUUID()})" :disabled="lines.length >= 100">إضافة منتج جاهز</button>
    </div>
    <noscript><div class="alert alert-warning">يلزم تفعيل JavaScript لإدخال المنتجات.</div></noscript>
</div>
