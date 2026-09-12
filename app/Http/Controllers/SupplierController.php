<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('suppliers.view'), 403, 'غير مصرح لك بعرض سجل الموردين.');

        $suppliers = Supplier::filter($request->only(['search', 'status']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('suppliers.manage'), 403, 'غير مصرح لك بإضافة مورد جديد.');

        $nextCode = Supplier::generateNextCode();

        return view('suppliers.create', compact('nextCode'));
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create([
            'supplier_code' => Supplier::generateNextCode(),
            'name' => $request->validated('name'),
            'commercial_name' => $request->validated('commercial_name'),
            'contact_person' => $request->validated('contact_person'),
            'mobile' => $request->validated('mobile'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'tax_number' => $request->validated('tax_number'),
            'commercial_registration' => $request->validated('commercial_registration'),
            'city' => $request->validated('city'),
            'address' => $request->validated('address'),
            'notes' => $request->validated('notes'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('suppliers.index')->with('success', "تم تسجيل المورد ({$supplier->name}) بالرمز [{$supplier->supplier_code}] بنجاح.");
    }

    public function show(Request $request, Supplier $supplier): View
    {
        abort_if(! $request->user()->can('suppliers.view'), 403, 'غير مصرح لك بعرض بيانات المورد.');

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Request $request, Supplier $supplier): View
    {
        abort_if(! $request->user()->can('suppliers.manage'), 403, 'غير مصرح لك بتعديل بيانات الموردين.');

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update([
            'name' => $request->validated('name'),
            'commercial_name' => $request->validated('commercial_name'),
            'contact_person' => $request->validated('contact_person'),
            'mobile' => $request->validated('mobile'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'tax_number' => $request->validated('tax_number'),
            'commercial_registration' => $request->validated('commercial_registration'),
            'city' => $request->validated('city'),
            'address' => $request->validated('address'),
            'notes' => $request->validated('notes'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('suppliers.index')->with('success', "تم تحديث بيانات المورد ({$supplier->name}) بنجاح.");
    }

    public function toggleStatus(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_if(! $request->user()->can('suppliers.manage'), 403, 'غير مصرح لك بتغيير حالة المورد.');

        $supplier->update(['is_active' => ! $supplier->is_active]);

        $statusMessage = $supplier->is_active ? 'تم تفعيل المورد بنجاح.' : 'تم تعطيل المورد بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
