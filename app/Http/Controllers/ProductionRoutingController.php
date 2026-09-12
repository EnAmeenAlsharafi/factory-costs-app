<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionRoutingRequest;
use App\Models\ProductionRouting;
use App\Models\ProductionRoutingOperation;
use App\Models\WorkCenter;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionRoutingController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view') && ! $request->user()->can('production.manage_routing'), 403);

        $query = ProductionRouting::withCount('operations');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('routing_code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $routings = $query->latest()->paginate(15)->withQueryString();

        return view('production.routings.index', compact('routings'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('production.manage_routing'), 403);

        $workCenters = WorkCenter::where('is_active', true)->orderBy('sort_order')->get();

        return view('production.routings.create', compact('workCenters'));
    }

    public function store(ProductionRoutingRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('production.manage_routing'), 403);

        DB::transaction(function () use ($request) {
            $code = DocumentNumberService::generateRoutingCode();

            $routing = ProductionRouting::create([
                'routing_code' => $code,
                'name_ar' => $request->name_ar,
                'description' => $request->description,
                'is_active' => true,
            ]);

            $opModels = [];
            foreach ($request->operations as $idx => $opData) {
                $op = ProductionRoutingOperation::create([
                    'production_routing_id' => $routing->id,
                    'work_center_id' => $opData['work_center_id'],
                    'operation_code' => $opData['operation_code'],
                    'name_ar' => $opData['name_ar'],
                    'sequence_number' => $opData['sequence_number'],
                    'branch_key' => $opData['branch_key'] ?? 'MAIN',
                    'is_parallel' => ! empty($opData['branch_key']) && $opData['branch_key'] !== 'MAIN',
                    'is_required' => true,
                ]);

                $opModels[$idx] = $op;
            }

            // Sync dependencies
            foreach ($request->operations as $idx => $opData) {
                if (! empty($opData['depends_on_indexes']) && is_array($opData['depends_on_indexes'])) {
                    $depIds = [];
                    foreach ($opData['depends_on_indexes'] as $depIdx) {
                        if (isset($opModels[$depIdx])) {
                            $depIds[] = $opModels[$depIdx]->id;
                        }
                    }
                    if (! empty($depIds)) {
                        $opModels[$idx]->dependencies()->sync($depIds);
                    }
                }
            }
        });

        return redirect()->route('production.routings.index')
            ->with('success', 'تم إنشاء مسار التصنيع بنجاح.');
    }

    public function show(Request $request, ProductionRouting $routing): View
    {
        abort_if(! $request->user()->can('production.view') && ! $request->user()->can('production.manage_routing'), 403);

        $routing->load(['operations.workCenter.department', 'operations.dependencies']);

        return view('production.routings.show', compact('routing'));
    }

    public function toggleStatus(Request $request, ProductionRouting $routing): RedirectResponse
    {
        abort_if(! $request->user()->can('production.manage_routing'), 403);

        $routing->is_active = ! $routing->is_active;
        $routing->save();

        return redirect()->back()->with('success', 'تم تحديث حالة مسار التصنيع.');
    }
}
