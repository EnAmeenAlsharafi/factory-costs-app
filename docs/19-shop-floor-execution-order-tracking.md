# Stage 16.5 — Shop Floor Execution & End-to-End Order Tracking

This document records the architecture, state machine, and UI delivered in Stage 16.5. It extends
Stage 9–11's manufacturing execution foundation (`docs/11-production-execution.md`) with structured
employee assignment, start/block/resume actions, a canonical append-only event log, a unified
order-journey timeline, and a derived end-to-end Customer Order status — without introducing a
second execution, dependency, or shortage engine.

## 1. Architecture

### 1.1 Smallest viable data-model extension
Stage 16.5 deliberately added only what the existing architecture lacked:

- **`production_operation_events`** — an append-only canonical audit + timeline source
  (`UPDATED_AT = null`, rows are never edited). This is **not** a second progress ledger:
  `production_operation_progress` (Stage 9) remains the quantity ledger; events record *why* and
  *who*, not *how much is left*.
- **`production_operation_blocks`** — a structured block lifecycle (reason code, note, the status to
  restore on resolve, optional links into the existing material-requirement/request tables — no
  second shortage engine).
- `production_order_operations.ready_at`, `.assigned_at`, `.assigned_by_user_id` — assignment and
  SLA timestamps on the existing operation row.
- `production_routing_operations.expected_minutes` — the SLA source for delay detection on the
  Production Control Board.
- `delivery_orders.assigned_at`, `.assigned_by_user_id` — assignment audit parity with operations.

No existing table was replaced or migrated away from. No new production-order, dependency, or
quantity-tracking table was created.

### 1.2 Services (single responsibility, no duplicated authority)

| Service | Responsibility |
|---|---|
| `ProductionEventService` | **Only** writer of `production_operation_events`. |
| `ProductionDependencyService` | **Only** authority for join-dependency math (`maxEligibleQuantity`, `evaluateReadiness`) — unchanged from Stage 9, now explicitly centralized. |
| `ProductionOrderService` | **Only** authority for the quantity ledger (`recordProgress`, `correctProgress`) and Production Order lifecycle (release/hold/resume/cancel). Unchanged behavior; now delegates dependency math and logs events. |
| `ProductionExecutionService` | Shop-floor façade: `start()`, `recordProgress()` / `complete()` (delegate to `ProductionOrderService`), `block()`, `resolveBlock()`. No independent quantity or dependency logic. |
| `ProductionAssignmentService` | `assign()` / `reassign()` (manager-initiated) and `claim()` (atomic compare-and-set, worker-initiated). |
| `AuthorizesOperationExecution` (trait) | Shared employee/department integrity check used by both execution and assignment services. |
| `OrderTrackingService` | Derives the Customer Order's operational status **on read** from production + delivery records. Never writes to `customer_orders.status`. |
| `OrderTimelineService` | Aggregates `production_operation_events` + posted `finished_goods_receipts` + `delivery_events` + Customer Order milestones into one chronological, de-duplicated list. No duplicate "mega event" table. |

**Controllers never contain execution, dependency, or progress logic** — every shop-floor action is
a thin call into one of the services above (`ProductionOperationController`,
`ProductionAssignmentController`, `ProductionMyTasksController`).

## 2. Operation state machine

Stage 16.5 adds exactly one new status, `ASSIGNED`, onto the existing machine; `WAITING` and
`CANCELLED` from early drafts were mapped onto the existing `PENDING` / `SKIPPED` values rather than
introducing synonyms.

```
PENDING → READY → ASSIGNED → IN_PROGRESS → PARTIALLY_COMPLETED → COMPLETED
                      ↑                           │
                      └─────────── BLOCKED ────────┘ (previous_status restored on resolve)
```

