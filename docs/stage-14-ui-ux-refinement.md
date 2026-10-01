# Stage 14 — UI/UX refinement

Reviewed on 2026-09-30 in the existing Laravel / Blade / Bootstrap / Alpine application.

## Scope and accounting boundary

This work changes presentation only. Existing Stage 14 report services, calculation formulas, database structure, routes, permission checks, exports and historical values remain the source of truth. The workspace already contained substantial uncommitted development; those changes were preserved. The report vocabulary remains **المساهمة التشغيلية**, never net profit.

The live audit preceded the redesign and covered all management report pages, their quality/inventory/fulfillment/receivables tabs, configuration grouping and representative record drill-downs. Browser screenshots and subsequent layout checks are in `stage-14-screenshots/`.

## Before / after decisions

| Area | Initial problem | Decision and resulting experience |
|---|---|---|
| Reports Center | Similar links and headings competed for attention | Group reports by management responsibility; show one icon, purpose and explicit opening action. Existing permission-filtered catalogue remains authoritative. |
| Dashboard | Approximately 35 equally prominent metrics made priority unclear | Put actionable operational alerts first, use six executive metrics for the commercial administrator, and compress remaining operational summaries into domain panels. Role-restricted sections remain restricted. |
| Filters | Many fields consumed the first mobile screen | Compact desktop period bar with advanced fields; one mobile drawer with all controls and fixed Apply / Reset actions. Applied chips support individual removal. |
| Profitability | Secondary columns competed with identity, contribution and status | Prioritize order/customer/value/cost/contribution/margin/status, reveal other columns on demand, and use mobile record cards. Negative values have text and visual emphasis. |
| Drill-down | All material details formed a long vertical page | Keep summary visible and disclose order lines and each production order's actual materials/variance in native collapsible sections. Waste and rework remain visible inside the relevant production section. |
| Charts | Inline fill spans did not render their intended width | Render lightweight bar fills as blocks; use mobile label/value rows above bars. Show real current/previous-period comparisons rather than inventing a time series. |
| Exceptions | Raw tables obscured reference, age and next action | Operational inbox grouped by exception type and responsible area, with reference, context, age, existing severity and Follow-up action. Do not fabricate severity. |
| Tablet | At 768px, an absolutely positioned accessible table caption leaked into page overflow; inbox columns became cramped | Anchor accessible captions inside their scroll region; contain the table; use two-column KPI grids and adaptable inbox rows on intermediate widths. Rechecked both tablet sizes. |

Representative evidence:

- [Reports Center before](stage-14-screenshots/center-before-desktop.png) / [after](stage-14-screenshots/center-1366.png).
- [Dashboard before](stage-14-screenshots/dashboard-before-desktop.png) / [after](stage-14-screenshots/dashboard-1366.png).
- [Mobile order profitability](stage-14-screenshots/order-profitability-390.png), [negative-contribution detail](stage-14-screenshots/profitability-detail-390.png), [mobile filter drawer](stage-14-screenshots/filter-drawer-390.png).
- [Tablet profitability after containment fix](stage-14-screenshots/order-profitability-768.png).

## Presentation system

Hierarchy: report context and period → high-priority metrics → relevant comparisons/distributions → record detail. Operational alerts may precede executive metrics when immediate follow-up is the dashboard's purpose.

KPI cards share label-first typography, tabular numerals, concise context and optional drill-down. Monetary formatting uses existing report precision and thousands separators. Two cards per row on phones, two on narrow tablets, three on typical laptops for six-card groups, and six on wide screens. Negative contribution keeps its sign and warning text.

Filters retain existing GET parameter names and all existing presets: today, yesterday, week, month, previous month, year and custom. Date inputs enable only for a custom period. Desktop secondary filters are progressively disclosed. Mobile controls use the existing Bootstrap offcanvas; its footer accounts for the bottom safe area and its content has matching bottom padding. Reset restores report defaults while preserving existing report grouping/sort context. Chips describe submitted parameters, not unsaved edits.

Tables retain the permission-approved column list, pagination, existing totals and contextual empty messages. Desktop tables expose primary columns first with an all-columns switch. Mobile order/customer/model/receivables summaries use the same cell formatter as desktop; analytical material/inventory tables retain controlled horizontal scrolling with a visible hint and keyboard-focusable region. No page-level horizontal overflow was present in the final viewport checks.

Reusable components live in `resources/views/components/report/`: header, KPI, comparison, period-filter, table, cell, empty-state, existing chart/bar/status/boundary components. Styles are scoped through `.report-workspace` in `resources/css/reports.css`; the small Alpine filter provider is `resources/js/report-ui.js`. Existing Cairo, system palette and shell are retained. Sidebar report navigation now leads to the Reports Center.

