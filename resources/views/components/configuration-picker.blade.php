@props([
    'configurations',
    'name' => 'product_configuration_id',
    'selected' => null,
    'idPrefix' => 'cfg-picker',
])

@php
    /*
     * Models that have at least one active configuration, each with its sizes.
     * Search is client-side: the list is small and this keeps the picker instant inside a dialog.
     */
    $pickerModels = $configurations
        ->filter(fn ($configuration) => $configuration->productModel)
        ->groupBy('product_model_id')
        ->map(function ($modelConfigurations) {
            $model = $modelConfigurations->first()->productModel;

            return [
                'id' => (string) $model->id,
                'name' => $model->name_ar,
                'code' => $model->model_code,
                'image' => $model->image_url,
                'configurations' => $modelConfigurations
                    ->sortBy([['width_cm', 'asc'], ['length_cm', 'asc'], ['has_storage', 'asc']])
                    ->map(fn ($configuration) => [
                        'id' => (string) $configuration->id,
                        'code' => $configuration->configuration_code,
                        'size' => (float) $configuration->width_cm.' × '.(float) $configuration->length_cm,
                        'hasStorage' => (bool) $configuration->has_storage,
                        'suggestedName' => 'وصفة '.$model->name_ar.' '.(float) $configuration->width_cm.'×'.(float) $configuration->length_cm.' '.($configuration->has_storage ? 'مع تخزين' : 'بدون تخزين'),
                    ])->values()->all(),
            ];
        })
        ->sortBy('name')
        ->values()
        ->all();
@endphp

