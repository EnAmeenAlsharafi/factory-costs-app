<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesItemMappingRequest;
use App\Models\Material;
use App\Models\ProductModel;
use App\Models\SalesBundleDefinition;
use App\Models\SalesChannel;
use App\Models\SalesItemMapping;
use App\Services\Sales\SallaValueNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesItemMappingController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('sales_imports.view'), 403, 'غير مصرح لك بعرض مطابقة طلبات سلة.');

        $mappings = SalesItemMapping::with(['material', 'bundleDefinition', 'productModel', 'salesChannel'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $channels = SalesChannel::active()->orderBy('name_ar')->get();
        $materials = Material::where('is_active', true)->sellableReady()->orderBy('name_ar')->get();
        $bundles = SalesBundleDefinition::active()->orderBy('name')->get();
        $models = ProductModel::where('is_active', true)->orderBy('name_ar')->get();

        return view('sales.item-mappings.index', compact('mappings', 'channels', 'materials', 'bundles', 'models'));
    }

    public function store(SalesItemMappingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['match_value'] = SallaValueNormalizer::normalizeMatchValue($validated['match_value']);
        $validated['created_by_user_id'] = $request->user()->id;

        SalesItemMapping::create($validated);

        return redirect()->route('sales.item-mappings.index')->with('success', 'تم إضافة المطابقة بنجاح.');
    }

    public function update(SalesItemMappingRequest $request, SalesItemMapping $item_mapping): RedirectResponse
    {
        $validated = $request->validated();
        $validated['match_value'] = SallaValueNormalizer::normalizeMatchValue($validated['match_value']);

        $item_mapping->update($validated);

        return redirect()->route('sales.item-mappings.index')->with('success', 'تم تحديث المطابقة بنجاح.');
    }

    public function toggleStatus(Request $request, SalesItemMapping $item_mapping): RedirectResponse
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك بتغيير حالة المطابقة.');

        $item_mapping->update(['is_active' => ! $item_mapping->is_active]);

        return back()->with('success', 'تم تحديث حالة المطابقة بنجاح.');
    }
}
