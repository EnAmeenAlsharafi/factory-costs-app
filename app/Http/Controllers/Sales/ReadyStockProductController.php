<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadyStockProductController extends Controller
{
    public function index(Request $request, InventoryService $inventoryService): View
    {
        abort_if(! $request->user()->can('materials.view'), 403, 'غير مصرح لك بعرض المنتجات الجاهزة للبيع.');

        $query = Material::with('category')->sellableReady();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sales_sku', 'like', "%{$search}%")
                    ->orWhere('sales_display_name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $materials = $query->orderBy('name_ar')->paginate(20)->withQueryString();

        foreach ($materials as $material) {
            $material->availability = $inventoryService->getReadyProductAvailability($material);
        }

        return view('sales.ready-stock-products.index', compact('materials'));
    }
}
