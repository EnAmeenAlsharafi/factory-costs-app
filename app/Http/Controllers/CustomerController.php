<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\SalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('customers.view'), 403, 'غير مصرح لك بعرض سجل العملاء.');

        $customers = Customer::with(['customerType', 'defaultSalesChannel'])
            ->filter($request->only(['search', 'customer_type_id', 'sales_channel_id', 'status', 'credit']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customerTypes = CustomerType::orderBy('name_ar')->get();
        $salesChannels = SalesChannel::orderBy('sort_order')->orderBy('name_ar')->get();

        return view('customers.index', compact('customers', 'customerTypes', 'salesChannels'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('customers.create'), 403, 'غير مصرح لك بإضافة عميل جديد.');

        $nextCode = Customer::generateNextCode();
        $customerTypes = CustomerType::active()->orderBy('name_ar')->get();
        $salesChannels = SalesChannel::active()->orderBy('sort_order')->get();

        return view('customers.create', compact('nextCode', 'customerTypes', 'salesChannels'));
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $isCredit = $request->boolean('is_credit_customer');

        $customer = Customer::create([
            'customer_code' => Customer::generateNextCode(),
            'customer_type_id' => $request->validated('customer_type_id'),
            'default_sales_channel_id' => $request->validated('default_sales_channel_id'),
            'name' => $request->validated('name'),
            'commercial_name' => $request->validated('commercial_name'),
            'mobile' => $request->validated('mobile'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'tax_number' => $request->validated('tax_number'),
            'commercial_registration' => $request->validated('commercial_registration'),
            'city' => $request->validated('city'),
            'address' => $request->validated('address'),
            'contact_person' => $request->validated('contact_person'),
            'notes' => $request->validated('notes'),
            'is_active' => $request->boolean('is_active', true),
            'is_credit_customer' => $isCredit,
            'credit_limit' => $isCredit ? $request->validated('credit_limit', 0) : null,
            'opening_balance' => $request->validated('opening_balance', 0) ?? 0,
        ]);

        return redirect()->route('customers.index')->with('success', "تم تسجيل العميل ({$customer->name}) بالرمز [{$customer->customer_code}] بنجاح.");
    }

    public function show(Request $request, Customer $customer): View
    {
        abort_if(! $request->user()->can('customers.view'), 403, 'غير مصرح لك بعرض بيانات العميل.');

        $customer->load(['customerType', 'defaultSalesChannel']);

        return view('customers.show', compact('customer'));
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_if(! $request->user()->can('customers.update'), 403, 'غير مصرح لك بتعديل بيانات العملاء.');

        $customerTypes = CustomerType::orderBy('name_ar')->get();
        $salesChannels = SalesChannel::orderBy('sort_order')->get();

        return view('customers.edit', compact('customer', 'customerTypes', 'salesChannels'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $isCredit = $request->boolean('is_credit_customer');

        $customer->update([
            'customer_type_id' => $request->validated('customer_type_id'),
            'default_sales_channel_id' => $request->validated('default_sales_channel_id'),
            'name' => $request->validated('name'),
            'commercial_name' => $request->validated('commercial_name'),
            'mobile' => $request->validated('mobile'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'tax_number' => $request->validated('tax_number'),
            'commercial_registration' => $request->validated('commercial_registration'),
            'city' => $request->validated('city'),
            'address' => $request->validated('address'),
            'contact_person' => $request->validated('contact_person'),
            'notes' => $request->validated('notes'),
            'is_active' => $request->boolean('is_active'),
            'is_credit_customer' => $isCredit,
            'credit_limit' => $isCredit ? $request->validated('credit_limit', 0) : null,
            'opening_balance' => $request->validated('opening_balance', 0) ?? 0,
        ]);

        return redirect()->route('customers.index')->with('success', "تم تحديث بيانات العميل ({$customer->name}) بنجاح.");
    }

    public function toggleStatus(Request $request, Customer $customer): RedirectResponse
    {
        abort_if(! $request->user()->can('customers.update'), 403, 'غير مصرح لك بتغيير حالة العميل.');

        $customer->update(['is_active' => ! $customer->is_active]);

        $statusMessage = $customer->is_active ? 'تم تفعيل العميل بنجاح.' : 'تم تعطيل العميل بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
