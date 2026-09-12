<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('units.view'), 403, 'غير مصرح لك بعرض وحدات القياس.');

        $units = UnitOfMeasure::withCount(['conversionsFrom', 'conversionsTo'])
            ->orderBy('id')
            ->paginate(15);

        return view('units.index', compact('units'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('units.manage'), 403, 'غير مصرح لك بإضافة وحدة قياس جديدة.');

        return view('units.create');
    }

    public function store(UnitOfMeasureRequest $request): RedirectResponse
    {
        $allowsDecimal = $request->boolean('allows_decimal');

        UnitOfMeasure::create([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'symbol' => $request->validated('symbol'),
            'unit_type' => $request->validated('unit_type'),
            'allows_decimal' => $allowsDecimal,
            'decimal_precision' => $allowsDecimal ? ($request->validated('decimal_precision') ?? 2) : 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('units.index')->with('success', 'تم إنشاء وحدة القياس بنجاح.');
    }

    public function edit(Request $request, UnitOfMeasure $unit): View
    {
        abort_if(! $request->user()->can('units.manage'), 403, 'غير مصرح لك بتعديل وحدات القياس.');

        return view('units.edit', compact('unit'));
    }

    public function update(UnitOfMeasureRequest $request, UnitOfMeasure $unit): RedirectResponse
    {
        $allowsDecimal = $request->boolean('allows_decimal');

        $unit->update([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'symbol' => $request->validated('symbol'),
            'unit_type' => $request->validated('unit_type'),
            'allows_decimal' => $allowsDecimal,
            'decimal_precision' => $allowsDecimal ? ($request->validated('decimal_precision') ?? 2) : 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('units.index')->with('success', 'تم تحديث بيانات وحدة القياس بنجاح.');
    }

    public function toggleStatus(Request $request, UnitOfMeasure $unit): RedirectResponse
    {
        abort_if(! $request->user()->can('units.manage'), 403, 'غير مصرح لك بتغيير حالة وحدة القياس.');

        $unit->update(['is_active' => ! $unit->is_active]);

        $statusMessage = $unit->is_active ? 'تم تفعيل وحدة القياس بنجاح.' : 'تم تعطيل وحدة القياس بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
