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
    /**
     * Create a new quality incident for a production order.
     */
    public function createIncident(ProductionOrder $productionOrder, User $user, array $data): QualityIncident
    {
        return DB::transaction(function () use ($productionOrder, $user, $data) {
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

            return $rework;
        });
    }

    /**
     * Complete a rework action and resolve the incident if all reworks are completed.
     */
    public function completeReworkAction(ProductionReworkAction $rework, User $user): ProductionReworkAction
    {
        return DB::transaction(function () use ($rework) {
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
            }

            // Check if all rework actions for the incident are completed
            $incident = $rework->qualityIncident;
            $pendingReworks = $incident->reworkActions()->where('status', '!=', 'COMPLETED')->count();

            if ($pendingReworks === 0) {
                $incident->update([
                    'status' => 'RESOLVED',
                    'resolved_at' => now(),
                ]);
            }

            return $rework;
        });
    }
}
