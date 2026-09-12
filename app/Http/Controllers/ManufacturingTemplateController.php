<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManufacturingTemplateRequest;
use App\Models\ManufacturingTemplate;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\SemiFinishedComponent;
use App\Models\UnitOfMeasure;
use App\Services\DocumentNumberService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManufacturingTemplateController extends Controller
{
    public function __construct(protected DocumentNumberService $documentNumberService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('manufacturing_templates.view'), 403, 'غير مصرح لك بعرض قوالب التصنيع.');

        $templates = ManufacturingTemplate::with('items.material', 'items.semiFinishedComponent', 'items.unit')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where('template_code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $configurations = ProductConfiguration::with('productModel')->where('is_active', true)->get();
        $components = SemiFinishedComponent::where('is_active', true)->get();

        return view('recipes.templates.index', compact('templates', 'configurations', 'components'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('manufacturing_templates.manage'), 403, 'غير مصرح لك بإنشاء قالب تصنيع.');

        $materials = Material::where('is_active', true)->orderBy('name_ar')->get();
        $components = SemiFinishedComponent::where('is_active', true)->get();
        $units = UnitOfMeasure::orderBy('name_ar')->get();

        return view('recipes.templates.create', compact('materials', 'components', 'units'));
    }

    public function store(ManufacturingTemplateRequest $request): RedirectResponse
    {
        try {
            $code = $this->documentNumberService->generateTemplateCode();
            $template = ManufacturingTemplate::create(array_merge($request->validated(), [
                'template_code' => $code,
            ]));

            foreach ($request->validated('items') as $index => $itemData) {
                $template->items()->create([
                    'item_type' => $itemData['item_type'],
                    'material_id' => $itemData['item_type'] === 'MATERIAL' ? $itemData['material_id'] : null,
                    'semi_finished_component_id' => $itemData['item_type'] === 'SEMI_FINISHED_COMPONENT' ? $itemData['semi_finished_component_id'] : null,
                    'quantity' => $itemData['quantity'],
                    'unit_id' => $itemData['unit_id'],
                    'waste_percentage' => $itemData['waste_percentage'] ?? 0.00,
                    'notes' => $itemData['notes'] ?? null,
                    'sort_order' => $index + 1,
                ]);
            }

            return redirect()->route('recipes.templates.index')
                ->with('success', 'تم إنشاء قالب التصنيع بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, ManufacturingTemplate $template): View
    {
        abort_if(! $request->user()->can('manufacturing_templates.view'), 403, 'غير مصرح لك بعرض القالب.');

        $template->load('items.material', 'items.semiFinishedComponent', 'items.unit');
        $configurations = ProductConfiguration::with('productModel')->where('is_active', true)->get();
        $components = SemiFinishedComponent::where('is_active', true)->get();

        return view('recipes.templates.show', compact('template', 'configurations', 'components'));
    }

    public function toggleStatus(Request $request, ManufacturingTemplate $template): RedirectResponse
    {
        abort_if(! $request->user()->can('manufacturing_templates.manage'), 403, 'غير مصرح لك بتغيير حالة القالب.');

        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', 'تم تحديث حالة قالب التصنيع بنجاح.');
    }
}
