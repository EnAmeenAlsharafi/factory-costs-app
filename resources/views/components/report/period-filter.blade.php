@props(['period', 'action' => null, 'keep' => [], 'showPeriod' => true])
@php
    $resetUrl = url()->current().(collect($keep)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? '?'.http_build_query(collect($keep)->filter(fn ($value) => $value !== null && $value !== '')->all()) : '');
@endphp
<div class="report-filter-shell report-no-print" x-data="reportFilters(@js($period?->preset ?? 'this_month'))">
    <div class="report-filter-mobile">
        <button type="button" class="btn btn-factory-primary" data-bs-toggle="offcanvas" data-bs-target="#report-filter-drawer" aria-controls="report-filter-drawer"><i class="fas fa-sliders" aria-hidden="true"></i> تصفية <span x-show="chips.length" x-text="chips.length" class="badge bg-white text-dark"></span></button>
        <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">مسح الكل</a>
    </div>
    <form method="GET" action="{{ $action ?? url()->current() }}" class="report-filters">
        @foreach ($keep as $name => $value)
            @if ($value !== null && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <div class="offcanvas-md offcanvas-bottom report-filter-drawer" tabindex="-1" id="report-filter-drawer" aria-labelledby="report-filter-title">
            <div class="offcanvas-header border-bottom"><h2 id="report-filter-title" class="h6 mb-0 fw-bold">تصفية التقرير</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#report-filter-drawer" aria-label="إغلاق التصفية"></button></div>
            <div class="offcanvas-body">
                <div class="report-filter-primary">
                    @if ($showPeriod && $period)
                        <div class="report-period-control"><label for="report-period" class="form-label fs-7">الفترة</label><select id="report-period" name="period" class="form-select" x-model="preset">
                            @foreach (\App\Domain\Reports\ReportPeriod::PRESETS as $key => $label)<option value="{{ $key }}" @selected($period->preset === $key)>{{ $label }}</option>@endforeach
                        </select></div>
                        <div x-show="preset === 'custom'" x-cloak><label for="report-from" class="form-label fs-7">من</label><input type="date" id="report-from" name="from" class="form-control" value="{{ $period->startDate() }}" :disabled="preset !== 'custom'"></div>
                        <div x-show="preset === 'custom'" x-cloak><label for="report-to" class="form-label fs-7">إلى</label><input type="date" id="report-to" name="to" class="form-control" value="{{ $period->endDate() }}" :disabled="preset !== 'custom'"></div>
                    @endif
                    @if (trim((string) $slot) !== '')<button type="button" class="btn btn-outline-secondary report-advanced-toggle" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="report-advanced">فلاتر متقدمة <i class="fas fa-angle-down" aria-hidden="true"></i></button>@endif
                    <div class="report-filter-actions"><button type="submit" class="btn btn-factory-primary">تطبيق</button><a href="{{ $resetUrl }}" class="btn btn-outline-secondary">مسح الكل</a></div>
                </div>
                @if (trim((string) $slot) !== '')<div id="report-advanced" class="report-advanced row g-2 align-items-end" x-show="advanced || mobile" x-cloak>{{ $slot }}</div>@endif
            </div>
        </div>
    </form>
    <div class="report-filter-chips" aria-label="الفلاتر المطبقة">
        <template x-for="chip in chips" :key="chip.name"><a :href="chip.removeUrl" class="report-filter-chip"><span x-text="chip.label"></span><span aria-hidden="true">×</span><span class="visually-hidden">إزالة الفلتر</span></a></template>
    </div>
</div>
