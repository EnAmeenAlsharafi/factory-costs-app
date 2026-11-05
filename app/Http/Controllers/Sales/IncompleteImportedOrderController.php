<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncompleteImportedOrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('orders.update'), 403, 'غير مصرح لك بعرض طلبات الاستيراد بانتظار الاستكمال.');

        $orders = CustomerOrder::whereNotNull('import_batch_id')
            ->where('status', 'DRAFT')
            ->whereHas('lines', fn ($q) => $q->where('is_incomplete', true))
            ->with(['customer', 'salesChannel', 'importBatch'])
            ->withCount(['lines as incomplete_lines_count' => fn ($q) => $q->where('is_incomplete', true)])
            ->latest('id')
            ->paginate(20);

        return view('sales.incomplete-imports.index', compact('orders'));
    }
}
