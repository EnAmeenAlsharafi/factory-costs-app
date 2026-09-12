<?php

namespace App\Http\Controllers;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockBalanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض أرصدة المخزون.');

        $categories = MaterialCategory::where('is_active', true)->orderBy('name_ar')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name_ar')->get();

        $selectedWarehouseId = $request->input('warehouse_id');
        $selectedCategoryId = $request->input('category_id');
        $search = $request->input('search');

        // Base Lots Query for valuation and lots tab
        $lotQuery = InventoryLot::with(['material.baseUnit', 'warehouse', 'supplier']);

        if ($selectedWarehouseId) {
            $lotQuery->where('warehouse_id', $selectedWarehouseId);
        }

        if ($selectedCategoryId) {
            $lotQuery->whereHas('material', fn ($q) => $q->where('material_category_id', $selectedCategoryId));
        }

        if ($search) {
            $lotQuery->where(function ($q) use ($search) {
                $q->where('lot_code', 'like', "%{$search}%")
                    ->orWhereHas('material', fn ($mq) => $mq->where('code', 'like', "%{$search}%")->orWhere('name_ar', 'like', "%{$search}%"));
            });
        }

        $allActiveLots = (clone $lotQuery)->where('remaining_quantity', '>', 0)->get();

        $totalInventoryValue = $allActiveLots->sum(fn ($lot) => $lot->remaining_quantity * $lot->unit_cost);
        $activeLotsCount = $allActiveLots->count();
        $lots = (clone $lotQuery)->orderBy('received_date', 'asc')->paginate(15, ['*'], 'lots_page');

        // Material Ledger Balances
        $matQuery = Material::with(['category', 'baseUnit']);

        if ($selectedCategoryId) {
            $matQuery->where('material_category_id', $selectedCategoryId);
        }

        if ($search) {
            $matQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        $materials = $matQuery->orderBy('name_ar')->paginate(15)->withQueryString();

        $materialsWithStockCount = 0;

        foreach ($materials as $mat) {
            $movQuery = InventoryMovement::where('material_id', $mat->id);
            if ($selectedWarehouseId) {
                $movQuery->where('warehouse_id', $selectedWarehouseId);
            }

            $mat->total_in = (float) (clone $movQuery)->where('direction', 'IN')->sum('quantity');
            $mat->total_out = (float) (clone $movQuery)->where('direction', 'OUT')->sum('quantity');
            $mat->stock_balance = $mat->total_in - $mat->total_out;

            if ($mat->stock_balance > 0) {
                $materialsWithStockCount++;
            }

            $matLots = $allActiveLots->where('material_id', $mat->id);
            $mat->total_lot_value = $matLots->sum(fn ($lot) => $lot->remaining_quantity * $lot->unit_cost);
        }

        return view('inventory.balances.index', compact(
            'materials',
            'lots',
            'categories',
            'warehouses',
            'totalInventoryValue',
            'materialsWithStockCount',
            'activeLotsCount'
        ));
    }

    public function showLot(InventoryLot $lot): View
    {
        abort_if(! request()->user()->can('inventory.view'), 403, 'غير مصرح لك بعرض تفاصيل اللوت.');

        $lot->load(['material.baseUnit', 'warehouse', 'supplier', 'receiptLine.materialReceipt', 'movements.baseUnit']);

        return view('inventory.balances.show_lot', compact('lot'));
    }
}
