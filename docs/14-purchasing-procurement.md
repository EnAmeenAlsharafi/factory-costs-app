# Stage 12 — Purchasing & Procurement Workflow

## Overview
Stage 12 implements the complete purchasing and procurement workflow for the **Sadir Furniture Factory Production Management System**. It spans low-stock automated procurement planning, purchase requests, requests for quotation (RFQs), supplier quotation recording, matrix price comparison with unit normalization, purchase order lifecycle management, and seamless integration into Stage 5 raw material inventory receiving.

---

## Technical & Business Domain Architectural Rules
1. **Commercial Terms vs. Inventory Receiving**:
   - **Purchase Orders (POs)** represent commercial contractual agreements with suppliers. A PO does **NOT** increase physical inventory stock.
   - **Stock Addition**: Stock increases **ONLY** when a Stage 5 `MaterialReceipt` is created and posted into inventory movements and inventory lots.
2. **Cost Accounting & Price Variance Tracking**:
   - Inventory lots retain actual physical receipt unit costs (`unit_cost_base`).
   - Price variances ($\text{Actual Receipt Cost} - \text{Agreed PO Price}$) are calculated and reported via `PurchaseReceivingService::getPoPriceVarianceSummary()` without retroactively altering approved PO commercial terms.
3. **Transactional Over-Allocation Ceiling**:
   - **Purchase Orders**: Total ordered base quantity across PO lines linked to a `PurchaseRequestLine` cannot exceed requested quantity.
   - **Material Receipts**: Cumulative received base quantity across receipt lines linked to a `PurchaseOrderLine` cannot exceed ordered quantity without executive approval.
4. **Unit Conversions & Price Normalization**:
   - Quotes in commercial purchase units (e.g., Cartons, Bundles) are normalized to the material's base unit of measure for objective matrix comparison.

---

## Data Models & Schema Extension

| Entity / Model | Database Table | Key Responsibilities & Relations |
| :--- | :--- | :--- |
| `PurchaseRequest` | `purchase_requests` | Internal demand header (`PRQ-000001`). Statuses: `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `PARTIALLY_ORDERED`, `ORDERED`. |
| `PurchaseRequestLine` | `purchase_request_lines` | Material/Fabric demand lines with requested quantity, base unit, preferred supplier, and estimated cost. |
| `PurchaseRfq` | `purchase_rfqs` | Solicitations sent to candidate suppliers (`RFQ-000001`). |
| `PurchaseRfqSupplier` | `purchase_rfq_suppliers` | Pivot table tracking candidate suppliers for an RFQ. |
| `SupplierQuotation` | `supplier_quotations` | Commercial proposals received from suppliers (`SQT-000001`). |
| `SupplierQuotationLine` | `supplier_quotation_lines` | Quoted prices, purchase units, conversion factors, normalized unit costs, and lead times. |
| `PurchaseOrder` | `purchase_orders` | Binding commercial agreement (`PO-000001`). Statuses: `DRAFT`, `APPROVED`, `SENT`, `PARTIALLY_RECEIVED`, `RECEIVED`, `CLOSED`, `CANCELLED`. |
| `PurchaseOrderLine` | `purchase_order_lines` | Agreed item quantities, purchase units, conversion factors, base quantities, and unit prices. |
| `MaterialReceipt` (Ext) | `material_receipts` | Linked to `purchase_order_id` for Stage 5 receiving. |
| `MaterialReceiptLine` (Ext)| `material_receipt_lines` | Linked to `purchase_order_line_id` with `received_base_quantity` increments. |

---

## Core Services

### 1. `ProcurementPlanningService`
- Compiles raw material stock levels, open PR base quantities, and open PO incoming quantities.
- Identifies materials falling below `min_stock_level` or `reorder_point`.
- Recommends automated reorder quantities.

### 2. `PurchaseRequestService`
- Manages manual PR creation, submission, and multi-tier approval flows.
- Automated creation from low-stock planning matrices and production order shortages.
- Prevents over-ordering against approved request lines.

### 3. `SupplierQuotationService`
- RFQ generation for candidate suppliers.
- Quotation entry with conversion factor calculation.
- Normalizes unit prices to base unit for matrix comparison.

### 4. `PurchaseOrderService`
- Generates POs from approved PR lines or direct entry.
- Enforces transactional over-allocation protection against PR lines (`lockForUpdate`).
- Order approval, status transitions, and cancellation safeguards.

### 5. `PurchaseReceivingService`
- Pre-fills Stage 5 `MaterialReceipt` draft from approved PO lines.
- Enforces over-receipt ceiling.
- Updates PO line `received_base_quantity` and PO status (`PARTIALLY_RECEIVED` / `RECEIVED`).
- Generates PO price variance summary reports.

---

## Document Sequence Generators

| Document Type | Prefix Format | Example |
| :--- | :--- | :--- |
| Purchase Request | `PRQ-` | `PRQ-000001` |
| Request for Quotation | `RFQ-` | `RFQ-000001` |
| Supplier Quotation | `SQT-` | `SQT-000001` |
| Purchase Order | `PO-` | `PO-000001` |

---

## Permissions & Access Control

| Permission Name | Module / Area | Description |
| :--- | :--- | :--- |
| `purchasing.view` | Purchasing Core | Access to purchasing dashboard, planning, requests, quotes, and orders. |
| `purchasing.request.create` | Requests | Create and edit draft purchase requests. |
| `purchasing.request.approve` | Requests | Review and approve/reject purchase requests. |
| `purchasing.rfq.manage` | RFQs & Quotes | Issue RFQs and enter supplier quotations. |
| `purchasing.order.create` | Orders | Create draft purchase orders. |
| `purchasing.order.approve` | Orders | Approve binding purchase orders. |
| `purchasing.order.cancel` | Orders | Cancel purchase orders. |
| `purchasing.receive` | Receiving | Create and post PO material receiving receipts. |

---

## Verification & Automated Test Coverage
- **`PurchaseRequestTest`**: Verifies manual PR creation, low-stock automated PR generation, submission, and approval flows.
- **`SupplierQuotationComparisonTest`**: Tests unit price normalization across different purchase units (Cartons vs. Pieces) for matrix comparison.
- **`PurchaseOrderWorkflowTest`**: Validates PO creation, approval, and lockForUpdate over-allocation protection against PR lines.
- **`PurchaseReceivingIntegrationTest`**: Full end-to-end integration test spanning PR -> RFQ -> Quotation -> PO -> Partial Receipt -> Full Receipt -> Inventory Lot Creation -> Price Variance Reporting.
- **`PurchasingPermissionsTest`**: Enforces strict RBAC access control for all purchasing routes and actions.
