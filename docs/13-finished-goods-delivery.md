# Stage 11: Finished Goods Handover, Delivery & Installation Workflow

## Overview & Architecture

Stage 11 establishes the manufacturing execution handover to final delivery and installation at the customer's site. It connects Stage 9/10 production completions to Stage 5 customer sales orders through a dedicated Finished Goods Ledger and transactional Delivery management.

```
+--------------------------+       +-------------------------------+       +-------------------------+
| Production Order (Stage 9)| ----> | Finished Goods Receipt (IN)   | ----> | Finished Goods Ledger   |
+--------------------------+       +-------------------------------+       +-------------------------+
                                                                                       |
                                                                                       v
+--------------------------+       +-------------------------------+       +-------------------------+
| Installation Completed   | <---- | Delivery Order (OUT Dispatch) | <---- | Delivery Address & Driver|
+--------------------------+       +-------------------------------+       +-------------------------+
            |
            v
+--------------------------+
| Customer Return & Quality|
+--------------------------+
```

---

## 1. Core Principles & Business Rules

1. **Finished Goods Ledger Isolation**:
   - Finished goods are tracked separately in `finished_goods_movements` table.
   - Movements have directions (`IN` / `OUT`) and types:
     - `PRODUCTION_RECEIPT` (IN): Inventory entry from completed production.
     - `DELIVERY_DISPATCH` (OUT): Inventory physical dispatch when delivery order goes `OUT_FOR_DELIVERY`.
     - `DELIVERY_RETURN` (IN): Inventory return when delivery fails or is rescheduled back to factory.
     - `CUSTOMER_RETURN` (IN): Inventory entry when post-delivery returned goods are received back at factory.
   - Available finished stock is computed dynamically: $\sum \text{IN} - \sum \text{OUT}$.

2. **Physical Completion & Handover Ceiling**:
   - `FinishedGoodsReceipt` quantity cannot exceed physical `completed_quantity` of `ProductionOrder`.
   - Prevents receiving unproduced or ghost items into stock.

3. **Address Snapshots & Driver Assignment**:
   - `DeliveryOrder` snapshots customer name, phone, city, district, and detailed address at creation.
   - Ensures delivery details remain historically immutable even if customer profile changes.
   - Assigns responsible driver/technician (`assigned_user_id`) with a mobile-optimized task queue ("مهامي").

4. **Transactional Dispatch & Stock Locking**:
   - Physical finished goods stock is deducted (`DELIVERY_DISPATCH` OUT movement) at the exact moment status transitions to `OUT_FOR_DELIVERY`.
   - Prevents double-allocation or shipping non-existent inventory.

5. **Failed Deliveries & Customer Returns**:
   - Failed or rescheduled deliveries return locked stock via `DELIVERY_RETURN` IN movement.
   - Post-delivery returns (`CustomerReturn`) are capped at net delivered quantity ($\text{Delivered} - \text{Accepted Returns}$).
   - Customer returns can be received at factory (`CUSTOMER_RETURN` IN movement) and linked directly to Stage 10 `QualityIncident` records.

---

## 2. Database Schema

### `finished_goods_receipts`
- `id`, `receipt_number` (FGR-YYYY-XXXXXX), `production_order_id`, `warehouse_id`
- `received_quantity`, `status` (DRAFT, POSTED), `receipt_date`, `created_by_user_id`, `received_by_user_id`

### `finished_goods_movements`
- `id`, `movement_number` (FGM-YYYY-XXXXXX), `production_order_id`, `warehouse_id`
- `delivery_order_id`, `delivery_order_line_id`, `customer_return_id`, `finished_goods_receipt_id`
- `movement_type` (PRODUCTION_RECEIPT, DELIVERY_DISPATCH, DELIVERY_RETURN, CUSTOMER_RETURN)
- `direction` (IN, OUT), `quantity`, `movement_date`, `created_by_user_id`

### `delivery_orders` & `delivery_order_lines`
- `id`, `delivery_number` (DEL-YYYY-XXXXXX), `customer_order_id`
- `customer_name_snapshot`, `customer_phone_snapshot`, `city_snapshot`, `district_snapshot`, `delivery_address_snapshot`
- `assigned_user_id`, `scheduled_delivery_date`, `dispatched_at`, `delivered_at`, `installed_at`
- `status` (PENDING, ASSIGNED, READY_FOR_DELIVERY, OUT_FOR_DELIVERY, DELIVERED, INSTALLED, FAILED, RESCHEDULED, CANCELLED)

### `delivery_events`
- `id`, `delivery_order_id`, `event_type`, `user_id`, `notes`, `created_at`

### `customer_returns`
- `id`, `return_number` (CRN-YYYY-XXXXXX), `customer_order_id`, `production_order_id`, `delivery_order_id`
- `quantity_returned`, `reason_code` (DEFECTIVE, WRONG_SPECIFICATION, TRANSPORT_DAMAGE, CUSTOMER_CHANGE)
- `status` (REPORTED, RECEIVED_AT_FACTORY, LINKED_TO_QUALITY), `condition_code`, `quality_incident_id`

---

## 3. Key Permissions

- `finished_goods.view`: View finished goods catalog, receipts, and balances.
- `finished_goods.receive`: Create and post finished goods receipts from completed production orders.
- `delivery.view`: View delivery orders, driver tasks, and delivery history.
- `delivery.create`: Create delivery orders from approved customer orders.
- `delivery.assign`: Assign or reassign drivers/technicians to delivery orders.
- `delivery.dispatch`: Dispatch delivery orders (triggering finished stock OUT movement).
- `delivery.complete`: Confirm physical customer delivery.
- `delivery.install`: Confirm final site installation and assembly.
- `delivery.reschedule`: Handle failed deliveries and reschedule shipments.
- `delivery.manage_returns`: Register customer returns, receive returned items into inventory, and link to quality incidents.

---

## 4. Workflows & Lifecycle States

### Delivery Workflow Status Diagram

```
[ PENDING / DRAFT ]
        |
        v
   [ ASSIGNED ]
        |
        v
[ READY_FOR_DELIVERY ]
        |
        v  (Dispatch: Stock OUT Movement)
[ OUT_FOR_DELIVERY ] -------------------> [ FAILED / RESCHEDULED ]
        |                                       | (Stock IN Return)
        v                                       v
   [ DELIVERED ]                        [ READY_FOR_DELIVERY ]
        |
        v  (Installation Complete)
   [ INSTALLED ]
```
