<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ProcurementAnalyticsService;
use App\Domain\Reports\ReportColumn as C;
use App\Domain\Reports\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use App\Models\Material;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementReportController extends Controller
{
    use RendersReports;

    public function __construct(protected ProcurementAnalyticsService $procurement) {}

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('reports.procurement'), 403);

        $period = ReportPeriod::fromRequest($request);
        $dataset = $this->procurement->priceVariance($period);
        $columns = [
            C::make('receipt', 'سند الاستلام', C::CODE),
            C::make('receipt_date', 'تاريخ الاستلام', C::DATE),
            C::make('purchase_order', 'أمر الشراء', C::CODE),
            C::make('supplier', 'المورد'),
            C::make('material', 'المادة', C::LINK),
            C::make('base_quantity', 'الكمية (أساسية)', C::QTY),
            C::make('unit', 'الوحدة الأساسية'),
            C::make('po_price_per_base', 'السعر المتفق / وحدة أساسية', C::MONEY),
            C::make('actual_price_per_base', 'السعر الفعلي / وحدة أساسية', C::MONEY),
            C::make('variance_per_base_unit', 'الانحراف / وحدة', C::MONEY),
            C::make('variance_total', 'إجمالي الانحراف', C::MONEY),
            C::make('direction', 'الاتجاه', C::BADGE),
        ];

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'انحراف أسعار الشراء', 'purchase-price-variance', $period);
        }

        return view('management_reports.procurement', [
            'period' => $period,
            'summary' => $this->procurement->summary($period),
            'suppliers' => $this->procurement->suppliers($period),
            'table' => $this->paginatedTable($request, $dataset, $columns),
        ]);
    }

    public function priceHistory(Request $request): View
    {
        abort_unless($request->user()->can('reports.procurement'), 403);

        $materials = Material::orderBy('name_ar')->get(['id', 'name_ar', 'code']);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $materialId = (int) $request->query('material_id');
        $supplierId = $request->filled('supplier_id') ? (int) $request->query('supplier_id') : null;
        $history = $materialId ? $this->procurement->priceHistory($materialId, $supplierId) : collect();

        return view('management_reports.procurement_price_history', compact('materials', 'suppliers', 'materialId', 'supplierId', 'history'));
    }
}
