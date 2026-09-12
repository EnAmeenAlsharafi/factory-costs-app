<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManufacturingRecipeRequest;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ManufacturingTemplate;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\SemiFinishedComponent;
use App\Models\UnitOfMeasure;
use App\Services\RecipeService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManufacturingRecipeController extends Controller
{
    public function __construct(protected RecipeService $recipeService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('recipes.view'), 403, 'غير مصرح لك بعرض وصفات التصنيع.');

        $recipes = ManufacturingRecipe::with([
            'productConfiguration.productModel',
            'semiFinishedComponent',
            'currentApprovedVersion',
            'versions',
        ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('recipe_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('productConfiguration.productModel', fn ($m) => $m->where('name_ar', 'like', "%{$search}%"))
                        ->orWhereHas('semiFinishedComponent', fn ($c) => $c->where('name_ar', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('target_type'), function ($q) use ($request) {
                $q->where('target_type', $request->target_type);
            })
            ->when($request->filled('model_id'), function ($q) use ($request) {
                $q->whereHas('productConfiguration', fn ($pc) => $pc->where('product_model_id', $request->model_id));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $productModels = ProductModel::orderBy('name_ar')->get();

        return view('recipes.index', compact('recipes', 'productModels'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('recipes.manage'), 403, 'غير مصرح لك بإنشاء وصفة تصنيع جديدة.');

        $configurations = ProductConfiguration::with('productModel')->where('is_active', true)->get();
        $components = SemiFinishedComponent::where('is_active', true)->get();
        $materials = Material::where('is_active', true)->orderBy('name_ar')->get();
        $units = UnitOfMeasure::orderBy('name_ar')->get();
        $templates = ManufacturingTemplate::where('is_active', true)->get();

        return view('recipes.create', compact('configurations', 'components', 'materials', 'units', 'templates'));
    }

    public function store(ManufacturingRecipeRequest $request): RedirectResponse
    {
        try {
            $recipe = $this->recipeService->createRecipe($request->validated(), $request->validated('items'));

            return redirect()->route('recipes.show', $recipe)
                ->with('success', 'تم إنشاء وصفة التصنيع وحفظ المسودة بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, ManufacturingRecipe $recipe): View
    {
        abort_if(! $request->user()->can('recipes.view'), 403, 'غير مصرح لك بعرض الوصفة.');

        $recipe->load([
            'productConfiguration.productModel',
            'semiFinishedComponent',
            'versions.approvedBy',
            'versions.items.material',
            'versions.items.semiFinishedComponent',
            'versions.items.unit',
        ]);

        $selectedVersionId = $request->query('version_id');
        $selectedVersion = $selectedVersionId
            ? $recipe->versions->firstWhere('id', $selectedVersionId)
            : ($recipe->currentApprovedVersion ?? $recipe->versions->first());

        $costPreview = $selectedVersion ? $this->recipeService->calculateStandardCostPreview($selectedVersion) : null;

        return view('recipes.show', compact('recipe', 'selectedVersion', 'costPreview'));
    }

    public function edit(Request $request, ManufacturingRecipe $recipe, ManufacturingRecipeVersion $version): View
    {
        abort_if(! $request->user()->can('recipes.manage'), 403, 'غير مصرح لك بتعديل المسودة.');
        abort_if($version->status !== 'DRAFT', 403, 'يمكن تعديل الإصدارات في حالة مسودة فقط. للإصدارات المعتمدة يرجى نسخ الإصدار أولاً.');

        $version->load(['items.material', 'items.semiFinishedComponent', 'items.unit']);
        $materials = Material::where('is_active', true)->orderBy('name_ar')->get();
        $components = SemiFinishedComponent::where('is_active', true)->get();
        $units = UnitOfMeasure::orderBy('name_ar')->get();

        return view('recipes.edit', compact('recipe', 'version', 'materials', 'components', 'units'));
    }

    public function updateVersion(Request $request, ManufacturingRecipe $recipe, ManufacturingRecipeVersion $version): RedirectResponse
    {
        abort_if(! $request->user()->can('recipes.manage'), 403, 'غير مصرح لك بتعديل المسودة.');

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:MATERIAL,SEMI_FINISHED_COMPONENT',
            'items.*.material_id' => 'required_if:items.*.item_type,MATERIAL|nullable|exists:materials,id',
            'items.*.semi_finished_component_id' => 'required_if:items.*.item_type,SEMI_FINISHED_COMPONENT|nullable|exists:semi_finished_components,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_id' => 'required|exists:units_of_measure,id',
            'items.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $this->recipeService->updateDraftVersion($version, $validated, $validated['items']);

            return redirect()->route('recipes.show', [$recipe, 'version_id' => $version->id])
                ->with('success', 'تم تحديث مسودة الوصفة بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, ManufacturingRecipe $recipe, ManufacturingRecipeVersion $version): RedirectResponse
    {
        abort_if(! $request->user()->can('recipes.approve'), 403, 'غير مصرح لك باعتماد وصفات التصنيع.');

        try {
            $this->recipeService->approveVersion($version, $request->user());

            return redirect()->route('recipes.show', [$recipe, 'version_id' => $version->id])
                ->with('success', 'تم اعتماد إصدار الوصفة (V'.$version->version_number.') بنجاح وتم إلغاء اعتماد الإصدار السابق تلقائياً.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function copyVersion(Request $request, ManufacturingRecipe $recipe, ManufacturingRecipeVersion $version): RedirectResponse
    {
        abort_if(! $request->user()->can('recipes.manage'), 403, 'غير مصرح لك بنسخ الإصدار.');

        try {
            $newVersion = $this->recipeService->copyToNewVersion($version);

            return redirect()->route('recipes.show', [$recipe, 'version_id' => $newVersion->id])
                ->with('success', 'تم إنشاء إصدار مسودة جديد (V'.$newVersion->version_number.') مشتق من V'.$version->version_number);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function createFromTemplate(Request $request, ManufacturingTemplate $template): RedirectResponse
    {
        abort_if(! $request->user()->can('recipes.manage'), 403, 'غير مصرح لك بإنشاء وصفة من قالب.');

        $request->validate([
            'target_type' => 'required|in:PRODUCT_CONFIGURATION,SEMI_FINISHED_COMPONENT',
            'product_configuration_id' => 'required_if:target_type,PRODUCT_CONFIGURATION|nullable|exists:product_configurations,id',
            'semi_finished_component_id' => 'required_if:target_type,SEMI_FINISHED_COMPONENT|nullable|exists:semi_finished_components,id',
            'name' => 'required|string|max:255',
        ]);

        try {
            $recipe = $this->recipeService->createRecipeFromTemplate($template, $request->only(['target_type', 'product_configuration_id', 'semi_finished_component_id', 'name']));

            return redirect()->route('recipes.show', $recipe)
                ->with('success', 'تم إنشاء وصفة جديدة بناءً على القالب ['.$template->name_ar.'] بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
