# Stage 14 — Management Reporting, Operational Profitability & Executive Analytics

Stage 14 turns the operational records of Stages 1–13 into management information. It is **read-only analytics**:
no accounting ledger, P&L statement, tax/VAT, payroll, labour or overhead allocation, and no second source of truth.

## 1. Architecture

```
routes/web.php  (prefix /reports, name reports.*)
  └─ app/Http/Controllers/Reports/*          thin controllers: permission check → service → ReportTable/view
       └─ Concerns/RendersReports            pagination (25/page) + CSV export + print for every list report
  └─ app/Domain/Reports/                     reporting domain
       ReportPeriod        business-timezone periods (today … this year, custom) + previous equivalent period
       ReportColumn        column definition (type, label, required abilities) used for BOTH screen and CSV
       ReportTable         permission-filtered columns + rows + paginator (Blade only renders it)
       ReportDataset       aggregated query + row mapper + per-page enrichment (never loads full history)
       ReportFormat        money / quantity / percent / null-safe ratio formatting
       ProfitabilityReportingService   orders, models/configurations, channels, customers
       ProductionAnalyticsService      cost variance, material variance, recipe accuracy/comparison,
                                       departments, bottlenecks, lead time, cost structure, fabric cost
       QualityAnalyticsService         waste, rework, quality incidents
       InventoryAnalyticsService       stock, operational valuation, fabric lots, slow-moving, shortages
       ProcurementAnalyticsService     spend summary, suppliers, purchase price variance, price history
       DeliveryAnalyticsService        order fulfilment pipeline, finished-goods aging, delivery, returns
       ReceivablesAnalyticsService     wraps Stage 13 balances, aging, credit and collections
       ManagementReportingService      management dashboard, today summary, trends, exceptions
  └─ resources/views/management_reports/*    report pages
  └─ resources/views/components/report/*     header (print metadata), period-filter, table, kpi, boundary
```

No report queries run in Blade. All aggregation is done in SQL (joins/sub-queries grouped by the report
dimension); rows are paginated; exports stream the filtered query in chunks of 500.

## 2. Source of truth

| Figure | Source |
|---|---|
| Commercial value | Historical `customer_orders.total_amount` (order level) / `customer_order_lines.line_total` (model level). Never current product prices. |
| Actual material cost | Stage 10 definition: POSTED issue line cost − POSTED usable return line cost, per production order. `ProductionCostService::costSummaryQuery()` is the set-based form of `calculateOrderMaterialCost()`; the reconciliation test proves they are identical. |
| Planned / reference cost | Recipe requirement planned quantity × weighted average cost of remaining lots (the Stage 10 basis). Always labelled *مخطط / مرجعي*. |
| Waste cost | `production_waste_records.total_cost` — analytical subset, **never added** to actual cost. |
| Rework material | Issue lines requested for REWORK/REMANUFACTURE — analytical subset of actual cost, counted once. |
| Procurement cost | Posted material receipt lines (actual). PO price stays the commercial expectation. |
| Inventory | Remaining quantity of ACTIVE lots × each lot's own unit cost. |
| Finished goods | Finished-goods movement ledger (Σ IN − Σ OUT = `FinishedGoodsService::getAvailableQuantity`). |
| Payments / receivables | Stage 13: confirmed allocations, `ReceivablesReportingService`, `CustomerCreditService`. |
| Delivery | Delivery orders and delivery events. |

## 3. Operational contribution (not net profit)

```
المساهمة التشغيلية  = القيمة التجارية للطلب − تكلفة المواد الفعلية
هامش المساهمة التشغيلي = المساهمة التشغيلية ÷ القيمة التجارية × 100
```

Excluded (not available in the system): labour and salaries, rent, electricity, depreciation, delivery labour,
marketing/advertising, administrative overhead, VAT, payment fees, tax, financing. Every profitability screen shows
this boundary (`x-report.boundary`). The terms "صافي الربح / Net Profit / Gross Profit" are never used (checked by a
test and by the browser smoke script).

## 4. Profitability status

