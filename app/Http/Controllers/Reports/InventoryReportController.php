<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\InventoryAnalyticsService;
use App\Domain\Reports\ReportColumn as C;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    use RendersReports;

    public const TABS = [
        'stock' => 'الأرصدة والواردة',
        'valuation' => 'القيمة التشغيلية للمخزون',
        'fabric' => 'مخزون الأقمشة',
        'slow' => 'مواد بطيئة الحركة',
        'shortages' => 'النواقص',
    ];

    public function __construct(protected InventoryAnalyticsService $inventory) {}

    public function index(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('reports.inventory'), 403);

        $tabs = collect(self::TABS)->when(! $user->can('costing.view'), fn ($tabs) => $tabs->except('valuation'));
        $tab = $tabs->has((string) $request->query('tab')) ? (string) $request->query('tab') : 'stock';
        $filters = $request->only(['material_category_id', 'search', 'state', 'material_id', 'supplier_id', 'color']);
        $threshold = in_array((int) $request->query('days'), [30, 60, 90], true) ? (int) $request->query('days') : 90;
        $extra = [];

        [$dataset, $columns, $title] = match ($tab) {
            'valuation' => [$this->inventory->stock($filters + ['state' => '']), [
                C::make('material', 'المادة'), C::make('code', 'الكود', C::CODE), C::make('category', 'الفئة'),
                C::make('stock_qty', 'الرصيد', C::QTY), C::make('unit', 'الوحدة'),
                C::make('stock_value', 'القيمة التشغيلية (تكلفة اللوت)', C::MONEY)->requires(['costing.view']),
            ], 'القيمة التشغيلية لمخزون المواد الخام'],
            'fabric' => [$this->inventory->fabricLots($filters), [
                C::make('fabric', 'القماش'), C::make('supplier', 'المورد'), C::make('color_code', 'كود اللون', C::CODE),
                C::make('color_name', 'اسم اللون'), C::make('supplier_color_code', 'كود لون المورد', C::CODE),
                C::make('lot', 'الدفعة', C::CODE), C::make('available_qty', 'المتاح', C::QTY), C::make('unit', 'الوحدة'),
                C::make('received_date', 'تاريخ الاستلام', C::DATE),
                C::make('lot_value', 'قيمة الدفعة', C::MONEY)->requires(['costing.view']),
            ], 'تحليل مخزون الأقمشة'],
            'slow' => [$this->inventory->slowMoving($threshold), [
                C::make('material', 'المادة'), C::make('code', 'الكود', C::CODE), C::make('category', 'الفئة'),
                C::make('stock_qty', 'الرصيد', C::QTY), C::make('unit', 'الوحدة'),
                C::make('stock_value', 'القيمة التشغيلية', C::MONEY)->requires(['costing.view']),
                C::make('last_issue_date', 'آخر صرف', C::DATE), C::make('days_without_issue', 'أيام بدون صرف', C::INT),
            ], 'مواد بطيئة الحركة'],
            'shortages' => [$this->inventory->shortages(), [
                C::make('material', 'المادة', C::LINK), C::make('code', 'الكود', C::CODE), C::make('category', 'الفئة'),
                C::make('stock_qty', 'الرصيد', C::QTY), C::make('unit', 'الوحدة'),
                C::make('min_stock_level', 'الحد الأدنى', C::QTY), C::make('reorder_point', 'حد إعادة الطلب', C::QTY),
                C::make('open_requirement', 'احتياج الإنتاج المفتوح', C::QTY),
                C::make('production_shortfall', 'عجز الإنتاج', C::QTY),
                C::make('affected_pos', 'أوامر إنتاج متأثرة', C::INT),
            ], 'النواقص وعجز المواد'],
            default => [$this->inventory->stock($filters), [
                C::make('material', 'المادة'), C::make('code', 'الكود', C::CODE), C::make('category', 'الفئة'),
                C::make('stock_qty', 'الرصيد الحالي', C::QTY), C::make('unit', 'الوحدة'),
                C::make('min_stock_level', 'الحد الأدنى', C::QTY), C::make('reorder_point', 'حد إعادة الطلب', C::QTY),
                C::make('incoming_qty', 'وارد بأوامر شراء مفتوحة', C::QTY)->withHint('غير مشمول في الرصيد الفعلي'),
                C::make('available_plus_incoming', 'الرصيد + الوارد', C::QTY),
                C::make('last_receipt_date', 'آخر استلام', C::DATE), C::make('last_issue_date', 'آخر صرف', C::DATE),
                C::make('state', 'الحالة', C::BADGE),
            ], 'أرصدة المواد الخام'],
        };

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, $title, 'inventory-'.$tab);
        }

        if ($tab === 'fabric') {
            $extra['fabricSummary'] = $dataset->totals['summary'];
            $extra['fabricMaterials'] = Material::whereHas('category', fn ($q) => $q->where('code', 'FABRIC'))->orderBy('name_ar')->get(['id', 'name_ar']);
            $extra['suppliers'] = Supplier::orderBy('name')->get(['id', 'name']);
        }
        if ($tab === 'valuation') {
            $extra['totalValue'] = $this->inventory->totalStockValue();
        }

        return view('management_reports.inventory', [
            'tab' => $tab, 'tabs' => $tabs, 'title' => $title, 'threshold' => $threshold,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'categories' => MaterialCategory::orderBy('name_ar')->get(['id', 'name_ar']),
        ] + $extra);
    }
}
