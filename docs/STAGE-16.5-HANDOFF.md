# Stage 16.5 — Shop Floor Execution & End-to-End Order Tracking — HANDOFF

> Handoff for continuing Stage 16.5 in **Claude Code** running locally against this
> repo + XAMPP. The backend foundation is already written to disk; this document
> records the locked decisions, exactly what is done, and the remaining work in order.
> **Read this fully before writing code. Do NOT build a second production system.**

## 0. Environment & first action

- Laravel **13**, PHP **8.3**, PHPUnit **12**, Bootstrap 5 RTL + Alpine + Cairo, Vite.
- Tests run on **SQLite `:memory:`** (see `phpunit.xml`) — fast, isolated, do not touch data.
- Dev/live app DB = MySQL/MariaDB `sadir_factory` (XAMPP). App URL per `.env` (`http://localhost`).
- Follow `CLAUDE.md` / `.ai/rules` (Boost). Run `vendor/bin/pint --format agent` on changed PHP before finishing.
- **FIRST, confirm the baseline is green** (the refactor below touches `ProductionOrderService` and adds a migration):
  ```
  vendor\bin\phpunit
  ```
  Baseline before Stage 16.5 was 249 passing / 1197 assertions. The new migration must be present for the suite to pass (RefreshDatabase runs it). If red, fix before continuing.

## 1. Locked architectural decisions (already implemented — keep them)

1. **State machine** maps onto the EXISTING operation statuses; only `ASSIGNED` was added.
   - `WAITING` → **PENDING**, `CANCELLED` → **SKIPPED**, others as-is.
   - `production_order_operations.status`: `PENDING, READY, ASSIGNED, IN_PROGRESS, PARTIALLY_COMPLETED, COMPLETED, BLOCKED, SKIPPED` (plain string column; no enum migration needed).
   - Arabic labels + badges live on `ProductionOrderOperation::getStatusArabicAttribute()/getStatusBadgeClassAttribute()`.
2. **Smallest data-model extension** (spec §120): added two tables + columns only.
   - `production_operation_events` = **append-only canonical audit + timeline source** (NOT a second progress ledger; `production_operation_progress` stays as the quantity ledger).
   - `production_operation_blocks` = structured block lifecycle (previous_status restored on resolve; optional links to material requirement/request — no second shortage engine).
   - Columns on operations: `ready_at`, `assigned_at`, `assigned_by_user_id`. Column on routing operations: `expected_minutes` (SLA source).
   - **No** supporting-employees crew in V1 (primary `assigned_user_id` only). **No** separate assignment-history table (history lives in the event log).
3. **Unified timeline = Option A, dynamic aggregation** (spec §121). `OrderTimelineService` unions `production_operation_events` + `finished_goods_receipts (POSTED)` + `delivery_events` + customer-order milestones. No duplicate "mega event" table.
4. **Derived Customer Order status = computed, not stored** (spec §30). `OrderTrackingService::deriveStatus()`. The CO's own `status` column (sales/review lifecycle) is never overwritten with manufacturing steps.
5. **Priority** reuses existing `production_orders.priority` enum `NORMAL, URGENT, VIP` (do NOT add HIGH). Queue sort: VIP, URGENT, then oldest.
6. **Permissions** resolve via `$user->can('x')` (Gate::before → `hasPermission`). Existing middleware alias `permission:` + `CheckPermission`.

## 2. Already DONE (files on disk — do not recreate)

**Migration**
- `database/migrations/2026_10_23_000001_create_shop_floor_execution_tables.php`

**Models**
- NEW `app/Models/ProductionOperationEvent.php` (append-only; `label_arabic`)
- NEW `app/Models/ProductionOperationBlock.php` (`reason_arabic`, `duration_minutes`)
- EDITED `app/Models/ProductionOrderOperation.php` — fillable/casts for `ready_at,assigned_at,assigned_by_user_id`; relations `events(), blocks(), activeBlock(), assignedByUser()`; accessors `remaining_quantity, is_assigned`; `ASSIGNED` in badge/arabic; Arabic labels now match spec §4.
- EDITED `app/Models/CustomerOrder.php` — relations `productionOrders(), deliveryOrders(), finishedGoodsReceipts()`.
- EDITED `app/Models/ProductionOrder.php` — relations `operationEvents(), deliveryOrders()`.

