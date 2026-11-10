{{-- Worker points: which category this bed counts as, so upholstery (and other points departments) earn the right points. --}}
@if ($order->customerOrderLine && auth()->user()->can('production.release'))
    @php
        $linePointCategory = $order->customerOrderLine->pointCategory;
        $modelPointCategory = $order->productModel?->pointCategory;
        $effectivePointCategory = $linePointCategory ?? $modelPointCategory;
        $pointCategories = \App\Models\ProductionPointCategory::active()->ordered()->get();
    @endphp
    <div id="points" tabindex="-1" class="recipe-link-box mt-3 {{ $effectivePointCategory ? '' : 'is-missing' }}">
        <p class="fw-semibold mb-1"><i class="fas fa-medal text-warning me-1" aria-hidden="true"></i> تصنيف النقاط:
            @if ($effectivePointCategory)
                {{ $effectivePointCategory->label }} <span class="text-muted fw-normal fs-7">({{ $linePointCategory ? 'خاص بهذا الطلب' : 'حسب الموديل' }})</span>
            @else
                <span class="text-warning-emphasis">غير مصنف — لا يكسب العمال نقاطاً عليه</span>
            @endif
        </p>
        <details @if (! $effectivePointCategory) open @endif>
            <summary class="fs-7 fw-semibold text-primary">{{ $effectivePointCategory ? 'تغيير التصنيف' : 'تحديد التصنيف' }}</summary>
            <form action="{{ route('production.orders.point-category', $order) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">
                @csrf @method('PUT')
                <label for="order-point-category" class="visually-hidden">تصنيف النقاط</label>
                <select id="order-point-category" name="point_category_id" class="form-select flex-grow-1" style="min-width: 220px;">
                    <option value="">{{ $modelPointCategory ? 'حسب الموديل: '.$modelPointCategory->label : '— بلا تصنيف —' }}</option>
                    @foreach ($pointCategories as $pointCategory)
                        <option value="{{ $pointCategory->id }}" @selected($linePointCategory?->id === $pointCategory->id)>{{ $pointCategory->label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary text-nowrap">حفظ</button>
            </form>
        </details>
    </div>
@endif
