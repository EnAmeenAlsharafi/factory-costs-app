# Stage 5 — Inventory, Receiving, Lots, Issues, Returns & Stock Balance

## Overview

Stage 5 introduces the core transaction-driven material inventory ledger for the **Sadir Furniture Factory Production Management System**.

This module tracks real physical movements, preserves historical purchase unit costs on inventory lots, prevents negative stock balances, and enforces immutability on posted inventory documents.

---

## Key Concepts & Architecture Decisions

1. **Transaction-Derived Inventory Balances (Rule #63)**
   - Stock balances are calculated as `SUM(IN) - SUM(OUT)` from `inventory_movements`.
   - Balances are NEVER stored as editable master fields.

2. **Historical Unit Cost & Inventory Lots (Rule #64 & #65)**
   - Every material receipt creates unique `inventory_lots` with unit costs per base unit locked at receipt time.
   - Subsequent price changes or vendor invoice adjustments do not mutate historical lot costs.

3. **Strict Prevention of Negative Inventory (Rule #66)**
   - Material issues and negative adjustments require available lot stock.
   - Posting executes within an atomic database transaction utilizing SELECT ... FOR UPDATE row locking (`lockForUpdate()`) to prevent concurrent over-issuing.

4. **Document Lifecycle & Immutability (Rule #67)**
   - All receipts, issues, returns, and adjustments follow a strict `DRAFT` → `POSTED` status lifecycle.
   - Posted documents cannot be edited or deleted. Corrective action must occur through returns or adjustments.

5. **Restricted Inventory Adjustments (Rule #68)**
   - Creating or posting stock adjustments requires the `inventory.adjust` permission, restricted exclusively to System Administrators.

---

## Database Schema & Models

1. **Warehouses (`warehouses`)**
   - `name_ar`, `name_en`, `code`, `is_active`.
   - Active raw-material warehouse: `RAW_MATERIALS`.
   - Reserved future warehouses: `WIP` (Work-In-Progress, active=false), `FINISHED_GOODS` (Finished Goods, active=false).

2. **Material Receipts (`material_receipts` & `material_receipt_lines`)**
   - Headers: `receipt_number` (`REC-YYYY-XXXXXX`), `supplier_id`, `warehouse_id`, `created_by_user_id`, `received_by_user_id`, `receipt_date`, `status` (`DRAFT`/`POSTED`), `supplier_reference`, `notes`.
   - Lines: `material_id`, `fabric_color_id`, `purchase_unit_id`, `base_unit_id`, `quantity_received`, `conversion_factor`, `base_quantity`, `unit_cost_purchase`, `unit_cost_base`, `total_cost`, `lot_reference`.

3. **Inventory Lots (`inventory_lots`)**
   - `lot_code` (`LOT-XXXXXX`), `material_id`, `fabric_color_id`, `supplier_id`, `warehouse_id`, `receipt_line_id`, `received_date`, `original_quantity`, `remaining_quantity`, `base_unit_id`, `unit_cost`, `status` (`ACTIVE`/`EXHAUSTED`/`EXPIRED`), `supplier_lot_reference`.

4. **Inventory Movements Ledger (`inventory_movements`)**
   - `movement_number` (`MOV-YYYY-XXXXXX`), `movement_type` (`RECEIPT`, `ISSUE`, `RETURN`, `ADJUSTMENT_IN`, `ADJUSTMENT_OUT`, `OPENING_BALANCE`), `direction` (`IN`/`OUT`), `material_id`, `fabric_color_id`, `warehouse_id`, `inventory_lot_id`, `quantity`, `unit_id`, `unit_cost`, `total_cost`, `reference_type`, `reference_id`, `occurred_at`, `performed_by_user_id`, `notes`.

5. **Material Issues (`material_issues` & `material_issue_lines`)**
   - Headers: `issue_number` (`ISS-YYYY-XXXXXX`), `warehouse_id`, `department_id`, `created_by_user_id`, `issued_by_user_id`, `issue_date`, `status` (`DRAFT`/`POSTED`).
   - Lines: `inventory_lot_id`, `material_id`, `fabric_color_id`, `base_unit_id`, `requested_quantity`, `issued_quantity`, `unit_cost`, `total_cost`.

6. **Material Returns (`material_returns` & `material_return_lines`)**
   - Headers: `return_number` (`RET-YYYY-XXXXXX`), `warehouse_id`, `department_id`, `created_by_user_id`, `received_by_user_id`, `return_date`, `status` (`DRAFT`/`POSTED`).
   - Lines: `material_id`, `fabric_color_id`, `inventory_lot_id`, `original_issue_line_id`, `returned_quantity`, `base_unit_id`, `unit_cost`, `total_cost`, `notes`.

7. **Inventory Adjustments (`inventory_adjustments`, `inventory_adjustment_lines`, `inventory_adjustment_reasons`)**
   - Headers: `adjustment_number` (`ADJ-YYYY-XXXXXX`), `warehouse_id`, `reason_id`, `created_by_user_id`, `adjusted_by_user_id`, `adjustment_date`, `status`.
   - Lines: `adjustment_type` (`ADJUSTMENT_IN`/`ADJUSTMENT_OUT`), `inventory_lot_id`, `material_id`, `fabric_color_id`, `base_unit_id`, `quantity`, `unit_cost`, `total_cost`.

---

## Key Services

- **`App\Services\DocumentNumberService`**: Sequential document code generator (`REC-`, `ISS-`, `RET-`, `ADJ-`, `LOT-`, `MOV-`).
- **`App\Services\InventoryService`**:
  - `postReceipt()`: Creates inventory lots, posts receipt movements, updates receipt status.
  - `postIssue()`: Locks lot rows, validates against remaining quantity, deducts lot balances, posts issue movements.
  - `postReturn()`: Restores lot balances, validates cumulative return limits against original issue lines, posts return movements.
  - `postAdjustment()`: Handles positive (opening balance / increase) and negative (decrease) stock adjustments. Creates new `inventory_lots` and `OPENING_BALANCE` movements when posting opening stock.
  - `calculateMaterialStockBalance()`: Returns net ledger balance (`IN - OUT`).
  - `calculateMaterialValuation()`: Returns current inventory value from remaining active lots.
  - `reconcileLotBalance()`: Verifies cached lot remaining quantity against movement history.

---

## Authorization & Permissions

- `inventory.view`: View stock balances, lot register, movements ledger, and posted documents.
- `inventory.receipt`: Create, edit, and post material receipts.
- `inventory.issue`: Create, edit, and post material issues.
- `inventory.return`: Create, edit, and post material returns.
- `inventory.adjust`: Create, edit, and post inventory adjustments (Admin only).

---

## Verification & Test Suite

- Feature tests in `tests/Feature/Inventory/`:
  - `InventoryReceiptTest.php`
  - `InventoryIssueTest.php`
  - `InventoryReturnTest.php` (Multi-line return & cumulative return limit tests)
  - `InventoryAdjustmentTest.php` (Admin authorization & opening balance lot/movement creation tests)
  - `InventoryBalanceAndReconciliationTest.php` (Full lifecycle, conversion snapshot immutability, lot reconciliation tests).
- Total tests passing: 71/71 (296 assertions).
