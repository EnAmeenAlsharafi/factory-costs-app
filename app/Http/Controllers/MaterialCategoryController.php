<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialCategoryRequest;
use App\Models\MaterialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('materials.view'), 403, 'غير مصرح لك بعرض تصنيفات المواد.');

        $categories = MaterialCategory::withCount('materials')
            ->orderBy('id')
            ->get();

        return view('material-categories.index', compact('categories'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بإضافة تصنيف مواد جديد.');

        return view('material-categories.create');
    }

    public function store(MaterialCategoryRequest $request): RedirectResponse
    {
        MaterialCategory::create([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('material-categories.index')
            ->with('success', 'تم إضافة تصنيف المواد بنجاح.');
    }

    public function edit(Request $request, MaterialCategory $category): View
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتعديل تصنيف المواد.');

        return view('material-categories.edit', compact('category'));
    }

    public function update(MaterialCategoryRequest $request, MaterialCategory $category): RedirectResponse
    {
        $category->update([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('material-categories.index')
            ->with('success', 'تم تحديث تصنيف المواد بنجاح.');
    }

    public function toggleStatus(Request $request, MaterialCategory $category): RedirectResponse
    {
        abort_if(! $request->user()->can('materials.manage'), 403, 'غير مصرح لك بتغيير حالة تصنيف المواد.');

        $category->update(['is_active' => ! $category->is_active]);

        $msg = $category->is_active ? 'تم تفعيل تصنيف المواد بنجاح.' : 'تم تعطيل تصنيف المواد بنجاح.';

        return back()->with('success', $msg);
    }
}
