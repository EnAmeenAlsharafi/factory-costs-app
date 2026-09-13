<?php

namespace App\Http\Controllers;

use App\Http\Requests\StandardBedSizeRequest;
use App\Models\StandardBedSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StandardBedSizeController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('products.view'), 403, 'غير مصرح لك بعرض المقاسات القياسية.');

        $sort = $request->query('sort', 'sort_order');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['sort_order', 'code', 'name_ar', 'width_cm', 'length_cm', 'id'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }

        $sizes = StandardBedSize::orderBy($sort, $direction)->orderBy('id')->get();

        return view('products.sizes.index', compact('sizes', 'sort', 'direction'));
    }

    public function store(StandardBedSizeRequest $request): RedirectResponse
    {
        StandardBedSize::create($request->validated());

        return redirect()->route('products.sizes.index')
            ->with('success', 'تم إضافة المقاس القياسي بنجاح.');
    }

    public function update(StandardBedSizeRequest $request, StandardBedSize $size): RedirectResponse
    {
        $size->update($request->validated());

        return redirect()->route('products.sizes.index')
            ->with('success', 'تم تحديث بيانات المقاس القياسي بنجاح.');
    }

    public function toggleStatus(Request $request, StandardBedSize $size): RedirectResponse
    {
        abort_if(! $request->user()->can('products.manage'), 403, 'غير مصرح لك بتغيير حالة المقاس.');

        $size->update(['is_active' => ! $size->is_active]);

        return back()->with('success', 'تم تحديث حالة المقاس بنجاح.');
    }
}
