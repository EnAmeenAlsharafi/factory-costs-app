<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionPointCategoryRequest;
use App\Models\Department;
use App\Models\ProductionPointCategory;
use App\Models\ProductModel;
use App\Services\WorkerPointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Settings for worker points: the bed categories and their points, which departments award
 * points, and crediting work recorded before its bed was classified.
 */
class ProductionPointCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);

        $categories = ProductionPointCategory::ordered()
            ->withCount('productModels')
            ->with(['productModels' => fn ($query) => $query->orderBy('name_ar')->select(['id', 'name_ar', 'model_code', 'point_category_id', 'is_active', 'reference_image_path'])])
            ->get();
        $departments = Department::where('is_production_department', true)->orderBy('id')->get();
        $unclassifiedModels = ProductModel::where('is_active', true)->whereNull('point_category_id')->orderBy('name_ar')->get(['id', 'name_ar', 'model_code', 'is_active']);

        return view('production.points.index', compact('categories', 'departments', 'unclassifiedModels'));
    }

    public function store(ProductionPointCategoryRequest $request): RedirectResponse
    {
        ProductionPointCategory::create($request->validated() + ['is_active' => true]);

        return back()->with('success', 'تمت إضافة التصنيف.');
    }

    public function update(ProductionPointCategoryRequest $request, ProductionPointCategory $category): RedirectResponse
    {
        $category->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', "تم تحديث «{$category->name_ar}». النقاط الجديدة تُحتسب على الأعمال المسجلة من الآن، ولا تغيّر ما سُجّل سابقاً.");
    }

    public function updateDepartments(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);
        $validated = $request->validate(['departments' => ['nullable', 'array'], 'departments.*' => ['integer', 'exists:departments,id']]);
        $selected = $validated['departments'] ?? [];

        Department::where('is_production_department', true)->get()
            ->each(fn (Department $department) => $department->update(['awards_points' => in_array($department->id, $selected)]));

        return back()->with('success', 'تم تحديث الأقسام التي تُحتسب فيها النقاط.');
    }

    /**
     * Assign categories to many models at once.
     */
    public function classifyModels(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);
        $validated = $request->validate([
            'models' => ['required', 'array'],
            'models.*' => ['nullable', 'exists:production_point_categories,id'],
        ]);
        $classified = 0;
        foreach (array_filter($validated['models']) as $modelId => $categoryId) {
            $classified += ProductModel::whereKey((int) $modelId)->update(['point_category_id' => $categoryId]);
        }

        return back()->with('success', "تم تصنيف {$classified} موديل.");
    }

    /**
     * Move the chosen models to another category, or take them out of every category.
     */
    public function moveModels(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);
        $validated = $request->validate([
            'model_ids' => ['required', 'array', 'min:1'],
            'model_ids.*' => ['integer', 'exists:product_models,id'],
            'target_category_id' => ['nullable', 'exists:production_point_categories,id'],
        ], ['model_ids.required' => 'اختر موديلاً واحداً على الأقل.']);
        $target = isset($validated['target_category_id']) ? ProductionPointCategory::find($validated['target_category_id']) : null;
        $moved = ProductModel::whereIn('id', $validated['model_ids'])->update(['point_category_id' => $target?->id]);

        return back()->with('success', $target
            ? "تم نقل {$moved} موديل إلى «{$target->name_ar}». التصنيف الجديد يُحتسب على الإنجاز المسجل من الآن."
            : "تمت إزالة التصنيف عن {$moved} موديل.");
    }

    public function creditUnclassified(Request $request, WorkerPointsService $points): RedirectResponse
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $points->creditUnclassified(Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth());

        return back()->with('success', $count > 0 ? "تم احتساب النقاط لـ {$count} إنجاز كان بلا تصنيف." : 'لا توجد أعمال بلا تصنيف يمكن احتسابها لهذا الشهر.');
    }
}
