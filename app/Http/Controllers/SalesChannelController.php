<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesChannelRequest;
use App\Models\SalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesChannelController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('sales_channels.view'), 403, 'غير مصرح لك بعرض قنوات البيع.');

        $channels = SalesChannel::withCount('customers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return view('sales-channels.index', compact('channels'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('sales_channels.manage'), 403, 'غير مصرح لك بإضافة قنوات بيع.');

        return view('sales-channels.create');
    }

    public function store(SalesChannelRequest $request): RedirectResponse
    {
        SalesChannel::create([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0) ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('sales-channels.index')->with('success', 'تم إنشاء قناة البيع بنجاح.');
    }

    public function edit(Request $request, SalesChannel $salesChannel): View
    {
        abort_if(! $request->user()->can('sales_channels.manage'), 403, 'غير مصرح لك بتعديل قنوات البيع.');

        return view('sales-channels.edit', compact('salesChannel'));
    }

    public function update(SalesChannelRequest $request, SalesChannel $salesChannel): RedirectResponse
    {
        $salesChannel->update([
            'code' => strtoupper($request->validated('code')),
            'name_ar' => $request->validated('name_ar'),
            'name_en' => $request->validated('name_en'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0) ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('sales-channels.index')->with('success', 'تم تحديث بيانات قناة البيع بنجاح.');
    }

    public function toggleStatus(Request $request, SalesChannel $salesChannel): RedirectResponse
    {
        abort_if(! $request->user()->can('sales_channels.manage'), 403, 'غير مصرح لك بتغيير حالة القناة.');

        $salesChannel->update(['is_active' => ! $salesChannel->is_active]);

        $statusMessage = $salesChannel->is_active ? 'تم تفعيل قناة البيع بنجاح.' : 'تم تعطيل قناة البيع بنجاح.';

        return back()->with('success', $statusMessage);
    }
}