| Status | Arabic | Rule |
|---|---|---|
| NOT_STARTED | لم يبدأ الإنتاج | No production order started and no material issued. |
| IN_PROGRESS | قيد الإنتاج | Production released but no posted material yet. |
| PROVISIONAL | تكلفة غير مكتملة | Material issued, but not every production order is completed, released quantity < ordered quantity, or a draft issue/return is still unposted. |
| FINAL_OPERATIONAL | نهائية تشغيلياً | All non-cancelled production orders completed, full quantity released, no unposted material documents. |
| MISSING_COST | تكلفة مفقودة | Production is complete but no posted material cost exists (data gap). Excluded from contribution; flagged in a red KPI on the orders report so it is never shown as 100% margin. |

Actual cost is shown only for PROVISIONAL/FINAL (as "cost to date" when provisional). Contribution and margin are
shown **only for FINAL_OPERATIONAL**; totals of contribution aggregate final orders only and state how many.
Negative contribution is shown as is (never clamped) and highlighted. Cancelled/rejected orders are excluded unless
the "تضمين الملغاة" filter is used. A physical customer return never reduces commercial value.

## 5. Reports

| Report | Route | Date basis | Permission |
|---|---|---|---|
| مركز التقارير | `reports.index` | — | any report permission |
| لوحة الإدارة (+ ملخص اليوم، مقارنة بالفترة السابقة) | `reports.dashboard` | per section (stated) | sections gated per permission |
| الاستثناءات التشغيلية | `reports.exceptions` | current state | groups gated per area permission |
| ربحية الطلبات + تفصيل الطلب | `reports.profitability.orders(.show)` | order date | `reports.profitability` |
| ربحية الموديلات / التكوينات + الترتيب | `reports.profitability.products` | order date | `reports.profitability` or `reports.sales` (no cost columns) |
| قنوات البيع | `reports.profitability.channels` | order date | same |
| تحليل العملاء | `reports.profitability.customers` | order date; balances current | same; balances need `reports.receivables` |
| انحراف تكلفة الإنتاج + انحراف المواد | `reports.production.variance(.show)` | completion / release date | `reports.production` + `costing.view` |
| دقة الوصفات + مقارنة النسخ | `reports.production.recipes` | completion date | `reports.production` |
| هيكل التكلفة + الأقمشة | `reports.production.cost-structure` | release / completion date | `reports.production` + `costing.view` |
| الهدر / إعادة العمل / الجودة | `reports.production.quality` | occurred / created date | `reports.production` or `reports.quality`; costs need `costing.view` |
| الأقسام والاختناقات ومدة الإنتاج | `reports.production.departments` | completion date; queues current | `reports.production` |
| المخزون (أرصدة، قيمة، أقمشة، بطيئة، نواقص) | `reports.inventory` | current state | `reports.inventory`; values need `costing.view` |
| المشتريات والموردون + تاريخ الأسعار | `reports.procurement(.price-history)` | receipt date (actual) / PO date (committed) | `reports.procurement` |
| التنفيذ والتوصيل والمرتجعات | `reports.fulfillment` | order / event / report date | `reports.delivery`; payment column needs `reports.receivables` |
| الذمم والأعمار والتحصيل | `reports.receivables` | current / payment date | `reports.receivables` |

### Highlights
* **Split production:** every production-order figure uses that order's own `released_quantity`; order-level totals sum its production orders.
* **Material variance:** per material — BOM qty/unit, waste %, planned, issued, returned, net consumed, recorded waste, quantity variance (only within the same unit; otherwise "وحدات مختلفة"), planned vs actual lot cost. Materials issued outside the recipe are flagged.
* **Custom designs** form their own group "تصاميم خاصة"; **customer aliases** never fragment model analysis (grouping is by internal model/configuration).
* **Supplier analysis** is factual (no score): PO count/value, actual receipt value, materials, average order→first receipt lead time, on-time % (completed POs whose last receipt ≤ expected date), late, partial, price variance.
* **Purchase price variance** = actual receipt unit cost (base unit) − PO price ÷ conversion factor; total = per-unit variance × base quantity. Positive = unfavourable.
* **Department analytics** are counts/quantities only — no employee productivity or ranking. Bottlenecks show queue size and "oldest open since" (start or creation time); average wait time is not computed because queue-entry times are not recorded.
* **Slow-moving** = positive stock with no posted issue in 30/60/90 days — never labelled obsolete.
* **Trends** compare with the previous equivalent period; change % is omitted when the previous value is zero.
* **Null handling:** "—" means not available / not calculated / not applicable; `0 ر.س` is only shown for a real zero.

