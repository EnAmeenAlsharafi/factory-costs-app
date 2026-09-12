# Stage 9 — Work Centers, Production Routing, Production Orders & WIP Execution

This document details the factory manufacturing execution foundation for the Sadir Furniture Factory Production Management System.

## Architecture Principles

### 1. Commercial vs. Manufacturing Execution
- **Customer Order**: Represents the commercial agreement with the customer (what was requested).
- **Production Order**: Represents the manufacturing release instructions for the factory floor (what must be built).
- **Snapshot Isolation**: When a Production Order is created and released, all approved manufacturing specifications (requested & reference dimensions, storage option, fabric material & color, recipe version, custom design info, and released quantity) are frozen in the Production Order record.
- **Order Change Handling**: Edits to Customer Orders after Production Order release do NOT silently overwrite active Production Orders. Instead, the system flags the Production Order with an `has_customer_order_changed` warning (`ORDER_CHANGE_PENDING`), allowing the Production Manager to manually review and issue revisions.

### 2. Production Release Rules
- Production Orders may ONLY be created from Customer Orders with status `APPROVED_FOR_PRODUCTION`.
- Standard product configurations must reference an approved `ManufacturingRecipeVersion` at the time of release.
- Custom designs (`is_custom_design = true`) do not require a pre-configured BOM recipe and can be released with custom operational notes.
- **Split Production Orders**: Multiple Production Orders can be created for a single Customer Order line, provided the cumulative `released_quantity` does not exceed the line's ordered quantity.

### 3. Work Centers & Parallel Routing Engine
- **Work Centers (`work_centers`)**: Map 1-to-1 to physical factory departments (`CARPENTRY`, `FOAM`, `UPHOLSTERY`, `BOX_PRODUCTION`, `BOX_PREPARATION`, `ASSEMBLY`, `PACKAGING`).
- **Production Routings (`production_routings`)**: Reusable master templates defining operations, sequence numbers, and parallel branch keys.
- **Parallel Stream Architecture**: Supports non-linear manufacturing workflows (e.g. Branch A: Carpentry → Foam → Upholstery vs. Branch B: Box Production → Box Preparation) that converge into join operations (Assembly → Packaging).
- **Operation Snapshotting**: When a Production Order is released, routing operations are cloned into `production_order_operations` to insulate active production from future master template edits.

### 4. Partial Progress & Join Dependency Quantity Limits
- **Incremental Progress Tracking**: Progress updates are logged in `production_operation_progress` with event types (`START`, `PROGRESS`, `COMPLETE`, `HOLD`, `RESUME`, `CORRECTION`).
- **Join Dependency Cap**: For operations with multiple upstream dependencies (e.g. Assembly depending on Upholstery AND Box Preparation), the maximum quantity available for Assembly completion is capped by:
  $$\text{Max Eligible Qty} = \min(\text{Upstream Branch Completed Quantities})$$
- **Final Completion Source**: Production Order `completed_quantity` and status (`PARTIALLY_COMPLETED` / `COMPLETED`) are driven strictly by the final routing operation (Packaging). Manual editing of Production Order completed quantity is disallowed.

### 5. Authorization & Department Queue Isolation
- Factory department workers (`production_worker`) can only view and update operations matching their assigned `department_id`.
- Production Managers and System Administrators retain full cross-department management capabilities.

---

## Controlled State Machines

### Production Order Statuses
- `DRAFT`: Newly created Production Order draft.
- `READY_FOR_RELEASE`: Pre-selected routing, awaiting release.
- `RELEASED`: Released to workshop floor, operations snapshot created.
- `IN_PROGRESS`: Workshop operations have started.
- `PARTIALLY_COMPLETED`: Partial quantities completed through final packaging.
- `COMPLETED`: 100% of released quantity completed final packaging.
- `ON_HOLD`: Temporarily paused by Production Manager with hold reason.
- `CANCELLED`: Cancelled with reason (restricted if progress exists).

### Operation Statuses
- `PENDING`: Awaiting upstream dependency completion.
- `READY`: Upstream dependencies satisfied, eligible for work.
- `IN_PROGRESS`: Work started, partial quantity completed.
- `PARTIALLY_COMPLETED`: Partial quantity finished.
- `COMPLETED`: Required quantity fully completed.
- `BLOCKED`: Blocked or paused.

---

## Anti-Scope Boundaries (Deferred to Stage 10+)
1. No automatic raw-material stock issuance or inventory deduction on release.
2. No WIP warehouse inventory ledger entries or financial WIP valuation.
3. No finished-goods inventory receipts.
4. No full Rework/Waste quality incident module.
5. No labor or machine costing allocations.
