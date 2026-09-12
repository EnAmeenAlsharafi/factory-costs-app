<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionReviewRequest;
use App\Models\CustomerOrder;
use App\Models\ManufacturingRecipeVersion;
use App\Services\CustomerOrderService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReviewController extends Controller
{
    public function __construct(protected CustomerOrderService $customerOrderService) {}

    public function showReviewPage(Request $request, CustomerOrder $order): View
    {
        abort_if(! $request->user()->can('orders.review_production') && ! $request->user()->can('orders.approve_production'), 403, 'غير مصرح لك بمراجعة المواصفات الإنتاجية.');

        $order->load([
            'customer',
            'salesChannel',
            'lines.productModel',
            'lines.productConfiguration',
            'lines.customerProductAlias',
            'lines.fabricMaterial',
            'lines.fabricColor',
        ]);

        $recipeVersions = ManufacturingRecipeVersion::where('status', 'APPROVED')
            ->with('recipe')
            ->get();

        return view('sales.orders.review', compact('order', 'recipeVersions'));
    }

    public function approveForProduction(ProductionReviewRequest $request, CustomerOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('orders.approve_production'), 403, 'غير مصرح لك باعتماد الطلبات للتصنيع.');

        try {
            $this->customerOrderService->approveForProduction(
                $order,
                $request->user(),
                $request->validated('lines') ?? []
            );

            return redirect()->route('sales.orders.show', $order)
                ->with('success', 'تم اعتماد الطلب للتصنيع (APPROVED_FOR_PRODUCTION) بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
