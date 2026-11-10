@extends('layouts.app')
@section('title', 'مركز التقارير')
@section('page-title', 'مركز التقارير')
@section('content')
@php
    $groupNames = ['التحليل التجاري' => 'تجاري', 'التصنيع' => 'الإنتاج', 'المواد والمخزون' => 'المخزون', 'التنفيذ والتوصيل' => 'التوصيل', 'الذمم والتحصيل' => 'التحصيل', 'الموظفون والمكافآت' => 'الموظفون'];
    $variants = [
        'reports.inventory' => ['stock' => ['أرصدة المخزون', 'الأرصدة الفعلية والوارد المتوقع.'], 'valuation' => ['قيمة المخزون', 'القيمة التشغيلية حسب تكلفة الدفعات.'], 'fabric' => ['تحليل الأقمشة', 'المتاح حسب القماش والمورد واللون.'], 'shortages' => ['النواقص', 'عجز المواد وتأثيره على الإنتاج.'], 'slow' => ['المواد بطيئة الحركة', 'أرصدة لم تُصرف خلال الفترة المحددة.']],
        'reports.fulfillment' => ['pipeline' => ['مسار التنفيذ', 'من طلب العميل إلى التسليم.'], 'finished_goods' => ['المنتجات الجاهزة', 'الكميات الجاهزة ومدة الانتظار.'], 'delivery' => ['تحليل التوصيل', 'حالات التسليم والتركيب والتعذر.'], 'returns' => ['المرتجعات', 'بلاغات المرتجعات والكميات المتأثرة.']],
        'reports.production.quality' => ['waste' => ['الهدر', 'المواد والأقسام وأسباب الهدر.'], 'rework' => ['إعادة العمل', 'الكميات المتأثرة والمواد الإضافية.'], 'quality' => ['الجودة', 'الحوادث والخطورة ومدة المعالجة.']],
        'reports.receivables' => ['balances' => ['المستحقات وأعمار الديون', 'الأرصدة الحالية والتأخر في السداد.'], 'collections' => ['التحصيل', 'الدفعات المؤكدة والمعلقة.']],
    ];
    $featuredRoutes = ['reports.dashboard', 'reports.exceptions', 'reports.workers.index'];
    $sections = $groups->map(function ($group) use ($groupNames, $variants) {
        $reports = collect($group['reports'])->flatMap(function ($report) use ($variants) {
            if (! isset($variants[$report['route']])) {
                return [$report];
            }

            return collect($variants[$report['route']])
                ->filter(fn ($item, $tab) => $report['route'] !== 'reports.inventory' || $tab !== 'valuation' || auth()->user()->can('costing.view'))
                ->map(fn ($item, $tab) => array_merge($report, ['title' => $item[0], 'purpose' => $item[1], 'params' => ['tab' => $tab]]))
                ->values();
        });

        return ['title' => $groupNames[$group['title']] ?? $group['title'], 'icon' => $group['icon'], 'reports' => $reports];
    })->values()->map(fn ($section, $index) => array_merge($section, ['key' => 'group-'.$index]));
    $featured = $sections->flatMap(fn ($section) => $section['reports'])->filter(fn ($report) => in_array($report['route'], $featuredRoutes, true))->unique('route')->values();
    $reportCount = $sections->sum(fn ($section) => $section['reports']->count());
    $searchText = fn (array $report, array $section) => $report['title'].' '.$report['purpose'].' '.$section['title'];
    $allTexts = $sections->flatMap(fn ($section) => $section['reports']->map(fn ($report) => $searchText($report, $section)))->values();
