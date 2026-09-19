# Stage 13: Customer Payments, Deposits, Outstanding Balances & Wholesale Credit

## 1. Executive Summary & Operational Financial Boundary
Stage 13 introduces an **operational commercial receivables layer** for the Sadir Furniture Factory Production Management System.

### Strict Boundaries & Exclusions:
- **Operational Scope**: Tracks expected order commercial values, customer payments, allocations to customer orders, deposit requirements, outstanding balances, credit limits, payment terms, snapshot due dates, aging, payment control overrides, and operational payment receipts.
- **Explicit Non-Accounting Exclusions**:
  - NO General Ledger (GL) or Chart of Accounts.
  - NO Accounts Receivable (AR) accounting journal entries.
  - NO official ZATCA tax invoice generation or tax ledger.
  - NO payment gateway or bank API integrations.
  - NO auto-refund engine.
  - NO cash drawer / POS engine.
- **Physical vs. Financial Separation**: Physical customer returns (Stage 11) do NOT automatically alter commercial customer balances or issue financial refunds unless explicitly processed through approved commercial adjustments.

---

## 2. Core Architecture & Database Schema

### Tables & Key Entities:
1. `customer_payments`:
   - Number format: `PAY-YYYY-XXXXXX` (Atomic `DocumentNumberService`).
   - Methods: `CASH`, `BANK_TRANSFER`, `CARD`, `SADAD_OR_EXTERNAL`, `CHEQUE`, `OTHER`.
   - Statuses: `DRAFT`, `PENDING_CONFIRMATION`, `CONFIRMED`, `REVERSED`, `CANCELLED`.
   - Only `CONFIRMED` payments affect customer balances and order allocations.
2. `customer_payment_allocations`:
   - Links confirmed payments to customer orders.
   - Types: `DEPOSIT`, `PARTIAL_PAYMENT`, `FINAL_PAYMENT`, `GENERAL_ALLOCATION`.
   - Unallocated portion remains as customer unapplied operational credit.
3. `customer_credit_profiles`:
   - `credit_enabled` (boolean), `credit_limit` (decimal 18,2), `credit_days` (int), `warning_threshold_percent` (decimal 5,2), `hold_when_exceeded` (boolean).
4. `payment_control_overrides`:
   - Audit log for authorized overrides (`PRODUCTION_RELEASE`, `DELIVERY_DISPATCH`, `CREDIT_LIMIT`) with mandatory recorded rationale.
5. `customer_payment_events`:
   - Immutable audit trail of payment life-cycle events (`CREATED`, `SUBMITTED`, `CONFIRMED`, `ALLOCATED`, `REALLOCATED`, `REVERSED`, `CANCELLED`).

---

## 3. Order Payment Terms & Calculation Rules

### Customer Order Payment Terms (`payment_terms_type`):
- `FULL_BEFORE_PRODUCTION`: Production release requires `Confirmed Paid >= Total Amount`.
- `DEPOSIT_AND_BALANCE`: Production release requires `Confirmed Paid >= Required Deposit`.
- `CASH_ON_DELIVERY` (COD): Allowed for production; delivery displays COD balance due upon dispatch.
- `CREDIT`: Subject to customer credit limit and exposure checks.
- `CUSTOM`: Custom terms.

### Key Calculation Formulas:
- `Order Commercial Total`: Derived from actual historical line prices (`CustomerOrderLine->line_total`).
- `Confirmed Allocated Payments`: `SUM(allocated_amount)` where `payment.status == 'CONFIRMED'`.
- `Outstanding Balance`: `MAX(0, Order Total - Confirmed Allocated Payments)`.
- `Unallocated Customer Credit`: `SUM(amount - allocated_amount)` for customer's `CONFIRMED` payments.
- `Current Credit Exposure`: Sum of unpaid commercial balances for open/fulfilled non-cancelled orders.

---

## 4. Payment Reversal & Historical Integrity
- Confirmed payments are **never hard-deleted**.
- Reversals transition status to `REVERSED`, zeroing out allocation impact while preserving historical allocation records and audit events.
- Reversal recalculates order outstanding balances, payment eligibility gates, and credit exposure.

---

## 5. Payment Gates & Controls

### Services:
- `CustomerPaymentService`: Life-cycle management (create, confirm, cancel, reverse).
- `PaymentAllocationService`: Atomic DB-transaction allocations with row-locking (`lockForUpdate()`), unallocated capacity validation, and order cancellation/price reduction excess handling.
- `OrderPaymentEligibilityService`: Evaluates `checkProductionEligibility($order)` and `checkDeliveryEligibility($order)`.
- `CustomerCreditService`: Exposure monitoring, credit status evaluation (`OK`, `WARNING`, `EXCEEDED`, `DISABLED`), and limit enforcement.
- `ReceivablesReportingService`: Dashboard aggregates, customer balances, operational statement of account (`كشف حساب تشغيلي`), and aging reports.

---

## 6. Roles & Permissions Matrix
- **`receivables_user`** ("مسؤول التحصيل"): Full operational receivables permissions (`receivables.view`, `receivables.payment.create`, `receivables.payment.confirm`, `receivables.payment.reverse`, `receivables.allocate`, `receivables.credit.view`, `receivables.credit.manage`, `receivables.override_payment_control`).
- **`sales_user`** / **`customer_service`**: Can view payment status and record pending payment entries.
- **`production_worker`** / **`delivery_user`**: Sensitive financial data (customer total balance, credit limit, payment history) is hidden; only non-sensitive eligibility indicators (`حالة السداد للإنتاج`, `حالة السداد للتسليم`) are visible.

---

## 7. Printable Operational Payment Receipt
Print view available at `/receivables/payments/{payment}/receipt`.
Mandatory Disclaimer:
`ملاحظة هامة: هذا الإيصال تشغيلي مخصص لإثبات المقبوضات التجارية داخل مصنع مفروشات سدير، ولا يُعد فاتورة ضريبية رسمية.`
