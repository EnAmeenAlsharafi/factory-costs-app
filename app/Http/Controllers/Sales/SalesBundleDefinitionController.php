<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesBundleDefinitionRequest;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\SalesBundleDefinition;
use App\Services\Sales\SallaValueNormalizer;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesBundleDefinitionController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('sales_imports.view'), 403, 'غير مصرح لك بعرض بكجات المبيعات.');

        $bundles = SalesBundleDefinition::with('components')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.bundles.index', compact('bundles'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك بإنشاء بكج مبيعات.');

        $materials = Material::where('is_active', true)->sellableReady()->orderBy('name_ar')->get();
        $configurations = ProductConfiguration::with('productModel')->where('is_active', true)->get();

        return view('sales.bundles.create', compact('materials', 'configurations'));
    }

    public function store(SalesBundleDefinitionRequest $request): RedirectResponse
    {
        try {
            $bundle = SalesBundleDefinition::create([
                'name' => $request->validated('name'),
                'sales_sku' => $request->validated('sales_sku'),
                'match_name_normalized' => SallaValueNormalizer::normalizeMatchValue($request->validated('name')),
                'is_active' => $request->boolean('is_active', true),
            ]);

            foreach ($request->validated('components') as $component) {
                $bundle->components()->create([
                    'component_type' => $component['component_type'],
                    'product_model_id' => $component['component_type'] === 'MANUFACTURED' ? $component['product_model_id'] : null,
                    'product_configuration_id' => $component['component_type'] === 'MANUFACTURED' ? $component['product_configuration_id'] : null,
                    'material_id' => $component['component_type'] === 'READY_STOCK' ? $component['material_id'] : null,
                    'quantity' => $component['quantity'],
                ]);
            }

            return redirect()->route('sales.bundles.index')->with('success', 'تم إنشاء البكج بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request, SalesBundleDefinition $bundle): View
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك بتعديل بكج المبيعات.');

        $bundle->load('components.productModel', 'components.productConfiguration', 'components.material');
        $materials = Material::where('is_active', true)->sellableReady()->orderBy('name_ar')->get();
        $configurations = ProductConfiguration::with('productModel')->where('is_active', true)->get();

        $initialComponents = $bundle->components->map(function ($c) {
            return [
                'component_type' => $c->component_type,
                'product_model_id' => $c->product_model_id ?? '',
                'product_configuration_id' => $c->product_configuration_id ?? '',
                'material_id' => $c->material_id ?? '',
                'quantity' => (float) $c->quantity,
            ];
        })->values()->toArray();

        return view('sales.bundles.edit', compact('bundle', 'materials', 'configurations', 'initialComponents'));
    }

    public function update(SalesBundleDefinitionRequest $request, SalesBundleDefinition $bundle): RedirectResponse
    {
        try {
            $bundle->update([
                'name' => $request->validated('name'),
                'sales_sku' => $request->validated('sales_sku'),
                'match_name_normalized' => SallaValueNormalizer::normalizeMatchValue($request->validated('name')),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $bundle->components()->delete();

            foreach ($request->validated('components') as $component) {
                $bundle->components()->create([
                    'component_type' => $component['component_type'],
                    'product_model_id' => $component['component_type'] === 'MANUFACTURED' ? $component['product_model_id'] : null,
                    'product_configuration_id' => $component['component_type'] === 'MANUFACTURED' ? $component['product_configuration_id'] : null,
                    'material_id' => $component['component_type'] === 'READY_STOCK' ? $component['material_id'] : null,
                    'quantity' => $component['quantity'],
                ]);
            }

            return redirect()->route('sales.bundles.index')->with('success', 'تم تحديث البكج بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, SalesBundleDefinition $bundle): RedirectResponse
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك بتغيير حالة البكج.');

        $bundle->update(['is_active' => ! $bundle->is_active]);

        return back()->with('success', 'تم تحديث حالة البكج بنجاح.');
    }
}
