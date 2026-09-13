<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequestFormRequest;
use App\Models\Department;
use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\PurchaseRequestService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(
        protected PurchaseRequestService $requestService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $query = PurchaseRequest::with(['requestedByUser', 'department', 'warehouse', 'lines.material']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('justification', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        return view('purchasing.requests.index', compact('requests'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.request'), 403);

        $warehouses = Warehouse::active()->get();
        $departments = Department::where('is_active', true)->get();
        $materials = Material::where('is_active', true)->with('unitOfMeasure', 'fabricColors')->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $units = UnitOfMeasure::where('is_active', true)->get();

        return view('purchasing.requests.create', compact('warehouses', 'departments', 'materials', 'suppliers', 'units'));
    }

    public function store(PurchaseRequestFormRequest $request): RedirectResponse
    {
        try {
            $pr = $this->requestService->createRequest($request->validated(), $request->user());

            if ($request->input('action') === 'submit') {
                $this->requestService->submitRequest($pr, $request->user());
            }

            return redirect()->route('purchasing.requests.show', $pr)
                ->with('success', "تم إنشاء طلب الشراء رقم {$pr->request_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(PurchaseRequest $purchaseRequest, Request $request): View
    {
        abort_if(! $request->user()->can('purchasing.view'), 403);

        $purchaseRequest->load([
            'requestedByUser',
            'department',
            'warehouse',
            'reviewedByUser',
            'approvedByUser',
            'rejectedByUser',
            'lines.material.unitOfMeasure',
            'lines.fabricColor',
            'lines.preferredPurchaseUnit',
            'lines.preferredSupplier',
            'purchaseOrders',
            'rfqs',
        ]);

        return view('purchasing.requests.show', compact('purchaseRequest'));
    }

    public function review(PurchaseRequest $purchaseRequest, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('purchasing.review_request'), 403);

        $request->validate([
            'action' => ['required', 'in:APPROVE,REJECT'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->requestService->reviewRequest(
                $purchaseRequest,
                $request->user(),
                $request->action,
                $request->rejection_reason
            );

            $msg = $request->action === 'APPROVE' ? 'تم اعتماد طلب الشراء بنجاح.' : 'تم رفض طلب الشراء.';

            return back()->with('success', $msg);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
