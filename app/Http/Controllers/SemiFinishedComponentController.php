<?php

namespace App\Http\Controllers;

use App\Http\Requests\SemiFinishedComponentRequest;
use App\Models\SemiFinishedComponent;
use App\Services\DocumentNumberService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemiFinishedComponentController extends Controller
{
    public function __construct(protected DocumentNumberService $documentNumberService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('semi_finished_components.view'), 403, 'غير مصرح لك بعرض المكونات نصف المصنعة.');

        $components = SemiFinishedComponent::with('recipe.currentApprovedVersion')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where('component_code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('recipes.components.index', compact('components'));
    }

    public function store(SemiFinishedComponentRequest $request): RedirectResponse
    {
        try {
            $code = $this->documentNumberService->generateComponentCode();
            SemiFinishedComponent::create(array_merge($request->validated(), [
                'component_code' => $code,
            ]));

            return redirect()->route('recipes.components.index')
                ->with('success', 'تم إضافة المكون نصف المصنع بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(SemiFinishedComponentRequest $request, SemiFinishedComponent $component): RedirectResponse
    {
        try {
            $component->update($request->validated());

            return redirect()->route('recipes.components.index')
                ->with('success', 'تم تحديث بيانات المكون نصف المصنع بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, SemiFinishedComponent $component): RedirectResponse
    {
        abort_if(! $request->user()->can('semi_finished_components.manage'), 403, 'غير مصرح لك بتغيير حالة المكون.');

        $component->update(['is_active' => ! $component->is_active]);

        return back()->with('success', 'تم تحديث حالة المكون بنجاح.');
    }
}