@endphp
<div class="report-workspace d-flex flex-column gap-3" x-data="{ search: '', group: 'all', matches(text) { const q = window.normalizeArabic ? window.normalizeArabic(this.search.trim()) : this.search.trim(); if (!q) return true; return (window.normalizeArabic ? window.normalizeArabic(text) : text).includes(q); } }">
    <header class="reports-hero">
        <div class="min-w-0">
            <h1 id="page-heading" class="reports-hero-title">مركز التقارير</h1>
            <p class="reports-hero-text">{{ $reportCount }} تقريراً لمتابعة المبيعات والإنتاج والموظفين والمخزون والتحصيل. تظهر التقارير حسب صلاحياتك.</p>
            <div class="reports-search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <label for="report-search" class="visually-hidden">ابحث عن تقرير</label>
                <input id="report-search" type="search" class="form-control" placeholder="ابحث عن تقرير: الهدر، الموظفين، الأقمشة…" x-model="search" autocomplete="off" enterkeyhint="search">
            </div>
        </div>
        @can('reports.profitability')<p class="reports-hero-note mb-0"><i class="fas fa-circle-info me-1" aria-hidden="true"></i> المساهمة التشغيلية = القيمة التجارية − تكلفة المواد الفعلية، وليست صافي ربح محاسبي.</p>@endcan
    </header>

    @if ($featured->isNotEmpty())
        <section aria-labelledby="featured-heading" x-show="!search">
            <h2 id="featured-heading" class="visually-hidden">الأكثر استخداماً</h2>
            <div class="reports-featured">
                @foreach ($featured as $report)
                    <a href="{{ route($report['route'], $report['params'] ?? []) }}" class="reports-featured-card">
                        <span class="reports-icon is-large" aria-hidden="true"><i class="fas {{ $report['icon'] }}"></i></span>
                        <span class="min-w-0"><strong>{{ $report['title'] }}</strong><span>{{ $report['purpose'] }}</span></span>
                        <i class="fas fa-arrow-left reports-go" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <nav class="reports-tabs" aria-label="مجالات التقارير">
        <button type="button" class="reports-tab" :class="{ 'is-active': group === 'all' }" :aria-pressed="(group === 'all').toString()" @click="group = 'all'">الكل <span>{{ $reportCount }}</span></button>
        @foreach ($sections as $section)
            <button type="button" class="reports-tab" :class="{ 'is-active': group === '{{ $section['key'] }}' }" :aria-pressed="(group === '{{ $section['key'] }}').toString()" @click="group = '{{ $section['key'] }}'"><i class="fas {{ $section['icon'] }}" aria-hidden="true"></i> {{ $section['title'] }} <span>{{ $section['reports']->count() }}</span></button>
        @endforeach
    </nav>

    @foreach ($sections as $section)
        <section class="reports-group" aria-labelledby="{{ $section['key'] }}-heading"
                 x-show="(group === 'all' || group === '{{ $section['key'] }}') && @js($section['reports']->map(fn ($report) => $searchText($report, $section))->values()).some(text => matches(text))">
            <h2 id="{{ $section['key'] }}-heading" class="reports-group-title"><i class="fas {{ $section['icon'] }}" aria-hidden="true"></i> {{ $section['title'] }}</h2>
            <div class="reports-grid">
                @foreach ($section['reports'] as $report)
                    <a href="{{ route($report['route'], $report['params'] ?? []) }}" class="reports-card" x-bind:hidden="!matches(@js($searchText($report, $section)))">
                        <span class="reports-icon" aria-hidden="true"><i class="fas {{ $report['icon'] }}"></i></span>
                        <span class="min-w-0">
                            <strong>{{ $report['title'] }}</strong>
                            <span>{{ $report['purpose'] }}</span>
                        </span>
                        <i class="fas fa-angle-left reports-go" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <div class="report-empty" role="status" x-show="search && !@js($allTexts).some(text => matches(text))" x-cloak>
        <i class="far fa-folder-open" aria-hidden="true"></i>
        <p class="fw-semibold mb-1">لا يوجد تقرير بهذا الاسم.</p>
        <p class="text-muted fs-7 mb-0">جرّب كلمة أخرى، مثل «إنتاج» أو «مخزون».</p>
    </div>
</div>
@endsection
