<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductModelRequest;
use App\Models\Customer;
use App\Models\ProductModel;
use App\Models\StandardBedSize;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductModelController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('products.view'), 403, 'غير مصرح لك بعرض موديلات المنتجات.');

        $query = ProductModel::withCount(['configurations', 'aliases']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model_code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $models = $query->latest('id')->paginate(15)->withQueryString();

        return view('products.models.index', compact('models'));
    }

    public function create(): View
    {
        abort_if(! request()->user()->can('products.manage'), 403, 'غير مصرح لك بإضافة موديل منتج جديد.');

        return view('products.models.create');
    }

    public function store(ProductModelRequest $request): RedirectResponse
    {
        $model = $this->productService->createModel(
            $request->validated(),
            $request->file('reference_image')
        );

        return redirect()->route('products.models.show', $model)
            ->with('success', 'تم إنشاء موديل المصنع بنجاح.');
    }

    public function show(Request $request, ProductModel $model): View
    {
        abort_if(! $request->user()->can('products.view'), 403, 'غير مصرح لك بعرض تفاصيل الموديل.');

        $model->load([
            'configurations.standardBedSize',
            'aliases.customer',
        ]);

        $standardSizes = StandardBedSize::where('is_active', true)->orderBy('sort_order')->get();
        $customers = Customer::where('is_active', true)->orderBy('name_ar')->get();

        return view('products.models.show', compact('model', 'standardSizes', 'customers'));
    }

    public function edit(ProductModel $model): View
    {
        abort_if(! request()->user()->can('products.manage'), 403, 'غير مصرح لك بتعديل الموديل.');

        return view('products.models.edit', compact('model'));
    }

    public function update(ProductModelRequest $request, ProductModel $model): RedirectResponse
    {
        $this->productService->updateModel(
            $model,
            $request->validated(),
            $request->file('reference_image')
        );

        return redirect()->route('products.models.show', $model)
            ->with('success', 'تم تحديث بيانات الموديل بنجاح.');
    }

    public function toggleStatus(Request $request, ProductModel $model): RedirectResponse
    {
        abort_if(! $request->user()->can('products.manage'), 403, 'غير مصرح لك بتغيير حالة الموديل.');

        $model->update(['is_active' => ! $model->is_active]);

        $statusText = $model->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusText} الموديل بنجاح.");
    }
}
