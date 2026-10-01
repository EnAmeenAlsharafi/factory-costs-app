<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ProductionAnalyticsService;
use App\Domain\Reports\QualityAnalyticsService;
use App\Domain\Reports\ReportColumn as C;
use App\Domain\Reports\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteReason;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManufacturingReportController extends Controller
{
    use RendersReports;

    public function __construct(
        protected ProductionAnalyticsService $production,
        protected QualityAnalyticsService $quality,
    ) {}

    public function variance(Request $request): View|StreamedResponse
    {
        $this->authorizeCost($request);

        $period = ReportPeriod::fromRequest($request);
        $filters = ['scope' => in_array($request->query('scope'), ['completed', 'active', 'all'], true) ? $request->query('scope') : 'completed']
            + $request->only(['product_model_id', 'sort']);
        $dataset = $this->production->costVariance($period, $filters);
        $columns = [
            C::make('production_order', 'أمر الإنتاج', C::LINK),
            C::make('customer_order', 'طلب العميل', C::CODE),
            C::make('product', 'المنتج'),
            C::make('status', 'الحالة', C::BADGE),
            C::make('released_quantity', 'الكمية المُطلقة', C::INT),
            C::make('completed_quantity', 'المنجزة', C::INT),
            C::make('planned_cost', 'التكلفة المخططة / المرجعية', C::MONEY),
            C::make('actual_cost', 'تكلفة المواد الفعلية', C::MONEY),
            C::make('cost_variance', 'انحراف التكلفة', C::MONEY),
            C::make('variance_pct', 'نسبة الانحراف', C::PERCENT),
            C::make('rework_cost', 'منها إعادة عمل', C::MONEY),
            C::make('waste_cost', 'منها هدر (تحليلي)', C::MONEY),
        ];

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'انحراف تكلفة الإنتاج', 'production-cost-variance', $period);
        }

        return view('management_reports.production.variance', [
            'period' => $period, 'filters' => $filters, 'totals' => $dataset->totals,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'models' => ProductModel::orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function showVariance(Request $request, ProductionOrder $productionOrder): View
    {
        $this->authorizeCost($request);

        $productionOrder->load(['productModel', 'productConfiguration', 'recipeVersion.recipe', 'customerOrder.customer', 'customerOrderLine']);
        $analysis = $this->production->materialVariance($productionOrder);
        $siblings = ProductionOrder::where('customer_order_line_id', $productionOrder->customer_order_line_id)
            ->whereKeyNot($productionOrder->id)->get(['id', 'production_order_number', 'released_quantity', 'status']);
        $wasteRecords = DB::table('production_waste_records as w')
            ->join('materials as m', 'm.id', '=', 'w.material_id')
            ->leftJoin('production_waste_reasons as wr', 'wr.id', '=', 'w.waste_reason_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'w.unit_id')
            ->where('w.production_order_id', $productionOrder->id)
            ->orderByDesc('w.occurred_at')
            ->get(['w.waste_number', 'w.quantity', 'w.total_cost', 'w.occurred_at', 'm.name_ar AS material', 'wr.name_ar AS reason', 'u.name_ar AS unit']);

        return view('management_reports.production.variance_show', [
            'po' => $productionOrder,
            'rows' => $analysis['rows'],
            'summary' => $analysis['summary'],
            'siblings' => $siblings,
            'wasteRecords' => $wasteRecords,
        ]);
    }

    public function recipes(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('reports.production'), 403);

        $period = ReportPeriod::fromRequest($request, 'this_year');
        $dataset = $this->production->recipeAccuracy($period);
        $columns = [
            C::make('recipe', 'الوصفة / النسخة'),
            C::make('material', 'المادة'),
            C::make('code', 'الكود', C::CODE),
            C::make('unit', 'الوحدة'),
            C::make('po_count', 'أوامر إنتاج مكتملة', C::INT),
            C::make('planned_qty', 'الكمية المخططة', C::QTY),
            C::make('consumed_qty', 'الكمية المستهلكة فعلياً', C::QTY),
            C::make('variance_qty', 'انحراف الكمية', C::QTY),
            C::make('variance_pct', 'نسبة الانحراف', C::PERCENT),
        ];

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'دقة وصفات التصنيع', 'recipe-accuracy', $period);
        }

        $recipes = ManufacturingRecipe::with(['versions' => fn ($q) => $q->orderBy('version_number')])->orderBy('name')->get();
        $comparison = null;
        $recipe = $recipes->firstWhere('id', (int) $request->query('recipe_id'));
        if ($recipe && $request->filled(['version_a', 'version_b'])) {
            $versionIds = $recipe->versions->pluck('id');
            $a = (int) $request->query('version_a');
            $b = (int) $request->query('version_b');
            if ($versionIds->contains($a) && $versionIds->contains($b)) {
                $comparison = [
                    'recipe' => $recipe,
                    'a' => $recipe->versions->firstWhere('id', $a),
                    'b' => $recipe->versions->firstWhere('id', $b),
                    'rows' => $this->production->compareRecipeVersions($a, $b),
                ];
            }
        }

        return view('management_reports.production.recipes', [
            'period' => $period,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'recipes' => $recipes,
            'comparison' => $comparison,
            'showCost' => $request->user()->can('costing.view'),
        ]);
    }

    public function costStructure(Request $request): View
    {
        $this->authorizeCost($request);

        $period = ReportPeriod::fromRequest($request, 'this_year');

        return view('management_reports.production.cost_structure', [
            'period' => $period,
            'byCategory' => $this->production->costByCategory($period),
            'breakdown' => $this->production->productCostBreakdown($period),
            'fabrics' => $this->production->fabricCost($period),
        ]);
    }

    public function quality(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('reports.production') || $user->can('reports.quality'), 403);

        $period = ReportPeriod::fromRequest($request);
        $tab = in_array($request->query('tab'), ['waste', 'rework', 'quality'], true) ? $request->query('tab') : 'waste';
        $filters = $request->only(['material_id', 'waste_reason_id', 'department_id', 'material_category_id', 'product_model_id', 'status']);
        $groupBy = array_key_exists((string) $request->query('group'), QualityAnalyticsService::WASTE_GROUPS) ? (string) $request->query('group') : 'material';
        $data = ['period' => $period, 'tab' => $tab, 'groupBy' => $groupBy];

        if ($tab === 'waste') {
            $dataset = $this->quality->wasteRecords($period, $filters);
            $columns = [
                C::make('waste_number', 'رقم الهدر', C::CODE),
                C::make('date', 'التاريخ', C::DATE),
                C::make('material', 'المادة'),
                C::make('category', 'الفئة'),
                C::make('quantity', 'الكمية', C::QTY),
                C::make('unit', 'الوحدة'),
                C::make('waste_cost', 'تكلفة الهدر (تحليلية)', C::MONEY)->requires(['costing.view']),
                C::make('production_order', 'أمر الإنتاج', C::LINK),
                C::make('model', 'الموديل'),
                C::make('detected_department', 'قسم الاكتشاف'),
                C::make('responsible_department', 'القسم المسؤول'),
                C::make('reason', 'السبب'),
            ];
            if ($this->wantsExport($request)) {
                return $this->exportCsv($request, $dataset, $columns, 'تحليل الهدر', 'waste-analysis', $period);
            }
            $data += [
                'table' => $this->paginatedTable($request, $dataset, $columns),
                'grouped' => $this->quality->wasteGrouped($period, $groupBy, $filters),
                'reasons' => ProductionWasteReason::orderBy('name_ar')->get(['id', 'name_ar']),
                'departments' => Department::orderBy('sort_order')->get(['id', 'name_ar']),
                'categories' => MaterialCategory::orderBy('name_ar')->get(['id', 'name_ar']),
                'models' => ProductModel::orderBy('name_ar')->get(['id', 'name_ar']),
            ];
        } elseif ($tab === 'rework') {
            $dataset = $this->quality->rework($period, $filters);
            $columns = [
                C::make('rework_number', 'رقم إعادة العمل', C::CODE),
                C::make('production_order', 'أمر الإنتاج', C::LINK),
                C::make('model', 'الموديل'),
                C::make('quantity', 'الكمية المتأثرة', C::INT),
                C::make('reason', 'سبب إعادة العمل'),
                C::make('incident', 'حادثة الجودة', C::CODE),
                C::make('detected_department', 'قسم الاكتشاف'),
                C::make('responsible_department', 'القسم المسؤول'),
                C::make('assigned_department', 'القسم المنفذ'),
                C::make('rework_material_cost', 'مواد إعادة العمل لأمر الإنتاج', C::MONEY)->requires(['costing.view']),
                C::make('status', 'الحالة', C::BADGE),
            ];
            if ($this->wantsExport($request)) {
                return $this->exportCsv($request, $dataset, $columns, 'تحليل إعادة العمل', 'rework-analysis', $period);
            }
            $data += ['table' => $this->paginatedTable($request, $dataset, $columns), 'totals' => $dataset->totals];
        } else {
            $data += ['summary' => $this->quality->qualitySummary($period)];
        }

        $data['showCost'] = $user->can('costing.view');

        return view('management_reports.production.quality', $data);
    }

    public function departments(Request $request): View
    {
        abort_unless($request->user()->can('reports.production'), 403);

        $period = ReportPeriod::fromRequest($request);

        return view('management_reports.production.departments', [
            'period' => $period,
            'departments' => $this->production->departments($period),
            'bottlenecks' => $this->production->bottlenecks(),
            'leadTimes' => $this->production->leadTimes($period),
        ]);
    }

    private function authorizeCost(Request $request): void
    {
        abort_unless($request->user()->can('reports.production') && $request->user()->can('costing.view'), 403, 'غير مصرح لك بعرض تقارير تكلفة الإنتاج.');
    }
}
