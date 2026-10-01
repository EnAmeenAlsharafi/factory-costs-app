@extends('layouts.app')
@section('title', 'مركز التقارير')
@section('page-title', 'مركز التقارير')
@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold mb-2">مركز التقارير</h1>
        <p class="text-muted fs-7 mb-0">اختر مجال المتابعة للوصول إلى مؤشرات الأداء والتفاصيل. تظهر التقارير حسب صلاحياتك.</p>
        @can('reports.profitability')<p class="report-note mt-2 mb-0">المساهمة التشغيلية = القيمة التجارية − تكلفة المواد الفعلية، وليست صافي ربح محاسبي.</p>@endcan
    </div>
    <div class="report-catalogue">
        @foreach ($groups as $group)
            @php
                $titles = ['التحليل التجاري' => 'تجاري', 'التصنيع' => 'الإنتاج', 'المواد والمخزون' => 'المخزون', 'التنفيذ والتوصيل' => 'التوصيل', 'الذمم والتحصيل' => 'التحصيل'];
                $links = collect($group['reports']);
                $variants = [
                    'reports.inventory' => ['stock' => ['أرصدة المخزون', 'الأرصدة الفعلية والوارد المتوقع.'], 'valuation' => ['قيمة المخزون', 'القيمة التشغيلية حسب تكلفة الدفعات.'], 'fabric' => ['تحليل الأقمشة', 'المتاح حسب القماش والمورد واللون.'], 'shortages' => ['النواقص', 'عجز المواد وتأثيره على الإنتاج.'], 'slow' => ['المواد بطيئة الحركة', 'أرصدة لم تُصرف خلال الفترة المحددة.']],
                    'reports.fulfillment' => ['pipeline' => ['مسار التنفيذ', 'من طلب العميل إلى التسليم.'], 'finished_goods' => ['المنتجات الجاهزة', 'الكميات الجاهزة ومدة الانتظار.'], 'delivery' => ['تحليل التوصيل', 'حالات التسليم والتركيب والتعذر.'], 'returns' => ['المرتجعات', 'بلاغات المرتجعات والكميات المتأثرة.']],
                    'reports.production.quality' => ['waste' => ['الهدر', 'المواد والأقسام وأسباب الهدر.'], 'rework' => ['إعادة العمل', 'الكميات المتأثرة والمواد الإضافية.'], 'quality' => ['الجودة', 'الحوادث والخطورة ومدة المعالجة.']],
                    'reports.receivables' => ['balances' => ['المستحقات وأعمار الديون', 'الأرصدة الحالية والتأخر في السداد.'], 'collections' => ['التحصيل', 'الدفعات المؤكدة والمعلقة.']],
                ];
                $links = $links->flatMap(function ($report) use ($variants) {
                    if (! isset($variants[$report['route']])) { return [$report]; }
                    return collect($variants[$report['route']])->filter(fn ($item, $tab) => $report['route'] !== 'reports.inventory' || $tab !== 'valuation' || auth()->user()->can('costing.view'))->map(fn ($item, $tab) => array_merge($report, ['title' => $item[0], 'purpose' => $item[1], 'params' => ['tab' => $tab]]));
                });
            @endphp
            <section class="report-catalogue-group" aria-labelledby="group-{{ $loop->index }}">
                <h2 id="group-{{ $loop->index }}" class="report-section-title">{{ $titles[$group['title']] ?? $group['title'] }}</h2>
                @foreach ($links as $report)
                    <a href="{{ route($report['route'], $report['params'] ?? []) }}" class="report-catalogue-link">
                        <i class="fas {{ $report['icon'] }}" aria-hidden="true"></i>
                        <span><strong>{{ $report['title'] }}</strong><p>{{ $report['purpose'] }}</p><span class="report-open">فتح التقرير</span></span>
                        <i class="fas fa-angle-left text-muted mt-2" aria-hidden="true"></i>
                    </a>
                @endforeach
            </section>
        @endforeach
    </div>
</div>
@endsection
