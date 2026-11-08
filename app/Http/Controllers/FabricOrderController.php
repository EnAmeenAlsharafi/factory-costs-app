<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\FabricOrderService;
use App\Services\PdfDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fabric ordering from suppliers: pick the beds, the meters of the same catalog and colour are summed
 * into one draft purchase order per supplier, then printed as a supplier sheet.
 */
class FabricOrderController extends Controller
{
    public function __construct(protected FabricOrderService $fabricOrders) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);

        $candidates = $this->fabricOrders->candidates();
        $rows = $candidates->map(fn (ProductionOrder $order) => [
            'order' => $order,
            'orderable' => $this->fabricOrders->isOrderable($order),
            'planned' => $this->fabricOrders->plannedMeters($order),
        ]);

        $recentOrders = PurchaseOrder::query()
            ->whereHas('lines.fabricAllocations')
            ->with('supplier')
            ->withSum('lines', 'ordered_quantity')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('purchasing.fabric_orders.index', compact('rows', 'recentOrders'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'extra_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'اختر سريراً واحداً على الأقل.',
            'extra_percent.max' => 'نسبة الاحتياط يجب ألا تتجاوز 50%.',
        ]);

        $metersByOrder = collect($data['items'])
            ->filter(fn (array $item) => ! empty($item['selected']))
            ->map(fn (array $item) => (float) ($item['meters'] ?? 0))
            ->all();

        if ($metersByOrder === []) {
            return back()->withInput()->withErrors(['items' => 'اختر سريراً واحداً على الأقل.']);
        }

        $purchaseOrders = $this->fabricOrders->createOrders(
            $metersByOrder,
            $this->fabricStore(),
            $request->user(),
            (float) ($data['extra_percent'] ?? 0),
            $data['notes'] ?? null,
        );

        return redirect()->route('purchasing.fabric-orders.index')
            ->with('success', 'تم إنشاء '.$purchaseOrders->count().' طلب قماش (أمر شراء مسودة لكل مورد). اطبع كل طلب وأرسله للمورد.')
            ->with('created_purchase_orders', $purchaseOrders->pluck('id')->all());
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        return view('purchasing.fabric_orders.print', $this->documentData($request, $purchaseOrder));
    }

    public function pdf(Request $request, PurchaseOrder $purchaseOrder, PdfDocumentService $pdf): Response
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $data = $this->documentData($request, $purchaseOrder);

        return $pdf->download(
            'purchasing.fabric_orders.document',
            $data,
            'fabric-order-'.$purchaseOrder->purchase_order_number.'.pdf',
            'طلب توريد قماش '.$purchaseOrder->purchase_order_number,
        );
    }

    /**
     * Fabric is delivered to the factory and kept as a raw material, so it is always received into the raw materials store.
     */
    private function fabricStore(): Warehouse
    {
        return Warehouse::where('code', 'RAW_MATERIALS')->first()
            ?? Warehouse::active()->orderBy('id')->firstOrFail();
    }

    /**
     * @return array{purchaseOrder: PurchaseOrder, company: CompanySetting, showBreakdown: bool}
     */
    private function documentData(Request $request, PurchaseOrder $purchaseOrder): array
    {
        abort_unless($purchaseOrder->lines()->whereHas('fabricAllocations')->exists(), 404);

        $purchaseOrder->load([
            'supplier',
            'warehouse',
            'createdByUser',
            'lines.material.fabricSpec',
            'lines.material.baseUnit',
            'lines.fabricColor',
            'lines.fabricAllocations.productionOrder' => fn ($q) => $q->with(['customerOrder.customer', 'productModel']),
        ]);

        return [
            'purchaseOrder' => $purchaseOrder,
            'company' => CompanySetting::current(),
            'showBreakdown' => $request->boolean('details', true),
        ];
    }
}
