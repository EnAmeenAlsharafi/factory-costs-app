{{--
    The models in one points category (or with none): search, tick, and move the ticked ones to another
    category in one step. $models, $categories, $currentId (null = unclassified), $key (unique per list).
--}}
@php
    $targets = $categories->where('is_active', true)->reject(fn ($category) => $category->id === $currentId);
    $items = $models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name_ar, 'code' => $model->model_code, 'active' => (bool) $model->is_active])->values();
@endphp
<div x-data="{
        q: '',
        selected: [],
        items: @js($items),
        get visible() { const q = window.normalizeArabic(this.q.trim()); return q ? this.items.filter(item => window.normalizeArabic(item.name + ' ' + item.code).includes(q)) : this.items; },
        get allVisibleSelected() { return this.visible.length > 0 && this.visible.every(item => this.selected.includes(item.id)); },
        toggleAll() { const ids = this.visible.map(item => item.id); this.selected = this.allVisibleSelected ? this.selected.filter(id => !ids.includes(id)) : [...new Set([...this.selected, ...ids])]; },
     }">
    @if ($models->isEmpty())
        <p class="small text-muted mb-0">{{ $currentId ? 'لا توجد موديلات في هذا التصنيف بعد. انقل إليه موديلات من تصنيف آخر أو من «موديلات بلا تصنيف».' : 'كل الموديلات مصنفة.' }}</p>
    @else
        <form method="POST" action="{{ route('production.points.models.move') }}">
            @csrf @method('PUT')
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <div class="model-mover-search flex-grow-1">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <label class="visually-hidden" for="search-{{ $key }}">ابحث في الموديلات</label>
                    <input type="search" id="search-{{ $key }}" class="form-control form-control-sm" placeholder="ابحث باسم الموديل أو كوده…" x-model="q" autocomplete="off">
                </div>
                <button type="button" class="btn btn-sm btn-light border" @click="toggleAll()" x-text="allVisibleSelected ? 'إلغاء التحديد' : 'تحديد الظاهر (' + visible.length + ')'"></button>
            </div>
            <ul class="model-mover-list list-unstyled mb-2">
                <template x-for="item in visible" :key="item.id">
                    <li>
                        <label class="model-mover-item" :class="{ 'is-selected': selected.includes(item.id) }">
                            <input type="checkbox" class="form-check-input mt-0" name="model_ids[]" :value="item.id" x-model.number="selected">
                            <span class="min-w-0"><span class="d-block text-truncate fw-semibold" x-text="item.name"></span><bdi class="small text-muted" x-text="item.code"></bdi><span class="small text-warning-emphasis" x-show="!item.active"> · غير نشط</span></span>
                        </label>
                    </li>
                </template>
            </ul>
            <p class="small text-muted mb-2" x-show="visible.length === 0">لا يوجد موديل مطابق للبحث.</p>
            <div class="model-mover-bar" x-show="selected.length > 0" x-cloak>
                <span class="fw-semibold"><span x-text="selected.length"></span> موديل محدد</span>
                <label class="visually-hidden" for="target-{{ $key }}">انقل إلى</label>
                <select class="form-select form-select-sm" id="target-{{ $key }}" name="target_category_id">
                    @foreach ($targets as $target)<option value="{{ $target->id }}">{{ $target->label }}</option>@endforeach
                    @if ($currentId)<option value="">— إزالة التصنيف —</option>@endif
                </select>
                <button class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-right-left me-1" aria-hidden="true"></i> {{ $currentId ? 'نقل' : 'تصنيف' }}</button>
            </div>
        </form>
    @endif
</div>
