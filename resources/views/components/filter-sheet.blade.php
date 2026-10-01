@props([
    'id',
    'action',
    'activeCount' => 0,
    'resetUrl' => null,
    'title' => 'تصفية النتائج',
])

{{--
    Responsive filter panel using Bootstrap's `offcanvas-md`:
    < 768px: collapsed behind a "تصفية" button and opens as a bottom sheet.
    >= 768px: rendered inline as the normal filter card (no duplicate markup, same GET form).
    Put the always-visible search field in the `search` slot and the secondary filters in the default slot.
--}}
<div class="filter-sheet-wrapper mb-3">
    <form action="{{ $action }}" method="GET" role="search">
        <div class="d-flex gap-2 align-items-stretch">
            @isset($search)
                <div class="flex-grow-1 min-w-0">{{ $search }}</div>
            @endisset
            <button type="button" class="btn btn-outline-secondary d-md-none flex-shrink-0 position-relative" data-bs-toggle="offcanvas" data-bs-target="#{{ $id }}" aria-controls="{{ $id }}">
                <i class="fas fa-sliders" aria-hidden="true"></i>
                <span>تصفية</span>
                @if ($activeCount > 0)
                    <span class="badge rounded-pill bg-warning text-dark">{{ $activeCount }}</span>
                @endif
            </button>
        </div>

        <div class="offcanvas-md offcanvas-bottom filter-sheet" tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}-label">
            <div class="offcanvas-header border-bottom">
                <h2 class="offcanvas-title h6 fw-bold mb-0" id="{{ $id }}-label">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#{{ $id }}" aria-label="إغلاق التصفية"></button>
            </div>
            <div class="offcanvas-body">
                <div class="row g-2 align-items-end w-100 m-0">
                    {{ $slot }}
                    <div class="col-12 col-md-auto d-flex gap-2 filter-sheet-actions">
                        <button type="submit" class="btn btn-factory-primary flex-grow-1">
                            <i class="fas fa-filter" aria-hidden="true"></i> تطبيق
                        </button>
                        @if ($resetUrl && $activeCount > 0)
                            <a href="{{ $resetUrl }}" class="btn btn-outline-secondary flex-grow-1">
                                <i class="fas fa-rotate-left" aria-hidden="true"></i> مسح الفلاتر
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
