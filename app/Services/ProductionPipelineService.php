<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Moves several orders through one production stage at once (approve, release, request materials, assign).
 *
 * Every item is processed on its own: one item that cannot move is reported and skipped,
 * it never rolls back or blocks the others.
 */
class ProductionPipelineService
{
    public function __construct(
        protected CustomerOrderService $customerOrderService,
        protected ProductionOrderService $productionOrderService,
        protected ProductionMaterialRequestService $materialRequestService,
        protected ProductionAssignmentService $assignmentService,
    ) {}

    /**
     * Approve sales orders for production.
     *
     * @param  array<int, int>  $orderIds
     * @return array{done: array<int, string>, skipped: array<string, string>}
     */
    public function approveOrders(array $orderIds, User $approver): array
    {
        $result = $this->emptyResult();

        foreach (CustomerOrder::whereIn('id', $orderIds)->get() as $order) {
            if ($order->status !== 'PENDING_PRODUCTION_REVIEW') {
                $result['skipped'][$order->order_number] = 'لم يعد بانتظار الاعتماد.';

                continue;
            }

            $this->attempt($result, $order->order_number, function () use ($order, $approver) {
                $this->customerOrderService->approveForProduction($order, $approver);
            });
        }

        return $result;
    }

    /**
     * Create (when needed) and release production orders on one routing.
     *
     * @param  array<int, int>  $lineIds  approved sales order lines that still have quantity to release
     * @param  array<int, int>  $productionOrderIds  production orders still in draft
     * @return array{done: array<int, string>, skipped: array<string, string>}
     */
    public function release(array $lineIds, array $productionOrderIds, ProductionRouting $routing, string $priority, User $user): array
    {
        $result = $this->emptyResult();

        foreach (CustomerOrderLine::with('customerOrder')->whereIn('id', $lineIds)->get() as $line) {
            $reference = $line->customerOrder->order_number.' / بند '.$line->item_number;

            $this->attempt($result, $reference, function () use ($line, $routing, $priority, $user) {
                $remaining = $this->remainingToRelease($line);
                if ($remaining <= 0) {
                    throw ValidationException::withMessages(['line' => 'تم إطلاق كامل الكمية مسبقاً.']);
                }

                $productionOrder = $this->productionOrderService->createFromOrderLine($line, [
                    'released_quantity' => $remaining,
                    'priority' => $priority,
                ]);
                $this->productionOrderService->releaseProductionOrder($productionOrder, $routing, $user);

                return $productionOrder->production_order_number;
            });
        }

        foreach (ProductionOrder::with('customerOrder')->whereIn('id', $productionOrderIds)->get() as $productionOrder) {
            $this->attempt($result, $productionOrder->production_order_number, function () use ($productionOrder, $routing, $priority, $user) {
                $productionOrder->update(['priority' => $priority]);
                $this->productionOrderService->releaseProductionOrder($productionOrder, $routing, $user);
            });
        }

        return $result;
    }

    /**
     * Raise and submit a material request per production order from its recipe requirements.
     *
     * @param  array<int, int>  $productionOrderIds
     * @return array{done: array<int, string>, skipped: array<string, string>}
     */
    public function requestMaterials(array $productionOrderIds, Warehouse $warehouse, User $user): array
    {
        $result = $this->emptyResult();

        foreach (ProductionOrder::visibleTo($user)->whereIn('id', $productionOrderIds)->get() as $productionOrder) {
            $this->attempt($result, $productionOrder->production_order_number, function () use ($productionOrder, $warehouse, $user) {
                if ($productionOrder->materialRequests()->where('status', '!=', 'REJECTED')->exists()) {
                    throw ValidationException::withMessages(['request' => 'لديه طلب مواد سابق.']);
                }

                if ($productionOrder->materialRequirements()->doesntExist()) {
                    $this->materialRequestService->createMaterialRequirementsFromRecipe($productionOrder);
                }

                $requirements = $productionOrder->materialRequirements()->get();
                if ($requirements->isEmpty()) {
                    throw ValidationException::withMessages(['recipe' => 'لا توجد وصفة تصنيع — أنشئ الطلب يدوياً.']);
                }

                $request = $this->materialRequestService->createRequest($productionOrder, $user, [
                    'warehouse_id' => $warehouse->id,
                    'request_date' => now()->toDateString(),
                    'lines' => $requirements->map(fn ($requirement): array => [
                        'production_material_requirement_id' => $requirement->id,
                        'material_id' => $requirement->material_id,
                        'base_unit_id' => $requirement->unit_id,
                        'requested_quantity' => (float) $requirement->total_planned_quantity,
                        'request_reason' => 'PLANNED_PRODUCTION',
                    ])->all(),
                ]);
                $this->materialRequestService->submitRequest($request);

                return $request->request_number;
            });
        }

        return $result;
    }

