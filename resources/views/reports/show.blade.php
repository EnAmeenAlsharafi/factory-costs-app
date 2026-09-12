@extends('layouts.app')

@section('title', 'تفاصيل تقرير الإنتاج ' . $report->production_date->format('Y-m-d') . ' — إدارة تكاليف المصنع')

@section('content')
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">
                تقرير إنتاج وتكاليف يوم: {{ $report->production_date->format('Y-m-d') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">
                تم الإنشاء: {{ $report->created_at->format('Y-m-d H:i') }} | آخر تحديث: {{ $report->updated_at->format('Y-m-d H:i') }}
                @if($report->creator)
                    | المسؤول: {{ $report->creator->name }}
                @endif
            </p>
        </div>
        <div class="no-print" style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ طباعة
            </button>
            <a href="{{ route('reports.edit', $report) }}" class="btn btn-secondary">
                ✏️ تعديل
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                العودة للسجلات
            </a>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-title">إجمالي عدد القطع المنتجة</div>
        <div class="kpi-value">{{ number_format($report->total_quantity) }} <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">قطعة</span></div>
    </div>
    <div class="kpi-card" style="border-right: 4px solid var(--primary);">
        <div class="kpi-title">إجمالي تكلفة اليوم</div>
        <div class="kpi-value" style="color: var(--primary);">{{ number_format($report->total_cost, 2) }} <span style="font-size: 0.9rem; font-weight: normal;">ر.س</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">تكلفة الخشب الإجمالية</div>
        <div class="kpi-value">{{ number_format($report->total_wood, 2) }} <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">ر.س</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">تكلفة الأقمشة الإجمالية</div>
        <div class="kpi-value">{{ number_format($report->total_fabric, 2) }} <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">ر.س</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">تكلفة الإسفنج الإجمالية</div>
        <div class="kpi-value">{{ number_format($report->total_foam, 2) }} <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">ر.س</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">المسامير ومواد التغليف</div>
        <div class="kpi-value">{{ number_format($report->total_nails + $report->total_packaging, 2) }} <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">ر.س</span></div>
    </div>
</div>

<!-- Active Production Items Table -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <div class="card-title">
            الأصناف المنتجة اليوم ({{ $activeItems->count() }} صنف)
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            مجموع الكميات: {{ number_format($report->total_quantity) }} قطعة
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">م</th>
                    <th style="width: 60px; text-align: center;">الكود</th>
                    <th>الصنف والمقاس</th>
                    <th style="width: 80px; text-align: center;">العدد</th>
                    <th style="width: 120px;">نوع التخزين</th>
                    <th style="width: 140px;">شركة القماش</th>
                    <th style="width: 110px;">نوع القماش</th>
                    <th style="width: 110px; text-align: left;">الخشب</th>
                    <th style="width: 110px; text-align: left;">الإسفنج</th>
                    <th style="width: 110px; text-align: left;">الأقمشة</th>
                    <th style="width: 130px; text-align: left;">إجمالي الصنف</th>
                    <th style="width: 90px; text-align: center;" class="no-print">تفاصيل</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activeItems as $item)
                    <tr>
                        <td style="text-align: center; color: var(--text-muted);">{{ $item->product->order }}</td>
                        <td style="text-align: center; font-weight: 800; color: var(--primary);">{{ $item->product->code }}</td>
                        <td style="font-weight: 600;">{{ $item->product->full_name }}</td>
                        <td style="text-align: center; font-weight: 800; font-size: 1rem;">{{ $item->quantity }}</td>
                        <td>
                            <span class="badge {{ $item->storage_type == 'بتخزين' ? 'badge-primary' : 'badge-muted' }}">
                                {{ $item->storage_type }}
                            </span>
                        </td>
                        <td>{{ $item->fabricCompany?->name ?? '—' }}</td>
                        <td>{{ $item->fabricType?->name ?? '—' }}</td>
                        <td style="text-align: left;">{{ number_format($item->total_wood, 2) }} ر.س</td>
                        <td style="text-align: left;">{{ number_format($item->total_foam, 2) }} ر.س</td>
                        <td style="text-align: left;">{{ number_format($item->total_fabric, 2) }} ر.س</td>
                        <td style="text-align: left; font-weight: 800; color: var(--primary);">
                            {{ number_format($item->line_total_cost, 2) }} ر.س
                        </td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleDetails({{ $item->id }})">
                                تفصيل
                            </button>
                        </td>
                    </tr>
                    <!-- Expandable Row Detail -->
                    <tr id="details-{{ $item->id }}" style="display: none; background-color: #fafbfc;">
                        <td colspan="12" style="padding: 1rem 1.5rem;">
                            <div style="font-weight: 700; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-heading);">
                                تفصيل تكاليف الوحدة والتكاليف الإجمالية للصنف: {{ $item->product->full_name }} (كود {{ $item->product->code }})
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; font-size: 0.85rem;">
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">نقل المواد الخام:</span>
                                    <strong>{{ number_format($item->total_transport, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">الخشب:</span>
                                    <strong>{{ number_format($item->total_wood, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">الإسفنج:</span>
                                    <strong>{{ number_format($item->total_foam, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">الأقمشة ({{ $item->unit_fabric_meters }}م × {{ $item->unit_fabric_price_per_meter }} ر.س):</span>
                                    <strong>{{ number_format($item->total_fabric, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">المسامير:</span>
                                    <strong>{{ number_format($item->total_nails, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">مواد التغليف:</span>
                                    <strong>{{ number_format($item->total_packaging, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">الدهانات:</span>
                                    <strong>{{ number_format($item->total_paint, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">المفصلات:</span>
                                    <strong>{{ number_format($item->total_hinges, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">أجور نجارين / منجدين / مغلفين:</span>
                                    <strong>{{ number_format($item->total_carpentry_wages + $item->total_upholstery_wages + $item->total_packaging_wages, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">أجور إدارية ودعاية وشحن:</span>
                                    <strong>{{ number_format($item->total_administrative_wages + $item->total_advertising + $item->total_shipping, 2) }} ر.س</strong>
                                </div>
                                <div style="background: #fff; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                    <span style="color: var(--text-muted); display: block;">مصاريف نثرية وهامش ربح:</span>
                                    <strong>{{ number_format($item->total_miscellaneous + $item->total_profit_margin, 2) }} ر.س</strong>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            لم يتم تسجيل أي إنتاج (الكميات = 0 لجميع الأصناف).
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="3" style="text-align: center;">الإجمالي اليومي</td>
                    <td style="text-align: center; font-size: 1.1rem; color: var(--primary);">{{ number_format($report->total_quantity) }}</td>
                    <td colspan="3"></td>
                    <td style="text-align: left;">{{ number_format($report->total_wood, 2) }} ر.س</td>
                    <td style="text-align: left;">{{ number_format($report->total_foam, 2) }} ر.س</td>
                    <td style="text-align: left;">{{ number_format($report->total_fabric, 2) }} ر.س</td>
                    <td style="text-align: left; font-size: 1.15rem; color: var(--primary);">
                        {{ number_format($report->total_cost, 2) }} ر.س
                    </td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Complete Cost Breakdown Table (All 16 Excel Cost Elements) -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <div class="card-title">
            ملخص تكاليف اليوم حسب البنود الستة عشر (مطابق لمعادلات الإكسل)
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>البند</th>
                    <th>الاسم في الإكسل</th>
                    <th style="text-align: left;">إجمالي التكلفة</th>
                    <th style="text-align: left;">النسبة من الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $categories = [
                        ['name' => 'مصاريف نقل المواد الخام', 'col' => 'العمود H', 'val' => $report->total_transport],
                        ['name' => 'الخشب (تلقائي)', 'col' => 'العمود I', 'val' => $report->total_wood],
                        ['name' => 'الإسفنج', 'col' => 'العمود J', 'val' => $report->total_foam],
                        ['name' => 'الأقمشة (تلقائي)', 'col' => 'العمود K', 'val' => $report->total_fabric],
                        ['name' => 'الدهانات', 'col' => 'العمود L', 'val' => $report->total_paint],
                        ['name' => 'المسامير', 'col' => 'العمود M', 'val' => $report->total_nails],
                        ['name' => 'المفصلات', 'col' => 'العمود N', 'val' => $report->total_hinges],
                        ['name' => 'مواد التغليف', 'col' => 'العمود O', 'val' => $report->total_packaging],
                        ['name' => 'أجور نجارين', 'col' => 'العمود P', 'val' => $report->total_carpentry_wages],
                        ['name' => 'أجور منجدين', 'col' => 'العمود Q', 'val' => $report->total_upholstery_wages],
                        ['name' => 'أجور مغلفين', 'col' => 'العمود R', 'val' => $report->total_packaging_wages],
                        ['name' => 'أجور إدارية', 'col' => 'العمود S', 'val' => $report->total_administrative_wages],
                        ['name' => 'دعاية وإعلان', 'col' => 'العمود T', 'val' => $report->total_advertising],
                        ['name' => 'مصاريف الشحن', 'col' => 'العمود U', 'val' => $report->total_shipping],
                        ['name' => 'مصاريف نثرية', 'col' => 'العمود V', 'val' => $report->total_miscellaneous],
                        ['name' => 'هامش ربح', 'col' => 'العمود W', 'val' => $report->total_profit_margin],
                    ];
                @endphp
                @foreach($categories as $cat)
                    @php
                        $percentage = $report->total_cost > 0 ? ($cat['val'] / $report->total_cost) * 100 : 0;
                    @endphp
                    <tr>
                        <td style="font-weight: 600;">{{ $cat['name'] }}</td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $cat['col'] }}</td>
                        <td style="text-align: left; font-weight: 700;">{{ number_format($cat['val'], 2) }} ر.س</td>
                        <td style="text-align: left; color: var(--text-muted);">{{ number_format($percentage, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="2">إجمالي التكلفة الكلية (العمود X)</td>
                    <td style="text-align: left; font-size: 1.15rem; color: var(--primary);">{{ number_format($report->total_cost, 2) }} ر.س</td>
                    <td style="text-align: left;">100%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Notes section if present -->
@if($report->notes)
    <div class="card">
        <div class="card-header">
            <div class="card-title">ملاحظات اليوم</div>
        </div>
        <div class="card-body">
            <p style="white-space: pre-line; color: var(--text-heading);">{{ $report->notes }}</p>
        </div>
    </div>
@endif

<!-- Expandable Idle Items Section (Rest of the 40 items) -->
<div class="card no-print" style="margin-top: 1.5rem;">
    <div class="card-header" style="cursor: pointer;" onclick="toggleIdleItems()">
        <div class="card-title" style="font-size: 1rem;">
            <span>➕ عرض باقي الأصناف غير المنتجة في هذا اليوم ({{ $idleItems->count() }} صنف)</span>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-muted);">انقر للإظهار / الإخفاء</span>
    </div>
    <div id="idleItemsContainer" style="display: none;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">م</th>
                        <th style="width: 60px; text-align: center;">الكود</th>
                        <th>الصنف والمقاس</th>
                        <th style="width: 80px; text-align: center;">العدد</th>
                        <th style="width: 120px;">نوع التخزين</th>
                        <th style="text-align: left;">التكلفة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($idleItems as $item)
                        <tr style="color: var(--text-muted);">
                            <td style="text-align: center;">{{ $item->product->order }}</td>
                            <td style="text-align: center; font-weight: 700;">{{ $item->product->code }}</td>
                            <td>{{ $item->product->full_name }}</td>
                            <td style="text-align: center;">0</td>
                            <td>{{ $item->storage_type }}</td>
                            <td style="text-align: left;">0.00 ر.س</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    if (row) {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
}

function toggleIdleItems() {
    const container = document.getElementById('idleItemsContainer');
    if (container) {
        container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
}
</script>
@endsection
