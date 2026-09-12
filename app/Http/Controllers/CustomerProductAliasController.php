<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerProductAliasRequest;
use App\Models\CustomerProductAlias;
use App\Services\ProductService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerProductAliasController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function store(CustomerProductAliasRequest $request): RedirectResponse
    {
        try {
            $alias = $this->productService->createAlias($request->validated());

            return redirect()->route('products.models.show', $alias->product_model_id)
                ->with('success', 'تم ربط مسمى العميل بالموديل بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(CustomerProductAliasRequest $request, CustomerProductAlias $alias): RedirectResponse
    {
        $validated = $request->validated();
        $alias->update($validated);

        if (! empty($validated['is_default'])) {
            $this->productService->setDefaultAlias($alias);
        }

        return redirect()->route('products.models.show', $alias->product_model_id)
            ->with('success', 'تم تحديث مسمى العميل بنجاح.');
    }

    public function setDefault(Request $request, CustomerProductAlias $alias): RedirectResponse
    {
        abort_if(! $request->user()->can('products.manage') && ! $request->user()->can('products.view'), 403, 'غير مصرح لك بضبط المسمى الافتراضي.');

        $this->productService->setDefaultAlias($alias);

        return back()->with('success', 'تم تعيين المسمى كافتراضي للعميل بنجاح.');
    }

    public function toggleStatus(Request $request, CustomerProductAlias $alias): RedirectResponse
    {
        abort_if(! $request->user()->can('products.manage'), 403, 'غير مصرح لك بتغيير حالة المسمى.');

        $alias->update(['is_active' => ! $alias->is_active]);

        return back()->with('success', 'تم تحديث حالة المسمى بنجاح.');
    }
}
