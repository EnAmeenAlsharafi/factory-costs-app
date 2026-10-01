<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ManagementReportingService;
use App\Domain\Reports\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsCenterController extends Controller
{
    /**
     * Every report-area permission; holding any of them opens the reports center.
     */
    public const AREA_PERMISSIONS = [
        'reports.view', 'reports.profitability', 'reports.sales', 'reports.production', 'reports.quality',
        'reports.inventory', 'reports.procurement', 'reports.delivery', 'reports.receivables',
    ];

    public function __construct(protected ManagementReportingService $management) {}

    public static function canAccess(User $user): bool
    {
        return collect(self::AREA_PERMISSIONS)->contains(fn (string $permission) => $user->can($permission));
    }

    public function index(Request $request): View
    {
        abort_unless(self::canAccess($request->user()), 403);

        $user = $request->user();
        $groups = collect($this->catalogue())
            ->map(fn (array $group) => array_merge($group, ['reports' => collect($group['reports'])->filter(fn (array $report) => collect($report['any'])->contains(fn ($p) => $user->can($p)))->values()]))
            ->filter(fn (array $group) => $group['reports']->isNotEmpty());

        return view('management_reports.center', compact('groups'));
    }

    public function dashboard(Request $request): View
    {
        abort_unless(self::canAccess($request->user()), 403);

        $period = ReportPeriod::fromRequest($request);
        $sections = $this->management->dashboard($request->user(), $period);
        $today = $this->management->today($request->user());
        $trends = isset($sections['commercial']) ? $this->management->trends($sections['commercial'], $request->user(), $period) : [];
        $exceptionCount = $this->management->exceptions($request->user())->sum('count');

        return view('management_reports.dashboard', compact('period', 'sections', 'today', 'trends', 'exceptionCount'));
    }

    public function exceptions(Request $request): View
    {
        abort_unless(self::canAccess($request->user()), 403);

        $groups = $this->management->exceptions($request->user());

        return view('management_reports.exceptions', compact('groups'));
    }

    /**
     * @return list<array{title: string, icon: string, reports: list<array{title: string, purpose: string, icon: string, route: string, params?: array<string, mixed>, any: list<string>}>}>
     */
    private function catalogue(): array
    {
        return [
            ['title' => 'الإدارة', 'icon' => 'fa-gauge-high', 'reports' => [
                ['title' => 'لوحة الإدارة', 'purpose' => 'المؤشرات الرئيسية للفترة ومقارنتها بالفترة السابقة وملخص اليوم.', 'icon' => 'fa-chart-line', 'route' => 'reports.dashboard', 'any' => self::AREA_PERMISSIONS],
                ['title' => 'الاستثناءات التشغيلية', 'purpose' => 'كل ما يعطل العمل الآن: مراجعات، إيقاف سداد، نقص مواد، تأخر موردين، جودة، توصيل.', 'icon' => 'fa-triangle-exclamation', 'route' => 'reports.exceptions', 'any' => self::AREA_PERMISSIONS],
            ]],
            ['title' => 'التحليل التجاري', 'icon' => 'fa-coins', 'reports' => [
                ['title' => 'ربحية الطلبات التشغيلية', 'purpose' => 'القيمة التجارية مقابل تكلفة المواد الفعلية لكل طلب، مع حالة اكتمال التكلفة.', 'icon' => 'fa-file-invoice-dollar', 'route' => 'reports.profitability.orders', 'any' => ['reports.profitability']],
                ['title' => 'ربحية الموديلات والمقاسات', 'purpose' => 'المبيعات والمساهمة حسب الموديل أو التكوين (المقاس والسحارة)، مع ترتيب الأعلى.', 'icon' => 'fa-bed', 'route' => 'reports.profitability.products', 'any' => ['reports.profitability', 'reports.sales']],
                ['title' => 'تحليل قنوات البيع', 'purpose' => 'الطلبات والقيمة ومتوسط الطلب والمساهمة لكل قناة بيع.', 'icon' => 'fa-store', 'route' => 'reports.profitability.channels', 'any' => ['reports.profitability', 'reports.sales']],
                ['title' => 'تحليل العملاء', 'purpose' => 'قيمة الطلبات والمساهمة وأرصدة العملاء حسب الصلاحية.', 'icon' => 'fa-users', 'route' => 'reports.profitability.customers', 'any' => ['reports.profitability', 'reports.sales']],
            ]],
            ['title' => 'التصنيع', 'icon' => 'fa-industry', 'reports' => [
                ['title' => 'انحراف تكلفة الإنتاج', 'purpose' => 'التكلفة المخططة مقابل الفعلية لكل أمر إنتاج مع التفصيل حسب المادة.', 'icon' => 'fa-scale-unbalanced', 'route' => 'reports.production.variance', 'any' => ['reports.production']],
                ['title' => 'دقة وصفات التصنيع', 'purpose' => 'الكميات المخططة مقابل المستهلكة لكل نسخة وصفة، ومقارنة النسخ.', 'icon' => 'fa-scroll', 'route' => 'reports.production.recipes', 'any' => ['reports.production']],
                ['title' => 'هيكل التكلفة والأقمشة', 'purpose' => 'تكلفة المواد حسب الفئة، تركيبة تكلفة المنتج، وتحليل استهلاك الأقمشة.', 'icon' => 'fa-layer-group', 'route' => 'reports.production.cost-structure', 'any' => ['reports.production']],
                ['title' => 'الهدر وإعادة العمل والجودة', 'purpose' => 'الهدر حسب المادة/الموديل/القسم/السبب، إعادة العمل، وحوادث الجودة.', 'icon' => 'fa-recycle', 'route' => 'reports.production.quality', 'any' => ['reports.production', 'reports.quality']],
                ['title' => 'أداء الأقسام والاختناقات', 'purpose' => 'العمليات المفتوحة والمنجزة، طوابير مراكز العمل، ومدة الإنتاج.', 'icon' => 'fa-diagram-project', 'route' => 'reports.production.departments', 'any' => ['reports.production']],
            ]],
            ['title' => 'المواد والمخزون', 'icon' => 'fa-warehouse', 'reports' => [
                ['title' => 'المخزون والأقمشة والنواقص', 'purpose' => 'الأرصدة والواردة، القيمة التشغيلية، الأقمشة حسب المورد واللون، بطيئة الحركة، والنواقص.', 'icon' => 'fa-boxes-stacked', 'route' => 'reports.inventory', 'any' => ['reports.inventory']],
            ]],
            ['title' => 'المشتريات', 'icon' => 'fa-cart-shopping', 'reports' => [
                ['title' => 'المشتريات والموردون', 'purpose' => 'المشتريات الفعلية مقابل الملتزم بها، أداء الموردين، وانحراف أسعار الشراء.', 'icon' => 'fa-truck-field', 'route' => 'reports.procurement', 'any' => ['reports.procurement']],
            ]],
            ['title' => 'التنفيذ والتوصيل', 'icon' => 'fa-truck-fast', 'reports' => [
                ['title' => 'تنفيذ الطلبات والتوصيل', 'purpose' => 'مسار الطلب من الإنتاج للتسليم، المنتجات الجاهزة غير المسلمة، التوصيل، والمرتجعات.', 'icon' => 'fa-route', 'route' => 'reports.fulfillment', 'any' => ['reports.delivery']],
            ]],
            ['title' => 'الذمم والتحصيل', 'icon' => 'fa-hand-holding-dollar', 'reports' => [
                ['title' => 'الذمم والأعمار والتحصيل', 'purpose' => 'المستحقات والمتأخرات حسب العميل، أعمار الديون، ونشاط التحصيل.', 'icon' => 'fa-file-invoice', 'route' => 'reports.receivables', 'any' => ['reports.receivables']],
            ]],
        ];
    }
}
