<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPortalOrderRequest;
use App\Models\CustomerPortalRequest;
use App\Services\CustomerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPortalReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('orders.update'), 403);
        $query = CustomerPortalRequest::with(['customer', 'order'])->where('status', '!=', 'DRAFT');
        if ($request->filled('status') && array_key_exists($request->string('status')->toString(), CustomerPortalRequest::STATUSES)) {
            $query->where('status', $request->input('status'));
        }
        $submissions = $query->latest('updated_at')->latest('id')->paginate(20)->withQueryString();

        return view('portal.review-index', compact('submissions'));
    }

    public function show(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): View
    {
        abort_unless($request->user()->can('orders.update'), 403);
        abort_if($submission->status === 'DRAFT', 404);
        $submission->load('customer', 'events');

        return view('portal.review', array_merge(compact('submission'), $portal->catalog($submission->customer)));
    }

    public function offer(CustomerPortalOrderRequest $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        $portal->offer($submission, $request->user(), $request->validated());

        return redirect()->route('sales.portal-requests.show', $submission)->with('success', 'تم إرسال العرض للعميل لتأكيد السعر والمواصفات.');
    }

    public function returnForChanges(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        abort_unless($request->user()->can('orders.update'), 403);
        $request->validate(['revision' => ['required', 'integer', 'min:1'], 'message' => ['required', 'string', 'max:5000']]);
        $portal->returnForChanges($submission, $request->user(), $request->integer('revision'), $request->input('message'));

        return back()->with('success', 'تمت إعادة الطلب للعميل لاستكمال البيانات.');
    }
}