**Services** (all container-resolved; constructor injection is safe)
- NEW `app/Services/Concerns/AuthorizesOperationExecution.php` — `assertEmployeeMayExecute($employee,$op,$permission)` + `hasDepartmentOverride()` (active + permission + same department as work center; admin/production_manager override). Spec §9.
- NEW `app/Services/ProductionEventService.php` — `record($op,$type,$attrs)` writes the event with user/department/work-center snapshots. **Only writer** of the event table.
- NEW `app/Services/ProductionDependencyService.php` — `maxEligibleQuantity($op)`, `evaluateReadiness($po)` (PENDING→READY when eligible qty > 0; sets `ready_at`; returns newly-ready ops). Centralized (spec §18) — preserves existing quantity-aware partial flow (§19).
- NEW `app/Services/ProductionAssignmentService.php` — `assign()`, `reassign()`, `claim()` (claim uses atomic compare-and-set UPDATE … WHERE status='READY' AND assigned_user_id IS NULL). Logs ASSIGNED/REASSIGNED/CLAIMED.
- NEW `app/Services/ProductionExecutionService.php` — `start()` (lock, idempotent, READY/ASSIGNED→IN_PROGRESS, claims if unassigned, STARTED event), `recordProgress()`/`complete()` (delegate to ProductionOrderService), `block()` (creates block + BLOCKED event), `resolveBlock()` (resolves active block, restores previous_status, BLOCK_RESOLVED event).
- NEW `app/Services/OrderTrackingService.php` — `deriveStatus()`, `statusLabel()/statusBadgeClass()`, `summary($co)` (status, current stage, stages X/Y, open-rework, blocked, last update), `productionProgress($po)` (process-tracker rows).
- NEW `app/Services/OrderTimelineService.php` — `forCustomerOrder($co,$limit=50,$role=null)`, `forProductionOrder($po)`.
- EDITED `app/Services/ProductionOrderService.php` — constructor now injects `ProductionDependencyService` + `ProductionEventService`; `releaseProductionOrder()` sets `ready_at` + logs READY; `recordProgress()`/`correctProgress()` log STARTED/PROGRESS_RECORDED/COMPLETED and promote+log downstream READY; dependency math delegated. **Behavior preserved** for existing tests; over-progress message now "لا يمكن تسجيل كمية أكبر من المتبقي".

**Permissions**
- EDITED `database/seeders/RoleAndPermissionSeeder.php` — added `production.assignment.view`, `production.assignment.manage`, `production.operation.start`, `production.operation.claim`, `production.operation.block`, `production.operation.unblock`. Granted: production_manager = all six; production_worker = view + start + claim + block. (No `production.operation.progress/complete` — reuse existing `production.update_progress`.)

## 3. REMAINING work (in order)

### A. Controllers + routes (gate every action with `permission:` middleware or `can()`)
Reuse existing controllers where they exist — **extend, don't replace**. Existing: `DepartmentQueueController` (queue + progress/correct), `ProductionBoardController` (board), `ProductionOrderController` (show), `DeliveryOrderController` (assign/dispatch/complete/install/my-tasks), `ProductionReworkController`, `QualityIncidentController`.
1. **Operation actions** — add to a controller (extend `DepartmentQueueController` or new `ProductionOperationController`): `start`, `complete`, `block`, `resolveBlock`, `claim` → call `ProductionExecutionService` / `ProductionAssignmentService`. Routes under `production/operations/{operation}/{action}` (POST). Return Arabic flash messages from the services' ValidationException.
2. **Assignment** — manager `assign`/`reassign` → `ProductionAssignmentService`. On the board or a dedicated assignment screen (spec §33): list READY ops, filter department/product/priority/unassigned, show employee workload (active+ready counts; NO scores §77).
3. **Employee «مهامي» (production)** — new `MyTasksController@index` + route `production.my-tasks`. Show the signed-in worker's ASSIGNED/IN_PROGRESS/PARTIALLY_COMPLETED ops + claimable READY ops in their department (reuse `OperationalDashboardService::operationsQueryFor()` isolation). Mobile-first.
4. **Production Control Board** (`ProductionBoardController`) — enhance to the matrix (PO rows × department/stage columns) with attention counts (ready-unassigned, in-progress, blocked, delayed, rework §129), filters, SLA/delay highlighting (§39/§40 via `expected_minutes` + `started_at`/`ready_at`). Bounded to active work + pagination (§71).
5. **Production Order show** — routing-progress table (dept, status, assignee, req/done/remaining, started/completed, block reason, rework badge), operational exceptions, FG/delivery downstream, and `OrderTimelineService::forProductionOrder()`.
6. **Customer Order show** (`CustomerOrderController@show` + `sales/orders/show`) — reusable **`<x-order-tracking-summary :order="$order"/>`** component fed by `OrderTrackingService::summary()` + `OrderTimelineService::forCustomerOrder()`. Answers "وين وصل الطلب؟" without opening the PO (spec §57, §110, §130).
7. **Delivery** — verify `delivery_orders` has `assigned_at`/`assigned_by` (spec §53); migration currently has only `assigned_user_id`. If missing and needed, add via a small migration and set them in `DeliveryOrderService::assign()`. Ensure `DeliveryOrderService` writes `delivery_events` for ready/assigned/dispatch/deliver/install/fail/reschedule so the unified timeline shows delivery (§56). Delivery «مهامي» already exists at `delivery.orders.my-tasks`.
8. **Rework/Quality event logging** — in the rework create/complete flow (`ProductionReworkController` / `QualityIncidentService`), call `ProductionEventService::record($op,'REWORK_CREATED'|'REWORK_COMPLETED', ...)` so rework appears on the timeline (spec §24/§25/§27). Preserve detected≠responsible department (already in `quality_incidents`).

