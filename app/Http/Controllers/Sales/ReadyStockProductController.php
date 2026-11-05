<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReadyStockProductRequest;
use App\Models\Material;
use App\Models\UnitOfMeasure;
use App\Services\InventoryService;
use App\Services\Sales\ReadyStockProductImportService;
use App\Services\Sales\ReadyStockProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class ReadyStockProductController extends Controller
{
    public function index(Request $request, InventoryService $inventoryService): View
    {
        abort_if(! $request->user()->can('materials.view'), 403, 'غير مصرح لك بعرض المنتجات الجاهزة للبيع.');

        $query = Material::with('category')->sellableReady();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sales_sku', 'like', "%{$search}%")
                    ->orWhere('sales_display_name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
                $q->orWhere('salla_product_id', 'like', "%{$search}%");
            });
        }

        $materials = $query->orderBy('name_ar')->paginate(20)->withQueryString();

        foreach ($materials as $material) {
            $material->availability = $inventoryService->getReadyProductAvailability($material);
        }

        return view('sales.ready-stock-products.index', compact('materials'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('materials.manage'), 403);

        return $this->form(new Material(['is_active' => true, 'base_unit_id' => UnitOfMeasure::where('code', 'PIECE')->value('id')]));
    }

    public function store(ReadyStockProductRequest $request, ReadyStockProductService $products): RedirectResponse
    {
        $products->save($request->validated());

        return redirect()->route('sales.ready-stock-products.index')->with('success', 'تمت إضافة المنتج الجاهز بنجاح.');
    }

    public function createOption(Request $request, Material $material): View
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        abort_unless($material->is_sellable_ready_product && $material->has_ready_options && ! $material->ready_parent_id, 404);

        return $this->form(new Material([
            'ready_parent_id' => $material->id, 'name_ar' => $material->name_ar.' — ',
            'base_unit_id' => $material->base_unit_id, 'is_active' => true, 'sales_image_url' => $material->sales_image_url,
        ]));
    }

    public function edit(Request $request, Material $material): View
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        abort_unless($material->is_sellable_ready_product, 404);

        return $this->form($material);
    }

    public function update(ReadyStockProductRequest $request, Material $material, ReadyStockProductService $products): RedirectResponse
    {
        abort_unless($material->is_sellable_ready_product, 404);
        $products->save($request->validated(), $material);

        return redirect()->route('sales.ready-stock-products.index')->with('success', 'تم تحديث المنتج الجاهز بنجاح.');
    }

    public function importForm(Request $request): View
    {
        abort_unless($request->user()->can('materials.manage'), 403);

        return view('sales.ready-stock-products.import');
    }

    public function previewImport(Request $request, ReadyStockProductImportService $importer): RedirectResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $request->validate(['import_file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);
        $file = $request->file('import_file');
        $path = $file->store('ready-stock-imports', 'local');

        try {
            $preview = $importer->preview(Storage::disk('local')->path($path));
        } catch (SpreadsheetException|ValidationException $exception) {
            Storage::disk('local')->delete($path);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            throw ValidationException::withMessages(['import_file' => 'تعذر قراءة الملف. يرجى رفع ملف تصدير منتجات سلة بصيغة Excel أو CSV.']);
        }

        $previous = $request->session()->get('ready_stock_import');
        if ($previous) {
            Storage::disk('local')->delete($previous['path']);
        }
        $token = (string) Str::uuid();
        $request->session()->put('ready_stock_import', ['token' => $token, 'path' => $path, 'created_at' => time(), 'file_name' => $file->getClientOriginalName()]);

        return redirect()->route('sales.ready-stock-products.import-preview');
    }

    public function showImportPreview(Request $request, ReadyStockProductImportService $importer): View|RedirectResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $pending = $request->session()->get('ready_stock_import');
        if (! $pending || time() - $pending['created_at'] > 3600 || ! Storage::disk('local')->exists($pending['path'])) {
            return redirect()->route('sales.ready-stock-products.import')->with('error', 'انتهت صلاحية المعاينة. يرجى رفع الملف مجدداً.');
        }
        $preview = $importer->preview(Storage::disk('local')->path($pending['path']));
        $token = $pending['token'];
        $fileName = $pending['file_name'];

        return view('sales.ready-stock-products.import-preview', compact('preview', 'token', 'fileName'));
    }

    public function confirmImport(Request $request, ReadyStockProductImportService $importer): RedirectResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $request->validate(['token' => ['required', 'uuid'], 'selected_rows' => ['required', 'json']]);
        $selected = json_decode($request->input('selected_rows'), true);
        Validator::make(['selected_rows' => $selected], [
            'selected_rows' => ['required', 'array', 'min:1', 'max:5000'],
            'selected_rows.*' => ['required', 'integer', 'distinct', 'min:2'],
        ], ['selected_rows.required' => 'يرجى تحديد منتج واحد على الأقل.'])->validate();

        $pending = $request->session()->get('ready_stock_import');
        if (! $pending || ! hash_equals($pending['token'], $request->input('token')) || time() - $pending['created_at'] > 3600 || ! Storage::disk('local')->exists($pending['path'])) {
            throw ValidationException::withMessages(['import_file' => 'انتهت صلاحية المعاينة. يرجى رفع الملف مجدداً.']);
        }

        $counts = $importer->import(Storage::disk('local')->path($pending['path']), $selected);
        Storage::disk('local')->delete($pending['path']);
        $request->session()->forget('ready_stock_import');

        return redirect()->route('sales.ready-stock-products.index')
            ->with('success', "تمت إضافة {$counts['created']} منتج وتحديث {$counts['updated']} منتج. لم تتغير أرصدة المستودع.");
    }

    protected function form(Material $material): View
    {
        $units = UnitOfMeasure::active()->orderBy('name_ar')->get();
        $material->load('readyParent', 'readyOptions');

        return view('sales.ready-stock-products.form', compact('material', 'units'));
    }
}
