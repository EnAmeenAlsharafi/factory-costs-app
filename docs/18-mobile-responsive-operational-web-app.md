# Stage 16 — Mobile Responsive Operational Web App

One system, one frontend: the same Laravel / Blade / Bootstrap 5.3 (RTL) / Alpine application now works as a
task-oriented operational tool in phone browsers (Chrome on Android, Safari on iPhone). No native app, no PWA,
no second frontend, no mobile-only business logic. Every mobile screen uses the same routes, services,
permissions, transactions and audit trails as desktop.

## 1. Goals

- A phone user sees **"what do I need to do now?"** and reaches the task in one tap.
- Primary actions are always visible (sticky bottom action bar) and never covered by the keyboard.
- No horizontal page scrolling; dense analytical tables scroll inside their own container only.
- Touch targets ≥ 44 px, inputs at 16 px (prevents iOS zoom), correct mobile keyboards.
- Sensitive values (costs, prices, balances) are shown only to roles that need them.

## 2. Breakpoint strategy

| Range | Name | Shell behaviour |
|---|---|---|
| `< 768px` (`max-width: 767.98px`) | Mobile | Off-canvas drawer, cards, fixed bottom action bar, stacked editable tables |
| `768–991px` | Tablet | Collapsible sidebar, tables, 44 px targets on touch (`pointer: coarse`) |
| `≥ 992px` | Desktop | Unchanged desktop layout |

Boundaries are Bootstrap-aligned (`md` = 768). All mobile rules use `max-width: 767.98px`, so 767/768/769 and
991/992/993 have no gap or overlap. `@media (pointer: coarse)` raises buttons, tabs and page links to 44 px on any
touch screen regardless of width, while desktop mice keep the denser 40 px controls.

## 3. Responsive component system (`resources/css/app.css`, "Stage 16" section)

| Pattern | Markup | Purpose |
|---|---|---|
| Status chip | `<x-status-badge domain="delivery" :status="…"/>` | Text + icon + colour from `App\Services\StatusPresenter` (single source of status language for desktop and mobile) |
| Task card | `.task-card` (+ `.tone-*`, `.is-rework`) with `.task-card-head/-title/-ref/-meta/-actions` | Operational list item (queue, deliveries, orders) |
| Quantity trio | `.qty-trio` (`.is-remaining`) | Target / done / remaining |
| Details list | `<dl class="detail-list">` | Responsive label/value grid |
| Segment tabs | `.segment-tabs` > `.segment-tab` (+ `.count`) | Simple status filters with counts, horizontally scrollable |
| Metric tile | `.metric-tile` (`.is-attention`, `.is-zero`) | Tappable dashboard counters |
| Action bar | `<x-mobile-action-bar>` | Fixed bottom on phones (safe-area aware, spacer included, hidden while typing); inline row ≥ 768 px |
| Record header | `.record-header` (+ `.record-header-sticky`) | Back action + identity + status, sticky under the top bar on phones |
| Filter sheet | `<x-filter-sheet id action :active-count :reset-url>` + `search` slot | Search always visible; secondary filters in a bottom sheet (`offcanvas-md`) on phones, inline card on desktop; "مسح الفلاتر" |
| Stacked table | `table.table-stack-sm`, `td[data-label]`, `.stack-head`, `.stack-half`, `.stack-only`, `.stack-hide-sm` | Type C editable line tables become one card per line on phones — **same DOM, no duplicated inputs** |
| Card list / table pair | `.mobile-cards-only` + `.has-mobile-cards` | Type A lists: cards on phones, table on tablet/desktop |
| Sticky first column | `table.table-sticky-first` | Type B dense tables keep the identifying column visible while scrolling |
| Helpers | `.ltr-isolate`, `.min-w-0`, `.color-dot`, `.rework-flag` | Technical codes/phones/dimensions in LTR isolation, truncation, fabric colour swatch, rework marker |

### Table strategy

- **Type A (simple lists)** → cards on phones: production queue, "مهامي", customer orders.
- **Type B (dense/analytical)** → controlled horizontal scroll inside `.table-responsive`, scroll hint shown only when the table actually overflows, sticky first column where useful (stock balances/lots, reports, procurement comparison).
- **Type C (editable lines)** → `table-stack-sm`: customer order create/edit lines (collapsible, duplicable, "البند N — model" header), material receipt create/edit lines, receipt/material-request line displays.

## 4. JavaScript behaviours (`resources/js/app.js`)

- **Drawer**: off-canvas on phones, anchored to the RTL start edge and fully hidden (`visibility: hidden`) when closed; only the current module's section expanded; closes on link tap, Escape and backdrop tap; main content made `inert` while open; body scroll released on close.
- **Form safety**: `data-confirm="…"` on a form or submit button asks before irreversible actions; every POST disables its submitter after submission (no duplicate posts on slow networks; bfcache restore re-enables); `data-unsaved-warning` warns before leaving large forms with edits.
- **Keyboard awareness**: `body.keyboard-open` while a text field is focused hides the fixed action bar so it never covers the field.
- **Network banner**: offline/online banner; typeahead distinguishes "no connection" / "failed" (with retry) from "no results".
- **Typeahead**: 250 ms debounce, stale-response guard, unique ARIA ids (`combobox`/`listbox`/`option`, `aria-activedescendant`), near full-width results on phones, flips above the field and shrinks to the visible viewport when the keyboard is open (`visualViewport`), scrolls the field clear of the sticky header.
- **Numeric inputs**: `type=number` fields get `lang="en"` (also for rows added dynamically) so digits stay Western like the rest of the UI.

