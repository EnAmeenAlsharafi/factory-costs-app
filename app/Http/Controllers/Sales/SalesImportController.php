<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Services\Sales\SalesImportConfirmService;
use App\Services\Sales\SalesImportParserService;
use App\Services\Sales\SalesImportPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SalesImportController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('sales_imports.view'), 403, 'غير مصرح لك بعرض دفعات استيراد طلبات سلة.');

        $batches = SalesImportBatch::with('salesChannel', 'uploadedByUser')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.imports.index', compact('batches'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك برفع ملفات استيراد طلبات سلة.');

        $channels = SalesChannel::active()->orderBy('name_ar')->get();

        return view('sales.imports.create', compact('channels'));
    }

    public function store(Request $request, SalesImportParserService $parser): RedirectResponse
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك برفع ملفات استيراد طلبات سلة.');

        $validated = $request->validate([
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'import_file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $channel = SalesChannel::findOrFail($validated['sales_channel_id']);
        $file = $request->file('import_file');
        $storedPath = $file->store('sales-imports', 'local');

        $batch = $parser->parse(
            Storage::disk('local')->path($storedPath),
            $file->getClientOriginalName(),
            $channel,
            $request->user()
        );

        return redirect()->route('sales.imports.preview', $batch);
    }

    public function preview(Request $request, SalesImportBatch $batch, SalesImportPreviewService $previewService): View
    {
        abort_if(! $request->user()->can('sales_imports.view'), 403, 'غير مصرح لك بمعاينة دفعات الاستيراد.');

        $preview = $previewService->preview($batch);

        return view('sales.imports.preview', compact('batch', 'preview'));
    }

    public function confirm(Request $request, SalesImportBatch $batch, SalesImportConfirmService $confirmService): RedirectResponse
    {
        abort_if(! $request->user()->can('sales_imports.manage'), 403, 'غير مصرح لك بتأكيد استيراد طلبات سلة.');

        $confirmed = $confirmService->confirm($batch, $request->user());

        return redirect()->route('sales.imports.result', $confirmed)->with('success', 'تم تأكيد الاستيراد بنجاح.');
    }

    public function result(Request $request, SalesImportBatch $batch): View
    {
        abort_if(! $request->user()->can('sales_imports.view'), 403, 'غير مصرح لك بعرض نتيجة الاستيراد.');

        $batch->load('rows.createdCustomerOrder');

        return view('sales.imports.result', compact('batch'));
    }
}
