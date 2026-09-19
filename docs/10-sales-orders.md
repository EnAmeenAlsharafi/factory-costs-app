# Stage 8 — Quotations, Customer Orders, Order Lines, Order Changes & Production Approval

## 1. Overview & Architecture

Stage 8 establishes the commercial-to-production intake workflow for the Sadir Furniture Factory Production Management System. It cleanly separates commercial negotiation from manufacturing execution.

### Lifecycle Flow
```
[ Commercial Quotation (DRAFT -> APPROVED -> CONVERTED) ]
                     │
                     ▼ (Single Transaction Conversion)
[ Customer Order (DRAFT -> PENDING_PRODUCTION_REVIEW -> APPROVED_FOR_PRODUCTION) ]
                     │
                     ▼ (Production Manager Review: Reference Dims & Recipe Versioning)
[ Ready for Future Production Orders (Stage 9) ]
```

## 2. Key Business Rules & Design Principles

1. **Quotation to Order Conversion**:
   - Only `APPROVED` quotations can be converted into `CustomerOrder`.
   - Conversion occurs inside a single database transaction (`DB::transaction`).
   - Prevents duplicate conversion. Updates quotation status to `CONVERTED` and records timestamp.

2. **Commercial Request vs. Production Reference Dimensions**:
   - `requested_width_cm` & `requested_length_cm`: Commercial requirements from customer.
   - `reference_width_cm` & `reference_length_cm`: Technical factory production dimensions confirmed by Production Manager during review.

3. **Custom Design Lines**:
   - Supports lines with `custom_design = true` and `custom_design_name`.
   - Custom design lines do not require a pre-existing `ProductModel` or `ProductConfiguration`.

4. **Fabric Supplier, Material & Color Code Specifications**:
   - For all bed models (`requires_fabric_selection = true`) or custom design lines requiring fabric, Customer Service MUST select:
     - **Fabric Supplier** (`fabric_supplier_id`)
     - **Fabric Material** (`fabric_material_id` - restricted to `FABRIC` category materials)
     - **Fabric Color Number / Code** (`fabric_color_code` - e.g. `204`, `BEIGE-08`)
   - Backend validation verifies `fabric_supplier_id` is linked to `fabric_material_id` in `material_supplier` mapping (`المورد المحدد غير مرتبط بنوع القماش المختار.`).
   - Quote conversion to Customer Order preserves all three fabric parameters.
   - Production Orders snapshot `fabric_supplier_id` and `fabric_color_code` to guarantee historical integrity even if master catalogs change later.
   - Production Material Requirements automatically resolve generic BOM fabric items to the exact customer-selected `fabric_material_id` and color code snapshot.

5. **Post-Approval Change Audit Logging**:
   - Editing an order after it has been `APPROVED_FOR_PRODUCTION` logs an entry in `customer_order_changes` with `occurred_after_production_approval = true`.
   - Formats human-readable audit change descriptions (e.g. `Supplier A / Velvet / 204` -> `Supplier B / Velvet / 118`).
   - Resets order status back to `PENDING_PRODUCTION_REVIEW` to mandate re-inspection by Production Management before manufacturing.

6. **Stage 8 Boundaries & Anti-Scope**:
   - Does **NOT** create Production Orders (reserved for Stage 9).
   - Does **NOT** execute WIP routing or work center dispatching.
   - Does **NOT** perform inventory reservation or issuing.
   - Does **NOT** generate finished goods inventory or accounting tax invoices.

## 3. Database Schema Overview

- **`quotations`**: Header for commercial quotes (`quotation_number`, `customer_id`, `sales_channel_id`, `status`, `total_amount`, `valid_until`).
- **`quotation_lines`**: Itemized lines for quotes (`product_model_id`, `product_configuration_id`, `custom_design`, `requested_width_cm`, `requested_length_cm`, `fabric_supplier_id`, `fabric_material_id`, `fabric_color_code`, `unit_price`, `line_total`).
- **`customer_orders`**: Header for commercial orders (`order_number`, `quotation_id`, `customer_id`, `sales_channel_id`, `customer_reference`, `order_date`, `status`, `total_amount`, `production_approved_by_user_id`).
- **`customer_order_lines`**: Itemized lines for orders (`requested_width_cm`, `requested_length_cm`, `reference_width_cm`, `reference_length_cm`, `fabric_supplier_id`, `fabric_material_id`, `fabric_color_id`, `fabric_color_code`, `recipe_version_id`, `quantity`, `unit_price`, `line_total`).
- **`customer_order_changes`**: Audit log for header and line modifications (`field_name`, `old_value`, `new_value`, `requested_by_user_id`, `occurred_after_production_approval`).

## 4. Permissions Matrix

- `quotations.view`, `quotations.create`, `quotations.update`, `quotations.approve`: Commercial Sales roles.
- `orders.view`, `orders.create`, `orders.update`, `orders.cancel`: Commercial & Customer Service roles.
- `orders.review_production`, `orders.approve_production`: Production Manager / Factory Administration.