Accessibility: associated input labels, table captions/column headers, named actions and scroll regions, visible focus outlines, native disclosure elements, textual states alongside color, and minimum 44px primary buttons. Bootstrap manages drawer focus and Escape dismissal. This is targeted accessibility improvement, not a certified WCAG audit.

RTL: identifiers and financial table cells use bidi isolation; numeric styling uses tabular figures. Do not translate technical IDs or drop negative signs. Long labels and values may wrap instead of being clipped.

Charts: use existing prepared data and semantic colors. Compare periods only where comparison data exists. Do not invent trend points or classify a cost delta as beneficial without business context. Keep numerical/text alternatives alongside CSS visualizations. Finished-goods aging explicitly covers **records on the current page**, not a fabricated global total. Inventory valuation explicitly states the existing global valuation scope where its total is not filter-specific. Mixed material units are never summed.

Loading/error boundaries: these reports render on the server; no asynchronous chart loaders or new network requests were introduced. Existing application validation/error handling remains in place. Error injection and production error-page review were not performed in this presentation task.

Print: existing title/period/filter/author context remains; interactive controls and mobile duplicate records are hidden, desktop tables and secondary columns are available, and disclosure content is exposed through print CSS. Physical printing and native WebView print integration were not exercised.

## Responsive evidence and matrices

PASS below means the report rendered and the browser viewport check found no page-level horizontal overflow; representative screenshots were visually inspected. It does not certify a physical Android/iOS WebView, native keyboard behavior or a printer. The detailed geometry record is [viewport-results.json](stage-14-screenshots/viewport-results.json). Analytical table scrolling inside its own region is intentional.

Desktop matrix:

| Screen | 1366×768 | 1440×900 | 1920×1080 |
|---|---|---|---|
| Reports Center | PASS | PASS | PASS |
| Dashboard | PASS | PASS | PASS |
| Order Profitability | PASS | PASS | PASS |
| Model Profitability | PASS | PASS | PASS |
| Production Variance | PASS | PASS | PASS |
| Inventory | PASS | PASS | PASS |
| Procurement | PASS | PASS | PASS |
| Delivery | PASS | PASS | PASS |
| Receivables | PASS | PASS | PASS |
| Exceptions | WARNING | WARNING | WARNING |

Mobile/tablet matrix:

| Screen | 360×800 | 390×844 | 412×915 | 430×932 | 768×1024 |
|---|---|---|---|---|---|
| Reports Center | PASS | PASS | PASS | PASS | PASS |
| Dashboard | PASS | PASS | PASS | PASS | PASS |
| Order Profitability | PASS | PASS | PASS | PASS | PASS |
| Profitability Detail | PASS | PASS | PASS | PASS | PASS |
| Production Variance | PASS | PASS | PASS | PASS | PASS |
| Inventory | PASS | PASS | PASS | PASS | PASS |
| Procurement | PASS | PASS | PASS | PASS | PASS |
| Delivery | PASS | PASS | PASS | PASS | PASS |
| Receivables | PASS | PASS | PASS | PASS | PASS |
| Exceptions | WARNING | WARNING | WARNING | WARNING | WARNING |

Additional 375×812 and 820×1180 checks covered all 13 matrix targets with zero page overflow. Waste, model profitability and the supplier-analysis report path were also included in mobile geometry checks; supplier analytics remains a section of Procurement, not a new route.

Exceptions WARNING refers to one **pre-existing data/reference issue**, not a remaining layout failure: an overdue receivable item has reference `—` and links to `/receivables/customers/0`. This was observed before the redesign and was preserved for a separate service/data investigation. Other exception groups retain their existing follow-up links. Raw technical reason codes in some records remain another small presentation opportunity.

## Interaction and regression verification

