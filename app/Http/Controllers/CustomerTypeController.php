<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerTypeRequest;
use App\Models\CustomerType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerTypeController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('customer_types.view'), 403, 'غير مصرح لك بعرض أنواع العملاء.');

        $types = CustomerType::withCount('customers')
            ->orderBy('id')
            ->paginate(15);

        return view('customer-types.index', compact('types'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('customer_types.manage'), 403, 'غير مصرح لك بإضافة نوع عميل جديد.');

        return view('customer-types.create');
    }

    public function store(CustomerTypeRequest $request): RedirectResponse
    {
        CustomerType::create([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('customer-types.index')->with('success', 'تم إنشاء نوع العميل بنجاح.');
    }

    public function edit(Request $request, CustomerType $customerType): View
    {
        abort_if(! $request->user()->can('customer_types.manage'), 403, 'غير مصرح لك بتعديل أنواع العملاء.');

        return view('customer-types.edit', compact('customerType'));
    }

    public function update(CustomerTypeRequest $request, CustomerType $customerType): RedirectResponse
    {
        $customerType->update([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('customer-types.index')->with('success', 'تم تحديث نوع العميل بنجاح.');
    }

    public function toggleStatus(Request $request, CustomerType $customerType): RedirectResponse
    {
        abort_if(! $request->user()->can('customer_types.manage'), 403, 'غير مصرح لك بتغيير حالة نوع العميل.');

        $customerType->update(['is_active' => ! $customerType->is_active]);

        $statusMessage = $customerType->is_active ? 'تم تفعيل نوع العميل بنجاح.' : 'تم تعطيل نوع العميل بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