{{--
    Searchable product-model picker with photos, followed by the chosen model's sizes as cards.
    Posts `{{ $name }}` (the configuration id) through a hidden input, so it always sends the key.
    Dispatches `configuration-selected` with { id, suggestedName } so a form can propose a recipe name.
--}}
<div x-data="configurationPicker(@js(['models' => $pickerModels, 'selected' => (string) $selected, 'idPrefix' => $idPrefix]))" class="cfg-picker" @click.outside="open = false">
    <input type="hidden" name="{{ $name }}" :value="configurationId">

    {{-- Step 1: model --}}
    <label :for="`${idPrefix}-search`" class="form-label fw-bold fs-7" x-show="!model">الموديل <span class="text-danger" aria-hidden="true">*</span></label>

    <div x-show="!model">
        <div class="cfg-search">
            <i class="fas fa-magnifying-glass cfg-search-icon" aria-hidden="true"></i>
            <input type="search" :id="`${idPrefix}-search`" class="form-control" autocomplete="off" enterkeyhint="search"
                   placeholder="ابحث باسم الموديل أو الكود"
                   role="combobox" aria-autocomplete="list" :aria-expanded="open" :aria-controls="`${idPrefix}-list`"
                   :aria-activedescendant="open && filtered[active] ? `${idPrefix}-opt-${filtered[active].id}` : null"
                   x-model="query" @focus="open = true" @input="open = true; active = 0"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter.prevent="filtered[active] && chooseModel(filtered[active])" @keydown.escape="open = false">
        </div>
        <ul class="cfg-list" role="listbox" :id="`${idPrefix}-list`" aria-label="الموديلات" x-show="open" x-cloak>
            <template x-for="(item, index) in filtered" :key="item.id">
                <li role="option" :id="`${idPrefix}-opt-${item.id}`" :aria-selected="index === active" class="cfg-option"
                    :class="{ 'is-active': index === active }" @mousedown.prevent="chooseModel(item)" @mousemove="active = index">
                    <span class="cfg-thumb">
                        <img x-show="item.image" :src="item.image" alt="" loading="lazy" x-on:error="item.image = null">
                        <i x-show="!item.image" class="fas fa-couch" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0 flex-grow-1">
                        <span class="d-block fw-bold text-dark text-truncate" x-text="item.name"></span>
                        <span class="code-chip fs-8" x-text="item.code"></span>
                    </span>
                    <span class="text-muted fs-8 flex-shrink-0" x-text="`${item.configurations.length} مقاس`"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="cfg-empty" role="status">
                لا يوجد موديل يطابق «<span x-text="query"></span>». جرّب جزءاً من الاسم أو الكود.
            </li>
        </ul>
    </div>

    {{-- Selected model --}}
    <div x-show="model" x-cloak class="cfg-selected" aria-live="polite">
        <span class="cfg-photo">
            <img x-show="model?.image" :src="model?.image" :alt="model ? `صورة موديل ${model.name}` : ''" x-on:error="model.image = null">
            <i x-show="!model?.image" class="fas fa-couch" aria-hidden="true"></i>
        </span>
        <div class="min-w-0 flex-grow-1">
            <span class="d-block text-muted fs-8">الموديل</span>
            <span class="d-block fw-bold text-dark fs-6" x-text="model?.name"></span>
            <span class="code-chip fs-8" x-text="model?.code"></span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" @click="changeModel()">
            <i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i> تغيير
        </button>
    </div>

    {{-- Step 2: size --}}
    <fieldset x-show="model" x-cloak class="mt-3">
        <legend class="form-label fw-bold fs-7 mb-2">المقاس <span class="text-danger" aria-hidden="true">*</span></legend>
        <div class="cfg-sizes">
            <template x-for="configuration in model?.configurations || []" :key="configuration.id">
                <label class="cfg-size" :class="{ 'is-selected': configurationId === configuration.id }">
                    <input type="radio" class="visually-hidden" :name="`${idPrefix}-size`" :value="configuration.id"
                           :checked="configurationId === configuration.id" @change="chooseConfiguration(configuration)">
                    <span class="cfg-size-dim"><span class="qty" x-text="configuration.size"></span> <span class="fs-8 fw-normal">سم</span></span>
                    <span class="fs-8" :class="configuration.hasStorage ? 'text-warning-emphasis fw-bold' : 'text-muted'">
                        <i class="fas" :class="configuration.hasStorage ? 'fa-box-archive' : 'fa-minus'" aria-hidden="true"></i>
                        <span x-text="configuration.hasStorage ? 'مع تخزين' : 'بدون تخزين'"></span>
                    </span>
                    <span class="cfg-size-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                </label>
            </template>
        </div>
    </fieldset>

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@once
    @push('scripts')
        <script>
            function configurationPicker(config) {
                // Arabic-aware matching: ignore diacritics/tatweel and treat أ/إ/آ, ة/ه and ى/ي as the same letter.
                const normalize = (text) => (text || '').toString().toLowerCase()
                    .replace(/[ً-ْـ]/g, '')
                    .replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim();
                const models = config.models.map((model) => ({ ...model, haystack: normalize(`${model.name} ${model.code}`) }));
                const owner = models.find((model) => model.configurations.some((c) => c.id === config.selected));

                return {
                    idPrefix: config.idPrefix,
                    models,
                    query: '',
                    open: false,
                    active: 0,
                    model: owner || null,
                    configurationId: owner ? config.selected : '',
                    get filtered() {
                        const terms = normalize(this.query).split(/\s+/).filter(Boolean);
                        return terms.length ? this.models.filter((model) => terms.every((term) => model.haystack.includes(term))) : this.models;
                    },
                    move(step) {
                        this.open = true;
                        const count = this.filtered.length;
                        if (count) {
                            this.active = (this.active + step + count) % count;
                            this.$nextTick(() => document.getElementById(`${this.idPrefix}-opt-${this.filtered[this.active].id}`)?.scrollIntoView({ block: 'nearest' }));
                        }
                    },
                    chooseModel(model) {
                        this.model = model;
                        this.open = false;
                        this.query = '';
                        this.configurationId = '';
                        // A model with a single size needs no second decision.
                        if (model.configurations.length === 1) {
                            this.chooseConfiguration(model.configurations[0]);
                        }
                    },
                    chooseConfiguration(configuration) {
                        this.configurationId = configuration.id;
                        this.$dispatch('configuration-selected', { id: configuration.id, suggestedName: configuration.suggestedName });
                    },
                    changeModel() {
                        this.model = null;
                        this.configurationId = '';
                        this.open = true;
                        this.$nextTick(() => document.getElementById(`${this.idPrefix}-search`)?.focus());
                    },
                };
            }
        </script>
    @endpush
@endonce