- Mobile custom period 2026-09-01 to 2026-09-30 plus FINAL_OPERATIONAL returned the two expected records. CSV URL retained period/from/to/status/sort. Removing the period chip removed both dates. Reset restored the normal report while retaining existing sort context.
- Drawer Apply/Reset measured 44px high and remained visible at the bottom. Escape dismissed the drawer and returned focus to the filter button.
- Order ORD-QA14-LOSS drill-down kept value 150.00, actual material cost 268.35, contribution -118.35 and margin -78.9%. Opening its production section exposed material, variance, waste and rework data without page overflow.
- A custom January 2025 period returned zero orders and displayed the empty-state message with guidance to widen the period or clear filters. See [empty state](stage-14-screenshots/empty-orders-390.png).
- The final interaction tab's warning/error console was empty. Rendered report and asset behavior showed no unexpected UI failures in the successful checks. Browser automation intermittently lost debugger synchronization; fresh tabs restored testing. These tool timeouts are not reported as application defects.
- `php vendor/bin/phpunit tests/Feature/Reports`: **27 tests, 194 assertions passed**.
- `php vendor/bin/phpunit`: **336 tests, 1,640 assertions passed**, 135.485 seconds. Later changes were limited to presentation/spacing and sidebar presentation.
- `php vendor/bin/pint --dirty --format agent`: passed. `php vendor/bin/pint --format agent`: passed.
- `npm run build`: passed without warnings. Final bundle: CSS **345.03 kB / 61.25 kB gzip**, JS **136.45 kB / 42.98 kB gzip**. No packages or CDN dependencies were added. Relative to the initial measured CSS bundle (332.97 kB), the increase is approximately 12.06 kB before gzip; no reliable pre-change JS delta was captured.
- `php artisan view:cache --no-interaction`: passed. `git diff --check`: passed; Git may print the existing CRLF normalization notice.
- `storage/logs/laravel.log` was reviewed. Last entries at 17:03 concern missing application encryption key; the log stayed unchanged during the later successful report review. No encryption key was regenerated and no environment secrets were changed.

## Final report — requested 25 points

| # | Topic | Result |
|---|---|---|
| 1 | Initial problems | Flat priority, oversized filter stacks, invisible chart fills, raw table density and long drill-down pages. |
| 2 | UX strategy | Decision-first hierarchy, shared components and progressive disclosure. |
| 3 | Reports Center | Grouped catalogue, short purpose and clear opening action. |
| 4 | Dashboard | Operational attention first; six executive KPIs and smaller domain panels. |
| 5 | Profitability | Primary columns, clear completeness states, negative-value warnings and mobile cards. |
| 6 | Production analytics | Shared metrics/charts and collapsible material-level detail; existing variance/waste rules preserved. |
| 7 | Inventory | Clear total scope, record counts, readable fabric identity/color/lot and controlled analytical scrolling. |
| 8 | Procurement | Existing receipt/commitment/delay summaries kept separate from supplier analysis and price history. |
| 9 | Delivery | Existing status summary retained; finished-goods page aging gets explicit buckets and scope. |
| 10 | Receivables | Aging visualization and customer mobile summaries; sensitive approved columns remain protected. |
| 11 | Exceptions | Grouped operational inbox; one pre-existing missing-reference link needs separate investigation. |
| 12 | Filters | Compact desktop bar, mobile drawer, active chips and preserved GET parameters. |
| 13 | Tables | Shared cell formatting, optional columns, mobile records and contained scrolling. |
| 14 | Charts | Correct visible bar widths, compact mobile labels and honest period comparisons. |
| 15 | RTL | Bidi-isolated technical IDs/numbers and stable negative signs. |
| 16 | Accessibility | Labels, focus states, textual severity, captions and 44px primary controls. |
| 17 | Laptop | All 13 targets fit 1366/1440/1920 viewports. |
| 18 | Mobile WebView | Browser layouts validated at five phone sizes; physical WebView/keyboard not certified. |
| 19 | Tablet | Both sizes fit after caption-containment and inbox-grid corrections. |
| 20 | Performance | Existing lightweight stack retained; no dependency additions. Bundle sizes recorded above. |
| 21 | Console/network | Fresh interaction console empty; no unexpected failures in successful live checks; no exhaustive HAR audit. |
| 22 | PHPUnit | 336 tests / 1,640 assertions passed; reports subset 27 / 194. |
| 23 | Build | Vite, Pint, compiled views and diff whitespace checks passed. |
| 24 | Remaining issues | Existing invalid customer reference, occasional currency wrapping/raw reason codes, real-device and physical-print verification outstanding. |
| 25 | Quality assessment | **CONDITIONAL GO — only minor visual/UX refinements remain.** The pre-existing reference defect is separate from UI layout readiness. |

## Future maintenance

Maintain existing report services and permission-filtered column metadata as the authority. Use shared components for new presentations. Always label partial/page-only/global totals honestly. Do not calculate new business metrics in Blade. Recheck 360, 390, 768 and 1366 breakpoints when changing density, and repeat the two tablet sizes for grids. Keep focus/touch/RTL behavior and print disclosure coverage. Resolve the missing customer reference in a separately scoped data/service task. No new stage was started.