### B. Views (Bootstrap 5 RTL, Cairo, Alpine; `<bdi>` for codes; primary touch targets ≥44px; status text+color not color-only)
- `production/board` (desktop matrix + mobile adaptive PO-card → operation status list, §83)
- `production/my-tasks` (worker task cards §45 + detail §46 + action modals: progress, block w/ reason select §20)
- enhance `production/queue/index` — add state-based action buttons (استلام المهمة / بدء العمل / تسجيل التقدم / تم التنفيذ / يوجد عائق)
- enhance `production/orders/show`
- `components/order-tracking-summary` + process tracker (§58, parallel-aware; `6 / 10` partial display §60; `تقدم المراحل` X/Y §59/§133; `إعادة عمل مفتوحة` badge §134)
- tracking section on `sales/orders/show`
- confirmation prompts for: complete, block, reassign, delivery complete (§87). Empty states §140.

### C. Mandatory automated tests (spec §90–104) → `tests/Feature/Production/`
Follow existing setup patterns in `tests/Feature/Production/ParallelDependencyTest.php` and `ProductionCompletionTest.php` (they build CO→approve→PO→release). Use `app(Service::class)`. Seed roles via `RoleAndPermissionSeeder`.
- §90 assign, §91 claim-race (exactly one wins), §92 start (+unauthorized rejected), §93 dependency, §94 parallel deps, §95 partial progress, §96 over-progress rejected, §97 concurrent progress no-overshoot, §98 block, §99 resume restores state, §100 rework preserves history, §101 packaging→FG (existing Stage 11 logic, no auto-deliver), §102 derived order status transitions, §103 timeline chronological + no duplicates, §104 permissions matrix.

### D. QA seeder (dev only — do NOT pollute production seed) → `database/seeders/ShopFloorQaSeeder.php`
Covers: linear flow, parallel flow, partial op, blocked op, rework case, multiple employees/departments, one delivery assignment, one delivered+installed order. Employee QA accounts per department (carpenter/foam/upholstery/assembly-packaging/manager/delivery) — reuse existing users where possible; no production default passwords (§88/§89).

### E. Docs → `docs/19-shop-floor-execution-order-tracking.md`
Architecture, state machine, assignment, queue, execution, partial progress, dependencies, block lifecycle, quality/rework, unified timeline, derived status, FG integration, delivery integration, permissions, concurrency, mobile UX, Stage 14 reporting touchpoints, future Salla/MRP/QR/Stage 18 boundaries.

### F. Verification (spec §146/§147/§114/§148) then Final Report (§151) + GO decision (§152)
```
vendor\bin\phpunit
vendor\bin\pint --format agent
npm run build
php artisan view:cache
git diff --check
```
- Review `storage/logs/laravel.log` (no new exceptions).
- Migration safety on an **isolated** DB (not the dev DB): fresh → rollback → fresh (MariaDB 10.4). Do not destroy dev data.
- Browser matrix (§148) desktop/tablet/mobile incl. 390×844 worker journey; console clean (§111).
- Run the mandatory end-to-end journey (§150): CO → approve → release → carpentry claim/start/complete → foam → box (parallel) → upholstery → assembly → packaging → FG receive → delivery assign → deliver → install → CO shows **مكتمل تشغيلياً** and the full timeline.

## 4. Traps to avoid
- Do NOT reimplement dependency/progress logic in controllers — call the services (spec §18, §58, §117).
- Do NOT write events from Blade (§62). `ProductionEventService` is the only event writer.
- Keep Production Order / operation codes LTR with `<bdi>` (§85).
- Shop-floor users must not see costing/receivables/profitability (§138) — none of it is in these tables, keep it out of the worker views.
- Quantities are integers/DECIMAL — no float arithmetic (§116).
- Historical safety: events/blocks must survive employee/department/routing changes (snapshots stored; FKs nullOnDelete where appropriate §65–69).
