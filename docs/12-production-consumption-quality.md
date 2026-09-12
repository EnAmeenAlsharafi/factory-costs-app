# Stage 10 — Production Material Requests, Actual Consumption, Waste, Rework & Quality Incidents

## 1. Overview & Purpose
Stage 10 builds the operational and analytical layer for material tracking, waste recording, quality management, rework, and variance reporting in the Sadir Furniture Factory Production Management System.

### Core Costing & Material Accounting Principles:
1. **Total Actual Material Cost Formula**:
   $$\text{Total Actual Material Cost} = \sum (\text{Issued Material Lots Cost}) - \sum (\text{Usable Returned Material Lots Cost})$$
2. **Waste Treatment**:
   - Waste represents lost/damaged material that is **NOT** returned to inventory.
   - Waste cost is an analytical breakdown/subset of total actual consumption and is **never added on top** of issues (preventing double counting).
3. **Rework & Remanufacture**:
   - When quality incidents occur, corrective rework actions or material re-issues can be tagged with `REWORK` or `REMANUFACTURE` request reasons.
   - Additional issues directly increment total actual consumption and variance against standard planned requirements.

---

## 2. Data Models & Database Architecture

### Key Models & Tables
- `ProductionMaterialRequirement` (`production_material_requirements`): Standard planned requirements derived from BOM & configuration.
- `ProductionMaterialRequest` (`production_material_requests`): Shop-floor material requisition header (`DRAFT`, `SUBMITTED`, `PARTIALLY_FULFILLED`, `FULFILLED`, `CANCELLED`).
- `ProductionMaterialRequestLine` (`production_material_request_lines`): Lines linking requirement, material, fabric color, requested quantity, approved quantity, and issued quantity.
- `ProductionWasteReason` (`production_waste_reasons`): Master table for waste classification codes (e.g., Cutting Scrap, Defective Material, Operator Error, Tooling Fault).
- `ProductionWasteRecord` (`production_waste_records`): Records actual waste quantity, unit cost, calculated cost, and associated waste reason.
- `QualityIncident` (`quality_incidents`): Quality non-conformance logs (`OPEN`, `UNDER_INVESTIGATION`, `RESOLVED`, `CLOSED`).
- `ProductionReworkAction` (`production_rework_actions`): Corrective action plan linked to a quality incident.

---

## 3. Workflow & Business Logic

### Material Requisition & Warehouse Fulfillment
1. **Requisition**:
   - Work center operators or department managers create a `ProductionMaterialRequest` for a `ProductionOrder` (optionally tied to a `ProductionOrderOperation`).
2. **Fulfillment**:
   - Raw material warehouse keeper fulfills requests by issuing specific inventory lots.
   - Posting the issue generates a `MaterialIssue` (linked to `production_material_request_id`), updates lot quantities, creates inventory movement records, and updates `issued_quantity` on the request lines.

### Actual Consumption & Cost Analysis
- Calculated dynamically via `ProductionCostService`:
  - Query all posted `MaterialIssueLine` records for the production order to get gross issues and cost.
  - Query all posted `MaterialReturnLine` records marked as usable (`is_usable = true`) to subtract returned values.
  - Calculate variance:
    $$\text{Quantity Variance} = \text{Actual Quantity} - \text{Planned Quantity}$$
    $$\text{Cost Variance} = \text{Actual Cost} - \text{Planned Cost}$$

### Quality Incidents & Rework
- Incidents capture defects during routing operation execution.
- Rework actions can recommend additional material issues (`REWORK` / `REMANUFACTURE`) or operational rework.
- Closing an incident verifies that rework actions have been executed.

---

## 4. Analytical Reports & Dashboards
- **Actual vs Planned Consumption**: Detailed table comparing standard planned quantities & costs against actual issued quantities & costs with variance percentages.
- **Waste & Scrap Analysis**: Breakdown of waste costs by waste reason, department, and material category.
- **Quality Incident Summary**: KPI summary of total incidents, severity levels, root causes, and resolution status.

---

## 5. Security & Authorization
- `view production costs`: Required to view cost values and monetary totals on production orders and report dashboards.
- `manage material requests`: Permission for creating and submitting material requisitions.
- `fulfill material requests`: Warehouse keeper permission for issuing lots against approved requests.
- `manage quality incidents`: Quality controller permission for logging, assigning, and resolving incidents.
- `manage waste records`: Permission to log production waste.
