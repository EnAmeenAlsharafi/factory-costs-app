<?php

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\QualityIncident;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class QualityIncidentService
{
    public function __construct(protected ProductionEventService $events) {}

    /**
     * Create a new quality incident for a production order.
     */
    public function createIncident(ProductionOrder $productionOrder, User $user, array $data): QualityIncident
    {
        return DB::transaction(function () use ($productionOrder, $user, $data) {
            if (! empty($data['production_order_operation_id'])) {
                $duplicate = QualityIncident::where('production_order_operation_id', $data['production_order_operation_id'])
                    ->where('incident_type', $data['incident_type'])
                    ->whereNotIn('status', ['RESOLVED', 'CANCELLED'])
                    ->first();

                if ($duplicate) {
                    throw new Exception("توجد بالفعل حالة جودة مفتوحة لنفس العملية ونفس نوع العيب [{$duplicate->incident_number}]. يرجى تحديث البلاغ الحالي بدلاً من إنشاء بلاغ جديد.");
                }
            }

            $incidentNumber = DocumentNumberService::generateIncidentNumber();

            $incident = QualityIncident::create([
                'incident_number' => $incidentNumber,
                'production_order_id' => $productionOrder->id,
                'production_order_operation_id' => $data['production_order_operation_id'] ?? null,
                'affected_quantity' => $data['affected_quantity'] ?? 1,
                'detected_department_id' => $data['detected_department_id'] ?? $user->department_id,
                'responsible_department_id' => $data['responsible_department_id'] ?? null,
                'incident_type' => $data['incident_type'],
                'description' => $data['description'],
                'severity' => $data['severity'] ?? 'MEDIUM',
                'disposition' => $data['disposition'] ?? null,
                'status' => 'OPEN',
                'detected_by_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['disposition'])) {
                $this->setDisposition($incident, $user, $data['disposition'], $data['disposition_notes'] ?? null);
            }

            return $incident;
        });
    }

    /**
     * Assign a disposition decision to a quality incident.
     */
    public function setDisposition(QualityIncident $incident, User $user, string $disposition, ?string $notes = null): QualityIncident
    {
        return DB::transaction(function () use ($incident, $user, $disposition, $notes) {
            $validDispositions = ['REPAIR', 'REWORK', 'REMANUFACTURE', 'SCRAP', 'ACCEPT_AS_IS'];
            if (! in_array($disposition, $validDispositions, true)) {
                throw new Exception('قرار المعالجة غير صالح.');
            }

            $incident->update([
                'disposition' => $disposition,
                'decided_by_user_id' => $user->id,
                'decision_at' => now(),
                'status' => match ($disposition) {
                    'ACCEPT_AS_IS' => 'RESOLVED',
                    default => 'ACTION_REQUIRED',
                },
                'resolved_at' => ($disposition === 'ACCEPT_AS_IS') ? now() : $incident->resolved_at,
                'notes' => $notes ? trim(($incident->notes ?? '')."\n".$notes) : $incident->notes,
            ]);

            if ($incident->status === 'RESOLVED') {
                $this->releaseProductionOrderAfterResolution($incident, $user);
            }

            // If disposition requires rework or repair, create automatic rework action if none exists
            if (in_array($disposition, ['REPAIR', 'REWORK', 'REMANUFACTURE'], true)) {
                if ($incident->reworkActions()->count() === 0) {
                    $this->createReworkAction($incident, $user, [
                        'action_type' => $disposition,
                        'quantity' => $incident->affected_quantity,
                        'assigned_department_id' => $incident->responsible_department_id ?? $incident->detected_department_id,
                        'source_operation_id' => $incident->production_order_operation_id,
                        'target_operation_id' => $incident->production_order_operation_id,
                        'notes' => "إجراء تلقائي بناء على قرار المعالجة: {$disposition}",
                    ]);
                }
            }

            return $incident;
        });
    }

    /**
     * Create a rework action for a quality incident.
     */
    public function createReworkAction(QualityIncident $incident, User $user, array $data): ProductionReworkAction
    {
        return DB::transaction(function () use ($incident, $user, $data) {
            $reworkNumber = DocumentNumberService::generateReworkNumber();

            $rework = ProductionReworkAction::create([
                'rework_number' => $reworkNumber,
                'quality_incident_id' => $incident->id,
                'production_order_id' => $incident->production_order_id,
                'source_operation_id' => $data['source_operation_id'] ?? $incident->production_order_operation_id,
                'target_operation_id' => $data['target_operation_id'] ?? $incident->production_order_operation_id,
                'action_type' => $data['action_type'] ?? $incident->disposition ?? 'REWORK',
                'quantity' => $data['quantity'] ?? $incident->affected_quantity,
                'status' => 'PENDING',
                'assigned_department_id' => $data['assigned_department_id'] ?? $incident->responsible_department_id,
                'authorized_by_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Optionally block the source operation if severe
            if (! empty($data['block_operation']) && $rework->source_operation_id) {
                $op = ProductionOrderOperation::find($rework->source_operation_id);
                if ($op && in_array($op->status, ['READY', 'IN_PROGRESS'], true)) {
                    $op->update(['status' => 'BLOCKED']);
                }
            }

            $incident->update(['status' => 'ACTION_REQUIRED']);

            if ($rework->source_operation_id) {
                $sourceOp = ProductionOrderOperation::find($rework->source_operation_id);
                if ($sourceOp) {
                    $this->events->record($sourceOp, 'REWORK_CREATED', [
                        'note' => $rework->notes,
                        'user' => $user,
                        'meta' => ['rework_id' => $rework->id, 'rework_number' => $rework->rework_number, 'quantity' => $rework->quantity],
                    ]);
                }
            }

            return $rework;
        });
    }

    /**
     * Complete a rework action and resolve the incident if all reworks are completed.
     */
    public function completeReworkAction(ProductionReworkAction $rework, User $user): ProductionReworkAction
    {
        return DB::transaction(function () use ($rework, $user) {
            if ($rework->status === 'COMPLETED') {
                return $rework;
            }

            $rework->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Unblock operation if blocked
            if ($rework->source_operation_id) {
                $op = ProductionOrderOperation::find($rework->source_operation_id);
                if ($op && $op->status === 'BLOCKED') {
                    $op->update(['status' => 'IN_PROGRESS']);
                }

                if ($op) {
                    $this->events->record($op, 'REWORK_COMPLETED', [
                        'note' => $rework->notes,
                        'user' => $user,
                        'meta' => ['rework_id' => $rework->id, 'rework_number' => $rework->rework_number],
                    ]);
                }
            }

            // Check if all rework actions for the incident are completed
            $incident = $rework->qualityIncident;
            $pendingReworks = $incident->reworkActions()->where('status', '!=', 'COMPLETED')->count();

            if ($pendingReworks === 0) {
                $incident->update([
                    'status' => 'RESOLVED',
                    'resolved_at' => now(),
                ]);
                $this->releaseProductionOrderAfterResolution($incident, $user);
            }

            return $rework;
        });
    }

    /**
     * An open incident keeps its production order short of COMPLETED. Once resolved, re-evaluate the
     * order so a fully packaged order does not stay stuck in production.
     */
    protected function releaseProductionOrderAfterResolution(QualityIncident $incident, User $user): void
    {
        if ($incident->productionOrder) {
            app(ProductionOrderService::class)->reevaluate($incident->productionOrder, $user);
        }
    }
}
