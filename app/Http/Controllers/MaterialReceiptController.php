<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialReceiptRequest;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialReceiptController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض إيصالات الاستلام.');

        $query = MaterialReceipt::with(['supplier', 'warehouse', 'createdByUser', 'receivedByUser']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('supplier_reference', 'like', "%{$search}%")
                    ->orWhere('purchase_invoice_reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $receipts = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('inventory.receipts.index', compact('receipts', 'suppliers'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.receive'), 403, 'غير مصرح لك بإضافة إيصال استلام مواد.');

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name_ar')->get();
        $materials = Material::with(['category', 'baseUnit', 'purchaseUnit', 'fabricColors'])->where('is_active', true)->orderBy('name_ar')->get();
        $units = UnitOfMeasure::where('is_active', true)->orderBy('name_ar')->get();
        $nextReceiptNumber = DocumentNumberService::generateReceiptNumber();

        return view('inventory.receipts.create', compact('suppliers', 'warehouses', 'materials', 'units', 'nextReceiptNumber'));
    }

    public function store(MaterialReceiptRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $receipt = DB::transaction(function () use ($request, $validated) {
            $receipt = MaterialReceipt::create([
                'receipt_number' => DocumentNumberService::generateReceiptNumber(),
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'receipt_date' => $validated['receipt_date'],
                'supplier_reference' => $validated['supplier_reference'] ?? null,
                'purchase_invoice_reference' => $validated['purchase_invoice_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'DRAFT',
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $lineData) {
                $material = Material::findOrFail($lineData['material_id']);
                $conversionFactor = (float) ($lineData['conversion_factor'] ?? 1);
                $qtyReceived = (float) $lineData['quantity_received'];
                $baseQty = $qtyReceived * $conversionFactor;

                $unitCostPurchase = (float) ($lineData['unit_cost_purchase'] ?? 0);
                $totalCost = round($qtyReceived * $unitCostPurchase, 4);
                $unitCostBase = $baseQty > 0 ? round($totalCost / $baseQty, 6) : 0;

                MaterialReceiptLine::create([
                    'material_receipt_id' => $receipt->id,
                    'material_id' => $material->id,
                    'fabric_color_id' => $lineData['fabric_color_id'] ?? null,
                    'quantity_received' => $qtyReceived,
                    'purchase_unit_id' => $lineData['purchase_unit_id'],
                    'conversion_factor' => $conversionFactor,
                    'base_quantity' => $baseQty,
                    'base_unit_id' => $material->base_unit_id,
                    'unit_cost_purchase' => $unitCostPurchase,
                    'total_cost' => $totalCost,
                    'unit_cost_base' => $unitCostBase,
                    'supplier_material_code' => $lineData['supplier_material_code'] ?? null,
                    'quality_note' => $lineData['quality_note'] ?? null,
                    'lot_reference' => $lineData['lot_reference'] ?? null,
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            return $receipt;
        });

        if ($request->has('post_immediately') || $request->input('action') === 'post') {
            try {
                $this->inventoryService->postReceipt($receipt, $request->user());

                return redirect()->route('inventory.receipts.show', $receipt)
                    ->with('success', 'تم تسجيل وإعتماد إيصال الاستلام وإنشاء اللوتات والمخزون بنجاح.');
            } catch (Exception $e) {
                return redirect()->route('inventory.receipts.show', $receipt)
                    ->with('error', 'تم حفظ الإيصال كمسودة فقط ولكن تعذر الاعتماد: '.$e->getMessage());
            }
        }

        return redirect()->route('inventory.receipts.show', $receipt)
            ->with('success', 'تم حفظ إيصال الاستلام كمسودة بنجاح.');
    }

    public function show(Request $request, MaterialReceipt $receipt): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض الإيصال.');

        $receipt->load(['supplier', 'warehouse', 'createdByUser', 'receivedByUser', 'lines.material.category', 'lines.fabricColor', 'lines.purchaseUnit', 'lines.baseUnit', 'lines.lot']);

        return view('inventory.receipts.show', compact('receipt'));
    }

    public function post(Request $request, MaterialReceipt $receipt): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.receive'), 403, 'غير مصرح لك باعتتماد إيصال استلام.');

        try {
            $this->inventoryService->postReceipt($receipt, $request->user());

            return back()->with('success', 'تم اعتماد إيصال الاستلام وتحديث حركة وحركات المخزون واللوتات بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
