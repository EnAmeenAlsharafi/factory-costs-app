<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuotationRequest;
use App\Models\Customer;
use App\Models\CustomerProductAlias;
use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductModel;
use App\Models\Quotation;
use App\Models\SalesChannel;
use App\Services\QuotationService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(protected QuotationService $quotationService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('quotations.view'), 403, 'غير مصرح لك بعرض عروض الأسعار.');

        $quotations = Quotation::with('customer', 'salesChannel', 'createdBy')
            ->filter($request->only(['search', 'status', 'customer_id']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();

        return view('sales.quotations.index', compact('quotations', 'customers', 'salesChannels'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('quotations.create'), 403, 'غير مصرح لك بإنشاء عرض سعر جديد.');

        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();
        $productModels = ProductModel::where('is_active', true)->with('configurations', 'aliases')->get();
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.quotations.create', compact('customers', 'salesChannels', 'productModels', 'fabrics', 'colors', 'aliases'));
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        try {
            $quotation = $this->quotationService->createQuotation(
                $request->validated(),
                $request->validated('lines'),
                $request->user()
            );

            return redirect()->route('sales.quotations.show', $quotation)
                ->with('success', 'تم إنشاء عرض السعر بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, Quotation $quotation): View
    {
        abort_if(! $request->user()->can('quotations.view'), 403, 'غير مصرح لك بعرض هذا العرض.');

        $quotation->load([
            'customer',
            'salesChannel',
            'createdBy',
            'approvedBy',
            'lines.productModel',
            'lines.productConfiguration',
            'lines.customerProductAlias',
            'lines.fabricMaterial',
            'lines.fabricColor',
            'customerOrder',
        ]);

        return view('sales.quotations.show', compact('quotation'));
    }

    public function edit(Request $request, Quotation $quotation): View
    {
        abort_if(! $request->user()->can('quotations.update'), 403, 'غير مصرح لك بتعديل هذا العرض.');
        abort_if($quotation->status === 'CONVERTED', 403, 'لا يمكن تعديل عرض سعر تم تحويله لطلب عميل مسبقاً.');

        $quotation->load('lines');
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $salesChannels = SalesChannel::where('is_active', true)->get();
        $productModels = ProductModel::where('is_active', true)->with('configurations', 'aliases')->get();
        $fabrics = Material::whereHas('category', fn ($c) => $c->where('code', 'FABRIC'))->with('fabricColors')->get();
        $colors = FabricColor::where('is_active', true)->get();
        $aliases = CustomerProductAlias::where('is_active', true)->get();

        return view('sales.quotations.edit', compact('quotation', 'customers', 'salesChannels', 'productModels', 'fabrics', 'colors', 'aliases'));
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        try {
            $this->quotationService->updateQuotation(
                $quotation,
                $request->validated(),
                $request->validated('lines')
            );

            return redirect()->route('sales.quotations.show', $quotation)
                ->with('success', 'تم تحديث عرض السعر بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_if(! $request->user()->can('quotations.approve'), 403, 'غير مصرح لك باعتماد عرض السعر.');

        try {
            $this->quotationService->approveQuotation($quotation, $request->user());

            return redirect()->route('sales.quotations.show', $quotation)
                ->with('success', 'تم اعتماد عرض السعر بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_if(! $request->user()->can('quotations.approve'), 403, 'غير مصرح لك برفض عرض السعر.');

        try {
            $this->quotationService->rejectQuotation($quotation, $request->user());

            return redirect()->route('sales.quotations.show', $quotation)
                ->with('success', 'تم رفض عرض السعر.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_if(! $request->user()->can('orders.create'), 403, 'غير مصرح لك بتحويل عروض الأسعار إلى طلبات عملاء.');

        try {
            $order = $this->quotationService->convertToOrder($quotation, $request->user(), $request->only(['customer_reference', 'external_order_reference', 'requested_delivery_date', 'priority']));

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم تحويل عرض السعر إلى طلب عميل بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