## 5. Role workflows

| Role | Entry | Mobile flow |
|---|---|---|
| Production worker | Dashboard "مهام قسمي" tiles / quick link | Queue tabs (بانتظار البدء / قيد التنفيذ / إعادة عمل / أُنجزت اليوم) → task card → full-screen task sheet (product, size, fabric, colour, notes, rework reason, target/done/remaining) → "تسجيل التقدم" with numeric keypad, ± buttons, "كامل المتبقي", over-entry blocked, confirmation on final completion, success message states the new remaining |
| Warehouse keeper | Dashboard tiles / quick links | Material request → sticky "صرف" → lot cards (material, colour, supplier, receipt ref, date, lot code; cost only with `costing.view`) with FIFO pre-allocation and live over-issue guard → confirmed issue. Receipt: stacked line cards, fabric colour required before posting, sticky "ترحيل سند الاستلام" with confirmation. Stock lookup by name, code, colour or lot |
| Delivery / installation | "مهامي" | Filter tabs (اليوم / جاهزة للانطلاق / خارج للتوصيل / بانتظار التركيب / متعذرة) → card with call button, district, date, items → detail (customer + `tel:` call, address, location link if present, items with size/fabric/colour) → only valid next action in the bottom bar (بدء التوصيل → تم التسليم → تم التركيب) with confirmations; quick fail/reschedule sheet (reason chips, note, new date) |
| Production manager | Dashboard "متابعة الإنتاج" | Review page as per-line manufacturing cards (model, configuration, requested vs reference size, fabric supplier/material, colour, recipe, payment gate) → sticky "اعتماد للإنتاج" → release → production board |
| Customer service | Quick link "طلب عميل جديد" | Order list with search + filter sheet + cards → create/edit with single-column header and stacked line cards, model/fabric typeaheads, colour select, duplicate/collapse lines, sticky save |
| Purchasing | Dashboard "المشتريات" (PR awaiting action, open/late/partial POs) | Planning, PR, quotations, PO and receipt status remain readable with controlled horizontal scroll |
| Receivables | Dashboard "التحصيل والذمم" | Balances search → statement → register payment → confirm → allocate |

## 6. Dashboard

`App\Services\OperationalDashboardService` builds permission-gated sections (production tasks, delivery,
warehouse, production management, customer service, purchasing, receivables). Counts are computed only for sections
the user can act on; every tile links to the filtered work list. Reference-data counts are collapsed under
"السجلات الأساسية" and computed only for records the user may open.

## 7. Security and privacy

- Hidden buttons are not security: every action is still authorised server-side (unchanged controllers/services); new routes follow the same permission checks (`delivery.orders.ready` requires `delivery.create`).
- Production worker task sheets show no costs, prices or balances.
- Delivery users see whether delivery is allowed and, for COD, the amount to collect — never the receivables reason, order prices or the payments card on the order page.
- Warehouse users see lot/receipt costs and receipt totals only with `costing.view`.
- Error messages are Arabic business messages; delivery transition errors use Arabic status labels; material-issue rule violations return to the form instead of a 500.

## 8. Browser support

Chrome/Chromium on Android and Safari on iOS (15.4+). Only broadly supported CSS/JS is used: logical properties,
`env(safe-area-inset-bottom)` with `viewport-fit=cover`, `dvh`, `visualViewport` (progressive), `inert`
(progressive), `:has()` only as a non-essential enhancement. No hover-only interactions.

## 9. QA dataset (local only)

`php artisan db:seed --class=MobileQaSeeder` (refuses to run outside `local`, not registered in `DatabaseSeeder`,
idempotent). Creates worker tasks, a submitted material request needing two fabric lots, a draft receipt,
delivery tasks in each field state (COD, installation required), a shortage PR, an overdue sent PO, a pending
customer payment, a releasable DRAFT production order and the users `purchasing.user` / `receivables.user`
(password `password`).

## 10. Explicit exclusions

Native app, Flutter, React Native, PWA/service worker/offline sync, push notifications, dark mode, GPS tracking,
digital signatures, barcode/QR infrastructure, image compression, mobile print layouts.

## 11. Future QR / barcode integration points

- **Lot barcode** → `inventory.lots.show` (`/inventory/lots/{lot}`) and the lot cards in `production/material_requests/fulfill.blade.php` (a scanned lot code can pre-select the matching lot checkbox).
- **Production order QR** → `production.queue.index` task sheets (`#task-sheet-{operation}`) and `production.orders.show`.
- **Delivery order QR** → `delivery.orders.show`; the next-action partial `delivery/orders/partials/next-actions.blade.php` is the single place to add scan-to-confirm.
- The search fields in `x-filter-sheet` (`name="search"`) accept scanned codes directly (keyboard-wedge scanners work today).
