<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductConfigurationRequest;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Services\ProductService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductConfigurationController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function store(ProductConfigurationRequest $request): RedirectResponse
    {
        $model = ProductModel::findOrFail($request->validated()['product_model_id']);

        try {
            $this->productService->createConfiguration($model, $request->validated());

            return redirect()->route('products.models.show', $model)
                ->with('success', 'تم إضافة تكوين تصنيعي جديد للموديل بنجاح.');
        } catch (Exception $e) {
            return redirect()->route('products.models.show', $model)
                ->with('error', $e->getMessage());
        }
    }

    public function update(ProductConfigurationRequest $request, ProductConfiguration $configuration): RedirectResponse
    {
        $configuration->update($request->validated());

        return redirect()->route('products.models.show', $configuration->product_model_id)
            ->with('success', 'تم تحديث التكوين التصنيعي بنجاح.');
    }

    public function toggleStatus(Request $request, ProductConfiguration $configuration): RedirectResponse
    {
        abort_if(! $request->user()->can('products.manage'), 403, 'غير مصرح لك بتغيير حالة التكوين.');

        $configuration->update(['is_active' => ! $configuration->is_active]);

        return back()->with('success', 'تم تحديث حالة التكوين بنجاح.');
    }
}
