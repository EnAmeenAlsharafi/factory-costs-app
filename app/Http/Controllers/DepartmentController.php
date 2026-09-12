<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('departments.view'), 403, 'غير مصرح لك بعرض أقسام المصنع.');

        $departments = Department::withCount('users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('departments.manage'), 403, 'غير مصرح لك بإضافة قسم جديد.');

        return view('departments.create');
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0) ?? 0,
            'is_production_department' => $request->boolean('is_production_department'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('departments.index')->with('success', 'تم إنشاء القسم بنجاح.');
    }

    public function edit(Request $request, Department $department): View
    {
        abort_if(! $request->user()->can('departments.manage'), 403, 'غير مصرح لك بتعديل بيانات الأقسام.');

        return view('departments.edit', compact('department'));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0) ?? 0,
            'is_production_department' => $request->boolean('is_production_department'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('departments.index')->with('success', 'تم تحديث بيانات القسم بنجاح.');
    }

    public function toggleStatus(Request $request, Department $department): RedirectResponse
    {
        abort_if(! $request->user()->can('departments.manage'), 403, 'غير مصرح لك بتغيير حالة القسم.');

        $department->update(['is_active' => ! $department->is_active]);

        $statusMessage = $department->is_active ? 'تم تفعيل القسم بنجاح.' : 'تم تعطيل القسم بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