- **PENDING**: upstream join dependency not yet satisfied (`ProductionDependencyService::maxEligibleQuantity() == 0`).
- **READY**: eligible quantity > 0; unassigned and claimable, or assignable by a manager.
- **ASSIGNED**: has an `assigned_user_id` but work has not started.
- **IN_PROGRESS` / `PARTIALLY_COMPLETED`**: unchanged from Stage 9 — driven by the quantity ledger.
- **BLOCKED**: an active `ProductionOperationBlock` exists; resolving it restores the operation to
  `previous_status` (whatever it was blocked from — `IN_PROGRESS`, `PARTIALLY_COMPLETED`, `ASSIGNED`…).
- **COMPLETED** / **SKIPPED**: terminal.

Progress can still be recorded directly from `READY` or `ASSIGNED` (the legacy Stage 9 flow, used by
the department queue's "تسجيل التقدم" form) — `start()` is an optional, explicit step for workers who
want their claim/start moment captured on the timeline; it is not a hard gate in front of
`recordProgress()`.

## 3. Assignment

Two independent modes write to the same `assigned_user_id` / `assigned_at` / `assigned_by_user_id`
columns and are both logged to the event history (no separate assignment-history table — history
lives in the append-only log):

- **Mode A — manager assigns/reassigns** (`ProductionAssignmentService::assign()`,
  `production.assignment.manage`): used from the assignment board
  (`production.assignment.index`) and reusable from anywhere an operation and employee are in hand.
  Logs `ASSIGNED` or `REASSIGNED` (reassignment is detected when `assigned_user_id` changes).
- **Mode B — employee claims** (`ProductionAssignmentService::claim()`,
  `production.operation.claim`): an atomic compare-and-set
  (`UPDATE ... WHERE status = 'READY' AND assigned_user_id IS NULL`). When two employees race for the
  same task, the loser's `affected` row count is `0` and a `ValidationException` is thrown — exactly
  one employee wins. Logs `CLAIMED`.

Employee eligibility (both modes) is enforced by `AuthorizesOperationExecution`: the employee must be
active, hold the relevant permission, and either belong to the operation's work-center department or
be an administrator / `production_manager` (department override).

## 4. Execution queue & my-tasks

- **Department queue** (`production.queue.index`, existing Stage 9 screen) — unchanged tab semantics,
  now includes `ASSIGNED` and `BLOCKED` operations in "كل المهام المفتوحة" so claimed/blocked work
  never silently disappears. The task sheet gained claim / start / block quick actions and a
  "تم التنفيذ بالكامل" one-click completion, alongside the existing quantity-stepper progress form.
- **«مهامي»** (`production.my-tasks`) — a worker-scoped view: operations currently assigned to the
  signed-in employee (any active status, including `BLOCKED` for visibility) plus `READY`,
  unassigned operations in their own department they may claim. Reuses
  `OperationalDashboardService::operationsQueryFor()` for the same department-isolation rule as the
  department queue.
- **Assignment board** (`production.assignment.index`, `production.assignment.view`) — manager/admin
  screen: `READY` / `ASSIGNED` operations filterable by department, priority, product, and
  "unassigned only", each row with an inline assign/reassign form, alongside every department
  employee's current workload (active-task and ready-task counts — **no performance scores**, per
  spec §77).
- **Production Control Board** (`production.board.index`) — enhanced with attention counts
  (ready-unassigned, in-progress, blocked, delayed-past-SLA, open rework), a matrix of active
  Production Orders × department columns (bounded + paginated), and per-department blocked/delayed
  counts, on top of the existing department summary cards.

## 5. Block / resume lifecycle

`ProductionExecutionService::block()` creates a `ProductionOperationBlock` row (reason code —
`MATERIAL_SHORTAGE`, `SPEC_ISSUE`, `QUALITY_ISSUE`, `AWAITING_MANAGER`, `EQUIPMENT_FAILURE`, `OTHER` —
plus an optional free-text note and optional links into an existing material requirement/request),
flips the operation to `BLOCKED`, and logs `BLOCKED`. `resolveBlock()` closes that same row
(`resolved_by_user_id`, `resolved_at`, `resolution_note`, `is_active = false` — the row is never
deleted, so block duration and history survive) and restores the operation to the `previous_status`
captured at block time. No second shortage-tracking table was introduced; the optional
`production_material_requirement_id` / `production_material_request_id` links reuse the existing
Stage 10 material-request architecture.

A production-manager-only "حل العائق" action is available directly from the Production Order show
page for fast recovery without navigating to the queue.

## 6. Quality & rework integration

Stage 10's rework/quality-incident flow (`QualityIncidentService`) is unchanged in its own tables and
disposition logic. Stage 16.5 adds exactly two `ProductionEventService::record()` calls —
`REWORK_CREATED` when a `ProductionReworkAction` is created, `REWORK_COMPLETED` when it completes —
so rework appears natively on the unified timeline without a parallel rework-event mechanism. The
detected-department-vs-responsible-department distinction from Stage 10 is preserved untouched.

## 7. Unified order timeline

`OrderTimelineService` is a **read-time aggregation**, not a stored table:

- `forProductionOrder()` — a single bounded query over `production_operation_events` for one
  Production Order (used on the Production Order show page).
- `forCustomerOrder()` — merges Customer Order milestones (`created_at`,
  `production_reviewed_at`, `production_approved_at`), `production_operation_events` across every
  Production Order for the Customer Order, **posted-only** `finished_goods_receipts`, and
  `delivery_events`, into one chronological list (used on the Customer Order show page, and could
  back a role-filtered delivery-driver view via the `$role` parameter).

**Ordering note (worth knowing if you extend this service):** database `timestamp` columns commonly
store only second-level precision, so two events recorded a moment apart can carry an identical
`occurred_at`. Sorting on `occurred_at` alone can therefore silently reorder same-second events.
Every row carries an internal `seq` (the source record's auto-increment id, assigned in true
creation order) that breaks ties; `orderAndCap()` sorts on `(occurred_at, seq)` and strips `seq`
before returning. Keep this in mind if a new event source is added.

Each row is capped at a bounded `$limit` per source query (no N+1, no unbounded growth) and the
merged result is capped again to the most recent `$limit` events overall.

## 8. Derived Customer Order status

`OrderTrackingService::deriveStatus()` computes a presentation-only operational status —
`APPROVED_FOR_PRODUCTION → IN_PRODUCTION → PRODUCTION_BLOCKED → PRODUCTION_COMPLETED →
READY_FOR_DELIVERY → OUT_FOR_DELIVERY → DELIVERED → FULFILLED` (plus `DRAFT` /
`WAITING_PRODUCTION_REVIEW` / `RETURNED` / `CANCELLED`) — from the authoritative
`production_orders.status` and `delivery_orders.status` records. **`customer_orders.status` (the
sales/review lifecycle column) is never overwritten with a manufacturing or delivery step.** A
Customer Order is released to the shop floor the moment at least one of its Production Orders leaves
`DRAFT`/`READY_FOR_RELEASE` — from that point it reads as `IN_PRODUCTION` even before any progress is
recorded, since the work has genuinely started on the floor.

`summary()` returns the reusable payload behind `<x-order-tracking-summary :order="$order" />`:
status + label + badge class, a human current-stage string, `stages_completed` / `stages_total`
(production-order operation counts, excluding `SKIPPED`), `has_open_rework`, `is_blocked`, and
`last_update`. `productionProgress()` returns the ordered process-tracker rows for one Production
Order (parallel-branch aware, since it simply lists every operation with its own status/quantities).

## 9. Finished goods & delivery integration

No change to Stage 11's finished-goods or delivery logic. Completing the final routing operation
(packaging) drives `ProductionOrder.status` to `COMPLETED` exactly as before; it **never**
auto-creates a `FinishedGoodsReceipt` or a `DeliveryOrder` — both remain explicit, manual actions
(`FinishedGoodsController::storeReceipt`, `DeliveryOrderController::store`). `DeliveryOrderService`
already wrote `delivery_events` for every transition (ready/assign/dispatch/deliver/install/fail/
reschedule); Stage 16.5 only added `assigned_at` / `assigned_by_user_id` columns for assignment-audit
parity with operations, set in `DeliveryOrderService::assignDriver()`.

## 10. Permissions

Six permissions were added under the `production` module, resolved the same way as every existing
permission (`$user->can('x')` → `Gate::before` → `User::hasPermission()` → `Role::hasPermission()`):

| Permission | Granted to |
|---|---|
| `production.assignment.view` | `production_manager`, `production_worker` |
| `production.assignment.manage` | `production_manager` |
| `production.operation.start` | `production_manager`, `production_worker` |
| `production.operation.claim` | `production_manager`, `production_worker` |
| `production.operation.block` | `production_manager`, `production_worker` |
| `production.operation.unblock` | `production_manager` only |

Progress recording and completion deliberately reuse the existing `production.update_progress`
permission rather than adding `production.operation.progress` / `.complete` — there is one permission
for "touch the quantity ledger", not three. Every new route is gated at the controller with
`abort_if(! $user->can(...))`, matching the codebase's existing convention (no route-level
`permission:` middleware is used anywhere in this app); the services independently re-verify
authorization (`AuthorizesOperationExecution`), so a controller bug can never bypass the department/
active-employee check.

An employee acting entirely outside any production permission gets a hard `403` (route-level
`abort_if`); an employee with the permission but outside their department gets a soft, session-flashed
`authorization` validation error (service-level `ValidationException`) — the existing distinction from
Stage 9's `DepartmentPermissionsTest`, preserved for the new actions.

## 11. Concurrency

- **Claim** is race-safe by construction: an atomic `UPDATE ... WHERE status = 'READY' AND
  assigned_user_id IS NULL` means at most one of two simultaneous claimants can succeed; the loser
  gets a clean `ValidationException`, not a stale read.
- **Progress / start / block / resolve-block** all lock the operation row (`lockForUpdate()`) inside a
  transaction before mutating it, exactly as Stage 9's `recordProgress()` already did — Stage 16.5's
  new actions follow the same pattern rather than introducing a different locking strategy.
- **Quantities are integers** throughout (`required_quantity`, `completed_quantity`, etc.) — no float
  arithmetic anywhere in the dependency or progress engine.

## 12. Mobile / shop-floor UX

- RTL throughout (`<html lang="ar" dir="rtl">`), Cairo font, Bootstrap 5 + Alpine, consistent with the
  rest of the app.
- Codes and numbers (production order numbers, SKUs) are isolated with the existing `.ltr-isolate`
  CSS utility so they never visually reverse inside Arabic text.
- Status is always communicated with text + icon (`<x-status-badge>` / `StatusPresenter`), never color
  alone — the `ASSIGNED` operation status was added to `StatusPresenter`'s `operation` domain map to
  keep this true for the new status.
- Every new destructive or state-changing form (claim, start, block, resolve-block, complete) follows
  the existing `data-confirm` global form-safety pattern (`resources/js/app.js`) rather than inventing
  new JS; confirmations are shown only where the action is irreversible or easy to mis-click (full-
  remaining completion, blocking).
- Primary touch targets follow the existing `.task-card-actions .btn` ≥44px convention; the my-tasks
  and queue views share the same card/table dual-rendering pattern as Stage 9.
- Shop-floor-only views never load costing, receivables, or profitability data — none of Stage 16.5's
  new tables or services touch those domains, and the new views only ever query
  operation/assignment/block/event data.

## 13. Stage 14 reporting touchpoints

Stage 16.5 does not change any Stage 14 management report. The new `expected_minutes` /
`started_at` delay signal on the Production Control Board is a live operational view, not a report;
a future stage could promote "time-to-complete vs. SLA" into the Stage 14 manufacturing reports using
the same `production_operation_events` history this stage introduced, without any schema change.

## 14. Boundaries — explicitly out of scope

- **Salla / e-commerce sync** — untouched; this stage is purely internal shop-floor execution.
- **MRP / capacity planning** — `expected_minutes` is an SLA signal for delay *detection*, not a
  scheduling or capacity-planning engine.
- **QR / barcode scanning** — claim/start/complete are web-form actions; a future stage could bind a
  QR scan to the same `ProductionExecutionService` / `ProductionAssignmentService` methods used here
  without any service-layer change.
- **Stage 18** (mobile/responsive shell) already exists independently
  (`docs/18-mobile-responsive-operational-web-app.md`); Stage 16.5's views were built against its
  existing card/table responsive conventions rather than introducing a new layout system.
- **Supporting-employee crews** — only a single primary `assigned_user_id` exists per operation in
  this stage; a crew/co-assignee model is deferred.
