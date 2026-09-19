@props([
    'name',
    'endpoint',
    'placeholder' => 'ابحث...',
    'initialId' => '',
    'initialLabel' => '',
    'parentParam' => '',
    'getParentId' => 'null',
    'onChange' => 'null',
])

<div x-data="typeaheadSelect({
    name: '{{ $name }}',
    endpoint: '{{ $endpoint }}',
    placeholder: '{{ $placeholder }}',
    initialId: '{{ $initialId }}',
    initialLabel: '{{ $initialLabel }}',
    parentParam: '{{ $parentParam }}',
    getParentId: {{ $getParentId }},
    onChange: {{ $onChange }}
})" class="typeahead-container">
    <input type="hidden" name="{{ $name }}" :value="selectedId">
    <div class="input-group input-group-sm">
        <input type="text" class="form-control form-control-sm"
               x-model="searchQuery"
               @focus="onFocus"
               @input.debounce.250ms="onInput"
               @keydown.arrow-down.prevent="navigateDown"
               @keydown.arrow-up.prevent="navigateUp"
               @keydown.enter.prevent="selectHighlighted"
               @keydown.escape="closeDropdown"
               @blur="closeDropdown"
               placeholder="{{ $placeholder }}"
               aria-label="{{ $placeholder }}">
        <button class="btn btn-outline-secondary btn-sm" type="button" x-show="selectedId" @click="clearSelection" tabindex="-1">
            <i class="fas fa-times fs-8"></i>
        </button>
    </div>
    <div class="typeahead-dropdown" x-show="isOpen" x-cloak>
        <div x-show="loading" class="p-2 text-center text-muted fs-8">
            <i class="fas fa-spinner fa-spin me-1"></i> جاري البحث...
        </div>
        <template x-for="(item, index) in results" :key="item.id">
            <div class="typeahead-item"
                 :class="{ 'active': index === highlightedIndex }"
                 @mousedown.prevent="selectItem(item)">
                <span x-text="item.name_ar || item.name || item.label"></span>
                <span class="item-code" x-text="item.code || ''"></span>
            </div>
        </template>
        <div x-show="!loading && results.length === 0" class="p-2 text-center text-muted fs-8">
            لا توجد نتائج مطابقة
        </div>
    </div>
</div>