## 6. Permissions

New permissions (seeded by `RoleAndPermissionSeeder`, idempotent): `reports.profitability`, `reports.sales`,
`reports.procurement`, `reports.quality`, `reports.delivery`, `reports.receivables`, `reports.export`
(existing: `reports.view`, `reports.production`, `reports.inventory`, `reports.financial`).

| Role | Granted by default |
|---|---|
| Administrator | everything (Gate::before) |
| Production manager | view, production, inventory, quality, delivery, procurement (no profitability, no receivables) |
| Warehouse keeper | view, inventory (valuation hidden without `costing.view`) |
| Purchasing | view, procurement |
| Sales | view, sales (commercial values without cost/contribution) |
| Receivables | view, receivables |
| Customer service, production worker, delivery | none |

`reports.profitability` and `reports.export` are not granted to operational roles; grant them per role from the
roles screen. After deploying, run `php artisan db:seed --class=RoleAndPermissionSeeder` to create the new
permissions.

## 7. Export and print

* `?export=csv` on any list report (button "تصدير CSV", requires `reports.export`) streams UTF-8 CSV (with BOM for
  Arabic in spreadsheets) containing the report title, period, applied filters, generation time and the **same
  permission-filtered columns** with the **same filters** as the screen, across all pages.
* "طباعة" uses print CSS: navigation, filters and buttons are hidden; title, period, applied filters and the
  generated timestamp/user are shown; A4 landscape. No PDF dependency.
* Receivables balances (Stage 13 paginated service) and non-tabular pages (dashboard, delivery summary, cost
  structure) are print-only.

## 8. Performance (local QA data, admin)

Queries per request: most reports 2–37 queries, 10–80 ms. Known heavier pages, all bounded:

| Page | Queries | Reason |
|---|---|---|
| لوحة الإدارة | ~170 | exceptions count + Stage 13 receivables summary (cached 5 min) |
| الاستثناءات | ~90 | payment eligibility evaluated for ≤ 200 open orders (Stage 13 service) |
| الذمم | ~195 | Stage 13 `getCustomerBalances` accessors (25 customers/page) and `getAgingReport` (all open orders) |
| تفصيل الطلب | ~35 | per production order: Stage 10 service + material variance |

These reuse the authoritative Stage 13/10 services instead of duplicating them. No speculative indexes were added.

## 9. Integrity fixes made during Stage 14

* **Usable returns not linked to production orders (High):** returns created from issue lines through the UI had no
  `production_order_id`, so they did not reduce actual material cost. `InventoryService::createReturn()` now links
  the return when all its lines trace to issues of one production order (explicit id still wins).
* **Duplicate credit profile crash (High):** `CustomerCreditService::getCustomerCreditProfile()` could insert a second
  profile for the same customer when several CREDIT orders were evaluated, crashing dashboards. It is now idempotent.
* **Purchasing pages (found in Stage 16):** missing `unitOfMeasure` relation replaced by `baseUnit`.

## 10. Historical integrity and remaining dependencies

Commercial values come from order/line snapshots (`total_amount`, `line_total`, `unit_price`) and costs from posted
document lines (`unit_cost`, `total_cost` frozen at posting). Remaining live dependencies: product model and
supplier **names** are read from master data (renaming changes the label, not the numbers); planned/reference cost
uses the *current* weighted average of remaining lots and therefore moves over time (it is labelled reference).

## 11. QA dataset (local only)

`php artisan db:seed --class=ManagementReportingQaSeeder` (after `MobileQaSeeder`; refuses to run outside `local`,
not registered in `DatabaseSeeder`, idempotent): profitable order, negative-contribution order, partial production,
split production (2 + 3), waste, rework with extra material, usable return, purchase price variance (20 → 22),
late partially received PO, finished goods awaiting delivery, overdue receivable (due 45 days ago).

## 12. Accounting boundary

This is operational management reporting. It is not a general ledger, P&L, official inventory valuation, VAT/tax
or ZATCA report, and it does not allocate labour or overhead. Future accounting stages may add those layers; until
then contribution must not be presented as profit.
