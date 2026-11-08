<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\ReadyProductSupplySetting;
use App\Models\Supplier;
use App\Models\SupplierProductionRequest;
use App\Models\SupplierStockMovement;
use App\Services\PdfDocumentService;
use App\Services\ReadyProductSupplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mattresses made by suppliers: stock (ours + held at the supplier), supply orders built from
 * customer orders, and production requests when a size falls to its reorder level.
 */
class MattressSupplyController extends Controller
{
    public function __construct(protected ReadyProductSupplyService $supply) {}

    public function stock(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $rows = $this->supply->overview();
        $trackedIds = $rows->pluck('setting.material_id');
        $untracked = Material::query()
            ->where('is_sellable_ready_product', true)
            ->where('has_ready_options', false)
            ->where('is_active', true)
            ->whereNotIn('id', $trackedIds)
            ->with('readyParent')
            ->orderBy('name_ar')
            ->get()
            ->sortByDesc(fn (Material $material) => str_contains(($material->readyParent?->name_ar ?? $material->name_ar), 'مرتب'))
            ->values();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $recentMovements = SupplierStockMovement::with(['material.readyParent', 'supplier', 'createdByUser'])->latest('id')->limit(12)->get();

        return view('purchasing.mattresses.stock', compact('rows', 'untracked', 'suppliers', 'recentMovements'));
    }

    public function track(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        $data = $request->validate([
            'material_ids' => ['required', 'array', 'min:1'],
            'material_ids.*' => ['integer', Rule::exists('materials', 'id')->where('is_sellable_ready_product', true)],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ], ['material_ids.required' => 'اختر مقاساً واحداً على الأقل.']);

        foreach ($data['material_ids'] as $materialId) {
            ReadyProductSupplySetting::firstOrCreate(['material_id' => $materialId], [
                'supplier_id' => $data['supplier_id'] ?? null,
                'reorder_level' => 0,
                'production_batch_size' => 1,
            ]);
        }

        return back()->with('success', 'أُضيفت المقاسات للمتابعة. حدّد لكل مقاس مورده وحدّه الأدنى وأقل كمية إنتاج.');
    }

    public function updateSetting(Request $request, ReadyProductSupplySetting $setting): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'production_batch_size' => ['required', 'integer', 'min:1', 'max:100000'],
            'supplier_balance' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'balance_note' => ['nullable', 'string', 'max:300'],
        ], [
            'reorder_level.required' => 'أدخل الحد الأدنى.',
            'production_batch_size.min' => 'أقل كمية إنتاج يجب أن تكون 1 على الأقل.',
        ]);

        $setting->update(collect($data)->only(['supplier_id', 'reorder_level', 'production_batch_size'])->all());

        if (isset($data['supplier_balance']) && $data['supplier_balance'] !== null) {
            $this->supply->setSupplierBalance($setting->fresh(), (float) $data['supplier_balance'], $request->user(), $data['balance_note'] ?? 'تحديث رصيد المورد');
        }

        return back()->with('success', 'تم حفظ إعدادات المقاس.');
    }

    public function orders(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);

        $demand = $this->supply->demand();
        $overview = $this->supply->overview()->keyBy('setting.material_id');
        $untrackedSupplierCount = ReadyProductSupplySetting::whereNull('supplier_id')->count();
        $recentOrders = PurchaseOrder::query()
            ->whereHas('lines.readyProductAllocations')
            ->with('supplier')
            ->withSum('lines', 'ordered_quantity')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('purchasing.mattresses.orders', compact('demand', 'overview', 'untrackedSupplierCount', 'recentOrders'));
    }

    public function storeOrders(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantities = collect($data['items'])
            ->filter(fn (array $item) => ! empty($item['selected']))
            ->map(fn (array $item) => (float) ($item['quantity'] ?? 0))
            ->all();
        if ($quantities === []) {
            return back()->withErrors(['items' => 'اختر طلباً واحداً على الأقل.']);
        }

        $orders = $this->supply->createSupplyOrders($quantities, $request->user(), $data['notes'] ?? null);

        return redirect()->route('purchasing.mattresses.orders')
            ->with('success', 'تم إنشاء '.$orders->count().' طلب توريد مراتب (أمر شراء مسودة لكل مورد).')
            ->with('created_purchase_orders', $orders->pluck('id')->all());
    }

    public function production(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);

        $rows = $this->supply->overview()->filter(fn (array $row) => $row['setting']->supplier_id)->values();
        $requests = SupplierProductionRequest::with(['supplier', 'lines.material.readyParent'])->latest('id')->limit(20)->get();

        return view('purchasing.mattresses.production', compact('rows', 'requests'));
    }

    public function storeProduction(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantities = collect($data['items'])
            ->filter(fn (array $item) => ! empty($item['selected']) && (int) ($item['quantity'] ?? 0) > 0)
            ->map(fn (array $item) => (int) $item['quantity'])
            ->all();
        if ($quantities === []) {
            return back()->withErrors(['items' => 'اختر مقاساً واحداً على الأقل بكمية أكبر من صفر.']);
        }

        $productionRequest = $this->supply->createProductionRequest(Supplier::findOrFail($data['supplier_id']), $quantities, $request->user(), $data['notes'] ?? null);

        return redirect()->route('purchasing.mattresses.production')
            ->with('success', "تم إنشاء طلب الإنتاج {$productionRequest->request_number}. نزّل ملف PDF وأرسله للمورد.")
            ->with('created_production_request', $productionRequest->id);
    }

    public function confirmProduction(Request $request, SupplierProductionRequest $productionRequest): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        $data = $request->validate([
            'produced' => ['array'],
            'produced.*' => ['integer', 'min:0', 'max:100000'],
        ]);

        $this->supply->confirmProduction($productionRequest->load('lines'), $data['produced'] ?? [], $request->user());

        return back()->with('success', "سُجّل إنتاج الطلب {$productionRequest->request_number}، وأُضيفت الكمية لرصيد المورد.");
    }

    public function cancelProduction(Request $request, SupplierProductionRequest $productionRequest): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.create_po'), 403);
        abort_unless($productionRequest->status === 'REQUESTED', 422);
        $productionRequest->update(['status' => 'CANCELLED']);

        return back()->with('success', "أُلغي طلب الإنتاج {$productionRequest->request_number}.");
    }

    public function productionPdf(Request $request, SupplierProductionRequest $productionRequest, PdfDocumentService $pdf): Response
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);
        $productionRequest->load(['supplier', 'createdByUser', 'lines.material.readyParent']);

        return $pdf->download('purchasing.mattresses.production-document', [
            'productionRequest' => $productionRequest,
            'company' => CompanySetting::current(),
        ], 'mattress-production-'.$productionRequest->request_number.'.pdf', 'طلب إنتاج مراتب '.$productionRequest->request_number);
    }

    public function supplyPdf(Request $request, PurchaseOrder $purchaseOrder, PdfDocumentService $pdf): Response
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);
        abort_unless($purchaseOrder->lines()->whereHas('readyProductAllocations')->exists(), 404);
        $purchaseOrder->load(['supplier', 'createdByUser', 'lines.material.readyParent', 'lines.readyProductAllocations.customerOrderLine.customerOrder.customer']);

        return $pdf->download('purchasing.mattresses.supply-document', [
            'purchaseOrder' => $purchaseOrder,
            'company' => CompanySetting::current(),
            'showBreakdown' => $request->boolean('details', true),
        ], 'mattress-supply-'.$purchaseOrder->purchase_order_number.'.pdf', 'طلب توريد مراتب '.$purchaseOrder->purchase_order_number);
    }
}