    /**
     * Assign operations to one employee, or spread them over each department's least-loaded employee.
     *
     * @param  array<int, int>  $operationIds
     * @return array{done: array<int, string>, skipped: array<string, string>}
     */
    public function assignOperations(array $operationIds, ?User $employee, User $actor): array
    {
        $result = $this->emptyResult();
        $operations = ProductionOrderOperation::visibleTo($actor)
            ->with(['productionOrder', 'workCenter'])
            ->whereIn('id', $operationIds)
            ->get();
        $workload = $employee ? collect() : $this->departmentWorkload($operations);

        foreach ($operations as $operation) {
            $reference = $operation->productionOrder->production_order_number.' — '.$operation->operation_name_snapshot;

            $this->attempt($result, $reference, function () use ($operation, $employee, $actor, $workload) {
                if ($employee) {
                    $this->assignmentService->assign($operation, $employee, $actor);

                    return $employee->name;
                }

                return $this->assignToLeastLoaded($operation, $actor, $workload);
            });
        }

        return $result;
    }

    public function remainingToRelease(CustomerOrderLine $line): int
    {
        $released = ProductionOrder::where('customer_order_line_id', $line->id)
            ->where('status', '!=', 'CANCELLED')
            ->sum('released_quantity');

        return (int) $line->quantity - (int) $released;
    }

    /**
     * Employees of each department touched by the operations, with their open task count.
     *
     * @param  Collection<int, ProductionOrderOperation>  $operations
     * @return Collection<int, Collection<int, User>>
     */
    private function departmentWorkload(Collection $operations): Collection
    {
        $departmentIds = $operations->pluck('workCenter.department_id')->filter()->unique();

        return User::query()
            ->where('is_active', true)
            ->whereIn('department_id', $departmentIds)
            ->withCount(['assignedOperations as open_tasks' => fn ($q) => $q->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])])
            ->get()
            ->groupBy('department_id');
    }

    /**
     * @param  Collection<int, Collection<int, User>>  $workload
     */
    private function assignToLeastLoaded(ProductionOrderOperation $operation, User $actor, Collection $workload): string
    {
        $candidates = ($workload->get($operation->workCenter?->department_id) ?? collect())->sortBy('open_tasks');

        foreach ($candidates as $candidate) {
            try {
                $this->assignmentService->assign($operation, $candidate, $actor);
                $candidate->open_tasks++;

                return $candidate->name;
            } catch (ValidationException) {
                continue;
            }
        }

        throw ValidationException::withMessages(['employee' => 'لا يوجد موظف مخوّل في قسم هذه العملية.']);
    }

    /**
     * @param  array{done: array<int, string>, skipped: array<string, string>}  $result
     */
    private function attempt(array &$result, string $reference, callable $action): void
    {
        try {
            // One transaction per item: a half-done item (e.g. created but not released) never stays behind.
            $outcome = DB::transaction(fn () => $action());
            $result['done'][] = is_string($outcome) ? "{$reference} ← {$outcome}" : $reference;
        } catch (ValidationException $e) {
            $result['skipped'][$reference] = collect($e->errors())->flatten()->first() ?? 'تعذّر التنفيذ.';
        } catch (QueryException $e) {
            report($e);
            $result['skipped'][$reference] = 'تعذّر الحفظ، حاول مرة أخرى.';
        } catch (Exception $e) {
            // Domain services raise plain exceptions with a ready Arabic message.
            $result['skipped'][$reference] = $e->getMessage() ?: 'تعذّر التنفيذ.';
        } catch (Throwable $e) {
            report($e);
            $result['skipped'][$reference] = 'تعذّر التنفيذ.';
        }
    }

    /**
     * @return array{done: array<int, string>, skipped: array<string, string>}
     */
    private function emptyResult(): array
    {
        return ['done' => [], 'skipped' => []];
    }
}
