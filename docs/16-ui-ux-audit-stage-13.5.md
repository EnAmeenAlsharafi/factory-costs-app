# Sadir Factory System — Full UI/UX Audit & Browser Validation
## Stage 13.5 Frontend Verification

**Audit date:** 20 September 2026
**Application under test:** `http://localhost/` (XAMPP, local)
**Codebase:** `D:\factory-costs-app`
**Auditor:** Claude (real-browser validation + source correlation)
**Stage 13.5 status on entry:** CONDITIONAL GO
**Stage 13.5 status on exit:** **NO-GO — UI stabilization required**

---

## 1. Executive UI/UX Summary

The Sadir Factory System is, structurally, a well-built RTL application. The shell, navigation, responsive behaviour and typeahead architecture are genuinely good — better than most internal ERP front-ends. Across twenty-nine screens loaded and instrumented in a real browser, there were **zero JavaScript errors, zero failed asset requests, and zero page-level horizontal scrolling** at every viewport except one specific case. The `ReferenceError: tr is not defined` regression does **not** reappear. The Stage 13.5 fixes that were meant to land have landed: the typeahead dropdown escapes table clipping via fixed positioning, model changes correctly clear configuration, multi-line state is properly isolated, and delivery status transitions are correctly gated so a DRAFT order offers no illegal action.

However, the audit found two defects that block a GO decision, and both are systemic rather than cosmetic.

**First, the application has no translation files at all.** `APP_LOCALE=ar` and `APP_FALLBACK_LOCALE=ar` are set, but no `lang/` directory exists. Every validation message that is not given an explicit custom string therefore renders to the user as a raw framework key. A customer-service agent who submits an incomplete order sees the literal text `يرجى تصحيح أخطاء الإدخال التالية: validation.required validation.required validation.required`. This affects every form in the system. For a factory-floor tool where the operator cannot ask a developer what went wrong, this is disqualifying on its own.

**Second, the procurement module is broken today.** Three of its create screens return HTTP 500 because nine code sites reference a relationship, `unitOfMeasure`, that does not exist on the `Material` model — the model defines `baseUnit`. Purchase Request create, Supplier Quotation create and Purchase Order create are all dead. The corresponding show screens currently return 200 only because there is no procurement data yet; they execute the same broken eager-load and will fail the moment the factory creates its first purchase order.

Beyond those two, three findings concern information that is either missing where it is needed or exposed where it should not be. The Production Review screen — the Production Manager's decision point — does not display fabric supplier, fabric type or colour code at all, even though those are captured at the order line and propagate into production requirements. Conversely, the customer order screen renders a full receivables panel (order total, amount paid, outstanding balance, payment terms) with no permission gate, so a delivery driver who has no receivables permission and is correctly blocked from `/receivables/*` with a 403 can read the customer's outstanding balance from the order page. And every typeahead in the system reports a failed search request as "لا توجد نتائج مطابقة" — telling the user the data does not exist when in fact the request failed. This was not theoretical: a transient `401` on `/api/search/suppliers` was captured live during the audit and produced exactly that false negative.

The recommendation is a short, targeted stabilization pass. None of the P0 items require redesign or business-logic change; the largest is adding a language file. The design system, layout and interaction model should be left alone — they are the strongest part of this application.

---

## 2. Browser Environment

| Item | Value |
|---|---|
| Browser | Claude built-in browser pane (Chromium), running on device `ameen` |
| Why not Chrome | The Claude-in-Chrome extension was not reachable from this session; the built-in browser was used instead |
| Server | XAMPP on Windows, MariaDB/MySQL |
| Session driver | `file` |
| Locale | `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=ar` |
| Font | Cairo — confirmed loaded (`document.fonts.check('16px Cairo')` → true) on every screen |
| Document direction | `dir="rtl"`, `lang="ar"` — correct throughout |

**Method.** Evidence was gathered primarily by DOM and CSSOM instrumentation executed inside the live pages rather than by reading screenshots. For each screen a probe measured real geometry (element rectangles versus viewport, scroll widths, ancestor overflow context), label-to-input association, accessible names, computed text direction on technical codes, table wrapping and internal scroll, and empty-state content. Responsive testing was performed by loading each screen in a same-origin iframe sized to the target viewport, so media queries evaluate against the true width. Overflow findings were filtered to exclude elements contained by an intentionally scrollable wrapper, a clipping ancestor, a transform (the off-canvas drawer) or fixed positioning, so that only genuine page-level breakage is reported.

**Two limitations are stated plainly.** The browser pane was hidden for most of the audit, which means `document.visibilityState === 'hidden'` and **CSS transitions are frozen at time zero**. Animation and transition smoothness was therefore not assessed. This initially produced a false reading that the mobile drawer did not open; disabling the transition and re-measuring showed the drawer lands correctly at `left: 0` fully inside the viewport, so the drawer is correct and no defect is reported. Separately, the `read_console_messages` tool returned nothing even for deliberately injected errors, so it was not trusted; console findings below come from `window.onerror` and `unhandledrejection` listeners attached inside each page, a harness that was self-tested against injected errors before use.

---

## 3. Viewports Actually Tested

| Viewport | Coverage |
|---|---|
| 1920×1080 | Login, Dashboard, Customer Order Create, Material Receipt Create |
| 1440×900 | Full sweep — 33 screens |
| 1366×768 | 9 matrix screens |
| 1024×768 | 9 matrix screens |
| 768×1024 | 9 matrix screens |
| 390×844 | Login, plus 9 matrix screens, plus interactive drawer test |

Browser zoom at 125% was **not** separately tested; the iframe width sweep covers the equivalent effective-width cases, but true zoom (which also scales fonts) was not exercised. This is a gap.

---

## 4. Roles Actually Tested

Six roles were tested by logging in as the seeded account and probing both navigation and direct URL access:

| Role | Account | Tested |
|---|---|---|
| System Administrator | `admin` | Yes |
| Production Manager | `prod.manager` | Yes |
| Customer Service | `cs.agent` | Yes |
| Warehouse Keeper | `warehouse.keeper` | Yes |
| Production Department User | `worker.carpenter` | Yes |
| Delivery / Installation | `delivery.driver` | Yes |
| Sales | — | **Not testable** |
| Purchasing | — | **Not testable** |
| Receivables | — | **Not testable** |

`RoleAndPermissionSeeder` defines nine roles, but `DevelopmentSeeder` creates user accounts for only six. There is no seeded `sales_user`, `purchasing_user` or `receivables_user`, so three of the nine roles could not be validated in the browser. This is itself a finding (I-19) — it means those three roles have almost certainly never been exercised through the UI.

---

## 5. User Journeys Completed

| # | Journey | Result |
|---|---|---|
| 1 | Customer Service: login → customer → new order → model search → configuration → supplier → fabric → colour → qty → price → save | **Completed through validation.** Every interaction step verified. Final persistence not completed — the submit surfaced the untranslated-validation defect (I-01), which was the more important finding. |
| 2 | Production Manager: order → production review → recipe → approve → release → monitor | **Partial.** Review screen reached and inspected; approval/release not executed against real records. |
| 3 | Warehouse: receipt fabric → colour → post → lot → material request → issue | **Partial.** Receipt create and conditional colour logic fully verified; all eight existing receipts are already posted, so the pre-post state and post-idempotency could not be observed. No material requests exist. |
| 4 | Purchasing: low stock → PR → RFQ → quote → PO → receipt | **BLOCKED.** PR create, quotation create and PO create all return HTTP 500 (I-02). |
| 5 | Production Worker: own queue → progress → complete | **Partial.** Queue reached and role scoping verified; no released production order exists to progress. |
| 6 | Delivery: assigned task → out for delivery → delivered → installed | **BLOCKED.** No delivery orders exist. Transition gating verified at source level instead. |
| 7 | Receivables: payment → confirm → allocate → verify outstanding | **BLOCKED.** No `receivables_user` account exists. |

Four of seven journeys could not be completed end-to-end. Two were blocked by defects, two by absent data or accounts. **This is the single biggest caveat on this audit**: the delivery, procurement and receivables modules have essentially no operational data, so their runtime behaviour under real load remains unverified.

---

## 6. Global Layout Findings

Layout quality is high. Across all six viewports and every screen tested, **page-level horizontal scroll was zero in every case but one**. Wide tables are consistently wrapped in `.table-responsive` and scroll internally, which is the intended pattern and is applied uniformly — the Roles matrix, for example, is 1190 px wide inside a 1093 px wrapper and scrolls internally without dragging the page. Cards align, forms reflow sensibly, and the sticky form action bar collapses to full-width stacked buttons with a 44 px minimum height below 768 px, which is correct for touch.

The single genuine layout defect is at exactly 768 px. At that width the `max-width: 767.98px` mobile rules are off, so the sidebar column still claims 248 px, leaving the content area at 505 px. On the Production Order screen a stats badge reading `0 هدر` (60 px wide) is pushed to `left: -35px`, producing 50 px of page-level horizontal scroll. The screen is clean at 390, 1024, 1366, 1440 and 1920 — this is a boundary-condition break that lands precisely on iPad portrait.

The mobile drawer is correctly implemented: `position: fixed`, parked at `translateX(105%)`, opening to `translateX(0)`, with a backdrop, a scroll lock via `body.shell-drawer-open { overflow: hidden }`, and — importantly — the lock is correctly released on both Escape and the close button. The specific concern about the body remaining locked after the drawer closes was tested directly and does **not** occur.

Sidebar section auto-expansion works: navigating to a deep route expands the owning section and leaves the others collapsed.

---

## 7. RTL Findings

Core RTL handling is correct. Direction, alignment, breadcrumbs, pagination, badges and modals all behave. Technical codes are deliberately isolated where it matters — the typeahead result rows wrap the code in `<span dir="ltr">`, so `MOD-000001` renders correctly beside Arabic model names, and the dimension display `160×200 سم` reads naturally on the review screen.

The gap is codes embedded inside Arabic sentence text, particularly `<option>` labels. On Material Receipt Create, twenty elements contain a technical code with a computed direction of `rtl` and no bidi isolation — for example `ابداع السرير (SUP-000014)` and `شركة الراجحي للأقمشة والمنسوجات (SUP-000010)`. In an RTL context the bracketed code and its parentheses can reorder visually. The same pattern appears on Product Models (15 instances), Inventory Balances (38) and Inventory Receipts (8). The fix is to wrap the code fragment in `<bdi>` or apply `unicode-bidi: isolate`; note that `<option>` cannot contain markup, so those specific cases need either a Unicode isolate character or a restructured label that puts the code at the start.

---

## 8. Responsive Findings

Behaviour at 390 px is good. The form action bar stacks, page header actions go full width, the table scroll hint appears, header meta is hidden, and tables scroll internally rather than breaking the page. Customer Order Create at 390 keeps its 1149 px line table inside a wrapper with 848 px of internal scroll and no page scroll — deliberate and correct. The typeahead component has an explicit mobile branch that pins the dropdown to `left: 12px; right: 12px` below 480 px rather than using the desktop width calculation.

The one responsive defect is the 768 px production-order overflow described above. Tap targets are adequate except on Procurement Planning, where nine controls render below 32 px in height.

---

## 9. Dashboard Findings

The dashboard is the weakest screen in the application, and the problem is prioritisation rather than correctness.

`DashboardController` computes six master-data counts (users, customers, suppliers, departments, units, sales channels) and up to six operational metrics. The operational metrics are properly permission-gated in the controller, and the master-data cards are properly gated in the Blade — I verified there are no dead cards linking to a 403 for any role tested. So permissions are fine.

The issue is that the master-data block is presented **first and most prominently**, above the operational indicators. "وحدات القياس النشطة 9" and "قنوات البيع النشطة 4" are not information anyone acts on. A factory user arriving at the dashboard should see what needs attention; instead they see a configuration census.

The role-specific consequences are worse:

- **Delivery driver** sees exactly two cards, one of which is "طلبات بانتظار المراجعة 1 طلب" — orders awaiting *production review*, which is not their work at all. They see **no delivery tasks, no assigned route, nothing actionable**. The controller defines no delivery metric.
- **Production worker** sees factory-wide active production orders and open quality/rework counts, but **not their own department queue** — the one number that matters to them.
- **Purchasing and Receivables** have no metrics defined at all, so those dashboards would be near-empty.

Zero-value cards render as a bare "0" with no guidance. Given the current data state almost every operational card reads zero, so the dashboard is presently a wall of zeros above a census of master data.

---

## 10. Sales / Customer Order Findings

**This module is the best-executed part of the application and largely passes.** Every specific behaviour called out in the audit brief was tested and works:

- **Product model search.** Typing `ميل` returned four correct matches. The dropdown renders at `position: fixed`, `z-index: 1070`, 360 px wide, fully inside the viewport, **not clipped by the table**. No duplicate codes, no text overflow, no broken wrapping. Codes are LTR-isolated.
- **Configuration selector.** Correctly a normal `<select>`, disabled until a model is chosen, then populated with only that model's configurations.
- **Model change clears configuration.** Verified explicitly: with configuration `5` selected, switching the model to `MOD-000016` reset the value to empty and reloaded a scoped option list.
- **Fabric supplier search.** `الرا` returned the two correct suppliers.
- **Fabric material dependency.** Correctly disabled with placeholder `اختر مورد القماش أولاً` until a supplier is chosen, then enabled with `ابحث عن نوع القماش...`. Scoping is correct at the API level too: supplier 3 (fabric supplier) returns `البطانة`; supplier 1 (foam supplier) correctly returns nothing. The component even distinguishes "this supplier has no fabrics mapped" from "no results" using the `X-Supplier-Has-Fabrics` response header.
- **Multi-line state isolation.** Three lines added; setting the supplier on line 2 left line 1 (supplier 3) and line 3 (empty) untouched.
- **Value retention after validation failure.** Verified: model id, model label, colour code, quantity, unit price, customer and the reloaded configuration options all survived the failed submit.

Two defects sit on top of this good foundation.

**Validation messages are untranslated (I-01).** The error summary reads `validation.required validation.required validation.required`. It does not name the offending field, and `.invalid-feedback` elements were empty — only `sales_channel_id` received `.is-invalid`, so errors are largely not bound to their fields.

**The error summary is duplicated (I-09).** `partials/flash.blade.php` is included globally in the layout *and* fifteen views render their own `$errors->any()` block, so the same list appears twice. Observed directly: two `.alert-danger` blocks with near-identical text.

**Search failures masquerade as empty results (I-04).** The component sets `errorMessage = 'تعذر تحميل النتائج'` on a failed request, but `errorMessage` is **not rendered in any view in the codebase**. The dropdown only renders loading / results / "لا توجد نتائج مطابقة" / "ابدأ بالكتابة". Proven two ways: a live `GET /api/search/suppliers?q=ا` returned `401` during normal use and the UI showed "no matching results" even though the API returns six suppliers for that query; and forcing a 401 for a known-good query (`الراجحي`) reproduced the false negative deterministically.

---

## 11. BOM / Recipe Findings

Recipes, templates and components render cleanly at all desktop widths with no overflow and no console errors. The BOM index, template index and semi-finished components index all wrap their tables correctly. No mixed Arabic/English heading corruption such as a stray `Target` was observed on these screens.

Coverage here is thinner than elsewhere: only two recipes exist, so version states (DRAFT vs APPROVED), the approve action, version copy and the immutability of approved versions were not exercised in the browser. The template → recipe → configuration relationship is expressed in the navigation and the review screen's BOM selector, but whether that workflow "feels obvious" to a new user could not be judged against real multi-version data.

---

## 12. Inventory Findings

**Material Receipt Create passes its critical test.** The fabric colour field is correctly conditional and was verified against four materials: selecting `قماش مخمل ثقيل` (fabric) makes the colour field visible **and** `required`; selecting `ام دي اف` (wood) hides it and clears `required`; `البطانة` (fabric) shows it; `لوح اسفنج` (foam) hides it. Because the field carries the `required` attribute, the user is told before posting rather than at post time — which is exactly what the brief asked for. The column header uses the correct term `رقم / كود اللون`.

**Material Receipt Show has a data-display defect (I-07).** The "كمية المستند" column reads `0.0000` on every receipt, while the base-quantity column correctly reads `15.0000`. Root cause: `resources/views/inventory/receipts/show.blade.php:192` reads `$line->received_quantity`, but the column on `MaterialReceiptLine` is `quantity_received` and no accessor bridges them, so Eloquent returns null and `number_format(null, 4)` prints `0.0000`. A warehouse keeper reading "document quantity: 0.0000 متر طولي" on a receipt for 15 metres has no way to know it is a display bug.

Cost masking on receipt **detail** is correct — `@can('costing.view')` with a `سري` fallback. But the receipt **index** is ungated (I-08): `{{ number_format($receipt->total_amount, 2) }} ر.س` renders unconditionally, and the warehouse keeper — who sees `سري` elsewhere, confirming they lack `costing.view` — reads `...75 ر.س` next to `REC-2026-000014`. Supplier purchase totals leak through the list view.

Inventory lots, balances, issues, returns and adjustments all render cleanly with correct empty states. Warehouse fulfilment could not be tested — no material requests exist.

---

## 13. Production Findings

**Production Order Show is well-structured, not over-dense.** It groups content into six tabs (workshop operations, material requests, quality/rework, waste, cost summary, finished-goods handover) with a header summary and a clear action row. The "مواصفات المصنع المعتمدة (Manufacturing Snapshot)" block correctly surfaces fabric supplier, fabric type and colour code. Cost masking is correct — a production worker sees `سري` in every cost position and no currency symbol appears anywhere on the page for that role.

**Production Review is missing critical manufacturing information (I-06).** This is the Production Manager's approval screen, and it shows customer, PO reference, sales channel, dates, model, customer-requested size, a reference-size input, BOM selection and quantity. It does **not** show fabric supplier, fabric type or colour code — confirmed at source: `resources/views/sales/orders/review.blade.php` contains no reference to `fabric`, `قماش` or `اللون` at all. It also shows no payment or production eligibility indicator. The manager is asked to approve an order for manufacturing while the fabric specification — captured at the order line and propagated into production requirements — is not on screen.

Department queue, production board, quality incidents, rework and waste index screens all render cleanly. Progress entry, material requirements for split production, and rework distinction could not be exercised: the only production order is in `مسودة` state with nothing released to the workshop.

Three create routes (`/production/material-requests/create`, `/production/quality-incidents/create`, `/production/waste/create`) return 404 on direct access because they require a `production_order_id` query parameter and use `findOrFail(null)`. This is contextual-creation by design and the sidebar correctly does not link to them, so it is low severity — but the bare 404 gives a bookmarked or mistyped URL no explanation (I-17).

---

## 14. Procurement Findings

**This module is not shippable in its current state.**

`Material` defines `baseUnit()` and `purchaseUnit()`. It does not define `unitOfMeasure()`. Nine code sites call it anyway:

- `PurchaseOrderController.php:68, 99`
- `PurchaseRequestController.php:57, 91`
- `SupplierQuotationController.php:43, 65, 94, 113`
- `ProcurementReportingService.php:67`
- plus five Blade views (`purchasing/orders/create`, `orders/show`, `quotations/create`, `quotations/show`, `requests/create`)

Live result, captured this session:

| Route | Status |
|---|---|
| `/purchasing/requests/create` | **500** |
| `/purchasing/quotations/create` | **500** |
| `/purchasing/orders/create` | **500** |

`storage/logs/laravel.log` records the matching exception at 13:08 today: `Call to undefined relationship [unitOfMeasure] on model [App\Models\Material]`, six occurrences.

The show and index routes currently return 200 **only because there is no procurement data**. `with('lines.material.unitOfMeasure')` on an empty result set never resolves the relation. The moment a purchase order, purchase request or supplier quotation exists, `/purchasing/orders/{id}` and `/purchasing/quotations/{id}` will throw the same exception. Supplier comparison readability, unit conversion display, normalised price and lead time could not be assessed for the same reason.

Procurement Planning renders and its available-versus-incoming distinction is present, but the screen has accessibility problems: 16 of 19 inputs have no programmatic label and nine controls are below 32 px.

---

## 15. Delivery Findings

**Status transition gating is correct and Stage 13.5's fix is confirmed.** In `delivery/orders/show.blade.php` every action is gated on both the exact prerequisite status and the permission: assign appears only at `READY_FOR_DELIVERY`, dispatch only at `ASSIGNED`, "إكمال التسليم للعميل" only at `OUT_FOR_DELIVERY`, install only at `DELIVERED` with `installation_required`. A DRAFT order offers no action. The specific failure mode the brief asked about — DRAFT offering `تم التسليم` directly — does not occur.

**But DRAFT is a dead end (I-05).** `DeliveryOrderService::markReady()` exists and the service's transition map allows `DRAFT → READY_FOR_DELIVERY`. There is **no route** for it — the delivery routes are index, my-tasks, create, store, show, assign, dispatch, complete, install, fail-or-reschedule — and **no button** in the show view. The only caller is `DeliveryOrderController::store()`, which invokes it when the create form is submitted with `action=ready`. The create form offers two buttons: "حفظ كمسودة" (`action=draft`) and the ready action. A user who chooses "save as draft" creates a delivery order that **can never be advanced through the UI**.

Mobile delivery UX could not be validated against real tasks — there are no delivery orders. The `/delivery/my-tasks` empty state is however excellent: "لا توجد مهام توصيل قيد التنفيذ حالياً — جميع الطلبات المسندة إليك مكتملة أو لا توجد شحنات جديدة." It explains both why it is empty and what that means.

---

## 16. Receivables Findings

The receivables screens (dashboard, payments index and create, customer balances, credit, aging report) all render cleanly at 1440 with no overflow, no console errors and correct empty states. Action-level permissions are properly applied inside the payment views — `receivables.payment.confirm`, `.reverse`, `.create` and `.allocate` each gate their own control.

The module could not be functionally validated: there is no `receivables_user` account, and only one payment record exists, so confirm, allocate, reverse and receipt printing were not exercised end-to-end.

The significant finding here is not in the receivables module itself but in how its data appears elsewhere — see I-03 below.

---

## 17. Permission Visibility Findings

Backend enforcement is solid. Direct-URL probing across 21 routes per role returned correct 403s in every case tested. Customer Service is blocked from production, inventory, purchasing, receivables, users and roles. Warehouse Keeper is blocked from sales, purchasing, receivables and product models. Production Manager is correctly blocked from creating customer orders (`/sales/orders/create` → 403) and from all receivables routes. Sidebar navigation matches permissions — no role is shown a link it cannot follow.

The `products.view` versus `products.manage` correction from Stage 13.5 holds at the backend level: Warehouse Keeper, who lacks `products.view`, receives 403 on `/products/models`. A dedicated read-only product user could not be tested because no such account is seeded.

**The failure is at the view layer, on one screen.** `sales/orders/show.blade.php` renders a block headed "الدفعات والتحصيل ومتابعة المستحقات (Payment & Receivables Status)" starting at line 158. Only the *action buttons* inside it are gated (`receivables.payment.create` at 161, `receivables.override_payment_control` at 166 and 284). **The financial values themselves are not gated by anything.** Logged in as `delivery.driver` — a role that receives 403 on every `/receivables/*` route — the order page returned:

> إجمالي مبلغ الطلب 250.00 ر.س … المسدد المؤكد / المطلوب: 0.00 / 250.00 ر.س … المتبقي القائم (Outstanding): 250.00 ر.س … حالة السداد التشغيلية: غير مدفوع … شرط السداد: سداد كامل القيمة قبل الإنتاج

Any role with `orders.view` — delivery, customer service, sales, production manager — reads the customer's outstanding balance, payment terms and payment history from the order screen. The brief's requirement that "production users should see only operational eligibility, not full financial history" is not met.

---

## 18. Accessibility Findings

Semantic foundations are present: `lang="ar"`, `dir="rtl"`, real `<button>` and `<a>` elements, `aria-expanded` on every collapsible sidebar section, and descriptive `aria-label`s on the navigation toggles. Status is not conveyed by colour alone — badges carry text.

Three systemic gaps:

**Labels are not programmatically associated.** Labels are present and visually correct, but carry no `for` attribute and the inputs have no `id`. On Customer Order Create, `تاريخ الطلب *` sits directly above `order_date` with no association; the same applies to `requested_delivery_date` and `commercial_notes`. A screen reader announces an unlabelled field. Worst case is Procurement Planning at 16 of 19 inputs, then `/recipes/components` at 7 of 13 and `/receivables/credit` at 4 of 6. In-table inputs (`lines[1][quantity]`, `lines[1][unit_price]`) rely on column headers, which is visually fine but needs `aria-label`.

**Typeaheads lack combobox semantics.** The custom control has no `role="combobox"`, no `aria-expanded`, no `aria-controls`, no `aria-activedescendant` and the result rows have no `role="option"`. Keyboard navigation itself is implemented — arrow up/down, Enter to select, Escape to close — so the interaction works; it is only unannounced.

**Icon-only buttons have no accessible name.** The typeahead clear buttons (`<i class="fas fa-times">` with no text or label) appear three times on Customer Order Create, four times on the aging report, and once each on several other screens.

Contrast was not measured systematically and 125% zoom was not tested — both remain gaps.

---

## 19. Browser Console Findings

**Clean.** Twenty-nine distinct screens were loaded with instrumented `error` and `unhandledrejection` listeners: dashboard, customer order create/show/review, production order, material receipt create, inventory balances, procurement planning, delivery my-tasks, receivables dashboard, recipes, product models, quotation create, inventory issues/adjustments/returns create, recipe and template create, production queue/board/material-requests, finished goods, delivery orders, customer returns, payments create, credit, customer balances, users, materials and roles.

**Result: zero JavaScript errors, zero unhandled promise rejections, zero failed resource loads (no 404 assets, no 4xx/5xx sub-resources).**

Specifically, `ReferenceError: tr is not defined` **did not occur on any page**. That regression is resolved.

The harness was validated before the sweep by injecting a deliberate `ReferenceError` into both the parent page and an iframe; both were captured, confirming the zero result is a real signal and not a broken listener.

---

## 20. Network Findings

The three search endpoints behave correctly and return appropriately bounded, non-sensitive payloads:

| Endpoint | Parameters observed | Result |
|---|---|---|
| `/api/search/product-models` | `?q=ميل` | 200, 4 scoped results, fields limited to `id`, `code`, `name`, `label` |
| `/api/search/product-configurations` | `?model_id=1`, `?model_id=16` | 200, correctly scoped to the selected model |
| `/api/search/suppliers` | `?q=الرا`, `?q=الراجحي`, `?q=ابداع`, `?q=ركن` | 200, correct substring matching on Arabic |
| `/api/search/fabric-materials` | `?q=&supplier_id=3` / `&supplier_id=1` | 200; supplier 3 → `البطانة`; supplier 1 (foam) → empty. Correct FABRIC-category and supplier-mapping filtering. |

No sensitive fields are leaked in search responses — payloads contain only id, code, name and label.

One anomaly: a single `GET /api/search/suppliers?q=ا → 401 Unauthorized` was captured during normal interaction, while the identical query returned 200 before and after. It was not reproducible under a deliberate eight-request burst. It may well have been caused by the audit's own parallel request sweeps interacting badly with `SESSION_DRIVER=file` (concurrent session-file writes on Windows), in which case it is an artefact of the test method rather than a product defect. **It is not reported as a defect.** Its importance is as the trigger that exposed I-04 — whatever the cause of a failed request, the UI must not report it as "no results".

---

## 21. Laravel Runtime Findings

`storage/logs/laravel.log` was monitored throughout. Exceptions generated by this audit:

| Time | Route(s) | Role | Exception | Root cause |
|---|---|---|---|---|
| 13:08:26–13:08:52 (×6) | `/purchasing/requests/create`, `/purchasing/quotations/create`, `/purchasing/orders/create` | admin | `RelationNotFoundException: Call to undefined relationship [unitOfMeasure] on model [App\Models\Material]` | `Material` defines `baseUnit()`, not `unitOfMeasure()` |

No other runtime exceptions were produced by any navigation, form interaction or role switch during the audit. Older entries in the log (pre-dating this session) relate to earlier migration and view-compilation work and are not current defects.

---

## 22. Issue Register

| ID | Sev | Module | Route | Role | Viewport | Problem | Evidence | UX Impact | Recommended Fix |
|---|---|---|---|---|---|---|---|---|---|
| I-01 | **CRITICAL** | Global | All forms | All | All | Validation errors render as raw framework keys | Submit on `/sales/orders/create` produced `يرجى تصحيح أخطاء الإدخال التالية: validation.required validation.required validation.required`. No `lang/` directory exists; `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=ar` | User cannot tell what is wrong or which field. Affects every form in the system | Add `lang/ar/validation.php` (and `lang/en/` fallback). Add `attributes` mappings so field names appear in Arabic. Bind errors to fields via `.invalid-feedback` |
| I-02 | **CRITICAL** | Procurement | `/purchasing/requests/create`, `/purchasing/quotations/create`, `/purchasing/orders/create` | admin (all) | All | HTTP 500 — undefined relationship | Live 500s; `laravel.log` 13:08 ×6: `Call to undefined relationship [unitOfMeasure]`. 9 code sites | Entire procurement creation workflow is unusable. Show screens will fail as soon as data exists | Rename `unitOfMeasure` → `baseUnit` at all 9 sites, or add a `unitOfMeasure()` alias on `Material` |
| I-03 | **HIGH** | Sales / Receivables | `/sales/orders/{id}` | delivery_user (and any `orders.view` role) | All | Receivables panel ungated — outstanding balance, totals and payment terms exposed | As `delivery.driver` (403 on all `/receivables/*`): "المتبقي القائم (Outstanding): 250.00 ر.س"; `show.blade.php:158` block has no `@can` around values | Customer financial position visible to delivery and production staff. Violates sensitive-data requirement | Wrap the value block (lines ~155–280) in `@can('receivables.view')`; show only an operational eligibility badge to others |
| I-04 | **HIGH** | Global typeahead | All search fields | All | All | Failed search requests display as "لا توجد نتائج مطابقة" | `errorMessage` is set in `app.js:268` but **never rendered in any view** (`grep errorMessage resources/views/` → no matches). Reproduced by forcing 401 on a valid `الراجحي` query; also observed live on a real 401 | User is told data does not exist when the request failed. Causes wrong business decisions (e.g. "this supplier has no fabric") | Render `errorMessage` in every typeahead dropdown, visually distinct from the empty state, with a retry affordance |
| I-05 | **HIGH** | Delivery | `/delivery/orders/{id}` | delivery/admin | All | DRAFT delivery orders cannot be advanced | `markReady()` exists and `DRAFT → READY_FOR_DELIVERY` is permitted, but no route and no button exist; only caller is `store()` with `action=ready`. Create form offers "حفظ كمسودة" | Any order saved as draft is permanently stuck; must be recreated | Add a `markReady` route and a gated button on the show screen for `status === 'DRAFT'` |
| I-06 | **HIGH** | Sales / Production | `/sales/orders/{id}/review` | production_manager | All | Fabric supplier, fabric type and colour code absent from Production Review; no payment eligibility indicator | `review.blade.php` contains no `fabric`, `قماش` or `اللون` reference | Manager approves manufacturing without seeing the fabric specification that drives material requirements | Add fabric supplier / type / colour to each review line; add a payment-eligibility badge |
| I-07 | **HIGH** | Inventory | `/inventory/receipts/{id}` | All | All | "كمية المستند" always shows `0.0000` | Receipts 6, 7 and 8 all show `0.0000` document qty against `15.0000` base qty. `show.blade.php:192` reads `$line->received_quantity`; column is `quantity_received`; no accessor | Warehouse staff cannot verify received quantity against the supplier document | Change to `$line->quantity_received` (or add an accessor) |
| I-08 | MEDIUM | Inventory | `/inventory/receipts` | warehouse_keeper | All | Receipt totals ungated | `index.blade.php:103` renders `total_amount` with no `@can('costing.view')`; warehouse keeper sees `...75 ر.س` while seeing `سري` on detail | Supplier purchase costs leak to a role without costing permission | Wrap the column in `@can('costing.view')` with a `سري` fallback |
| I-09 | MEDIUM | Global | 15 screens | All | All | Duplicate validation error summary | `partials.flash` included globally at `layouts/app.blade.php:40` **and** 15 views render their own `$errors->any()` block. Two `.alert-danger` observed on order create | Same error list shown twice; pushes the form below the fold | Remove the per-view blocks and keep the global partial |
| I-10 | MEDIUM | Global | All forms | All | All | Labels not programmatically associated (no `for`/`id`) | `order_date`, `requested_delivery_date`, `commercial_notes` on order create; 16/19 on procurement planning; 7/13 on recipe components; 4/6 on credit | Screen-reader users hear unlabelled fields | Add `id` to inputs and `for` to labels; `aria-label` for in-table inputs |
| I-11 | MEDIUM | Global typeahead | All search fields | All | All | No ARIA combobox semantics | No `role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`; rows lack `role="option"` | Assistive tech cannot announce results or the active option (keyboard nav itself works) | Add combobox ARIA pattern to `typeaheadSelect` |
| I-12 | MEDIUM | Production | `/production/orders/{id}` | All | **768 only** | 50 px page-level horizontal scroll | At 768 px the `≤767.98` MQ is off so sidebar keeps 248 px, main = 505 px; `span.badge.bg-secondary.fs-6` ("0 هدر", 60 px) lands at `left:-35`. Clean at 390/1024/1366/1440/1920 | Horizontal page scroll on iPad portrait | Move the sidebar breakpoint to `991.98px`, or allow the stats row to wrap below 992 px |
| I-13 | MEDIUM | Dashboard | `/dashboard` | delivery_user, production_worker | All | Dashboard shows no role-relevant work | Delivery driver sees only "طلبات بانتظار المراجعة" (a production-review metric) and no delivery tasks; worker sees factory-wide counts, not their department queue; no metrics defined for purchasing or receivables | The primary landing screen is not actionable for three of nine roles | Add delivery, department-queue, procurement and receivables metrics to `DashboardController`, gated per permission |
| I-14 | MEDIUM | Dashboard | `/dashboard` | All | All | Master-data census outranks operational indicators | Users/customers/suppliers/departments/units/channels rendered above the metrics block | Operators scan configuration counts before seeing what needs attention | Promote operational metrics to the top; demote master-data counts to a collapsed footer or admin-only strip |
| I-15 | MEDIUM | Global RTL | Multiple | All | All | Technical codes inside Arabic text lack bidi isolation | 20 instances on receipt create, 38 on inventory balances, 15 on product models, 8 on receipts index — e.g. `شركة الراجحي للأقمشة والمنسوجات (SUP-000010)` computed `direction: rtl` with no isolation | Bracketed codes can reorder visually in RTL | Wrap codes in `<bdi>` / `unicode-bidi: isolate`; for `<option>` use Unicode isolate characters or lead with the code |
| I-16 | MEDIUM | Procurement | `/purchasing/planning` | All | All | 16/19 inputs unlabelled; 9 controls under 32 px | Probe at 1440 and 390 | Hard to use with assistive tech; small touch targets | Add label associations; raise control height to ≥ 32 px (44 px on touch) |
| I-17 | LOW | Production / Delivery / FG | 6 context-required create routes | All | All | Bare 404 on direct URL | `/production/material-requests/create`, `/production/quality-incidents/create`, `/production/waste/create`, `/finished-goods/receipts/create`, `/delivery/orders/create`, `/customer-returns/create` → 404 without a context param (`findOrFail(null)`) | Bookmarked or mistyped URL gives no explanation. Not reachable from the sidebar, so impact is low | Return a friendly page explaining the screen must be opened from its parent record |
| I-18 | LOW | Products | Configuration dropdown | All | All | Dimensions show trailing decimals and no unit | `90.00×190.00 — بدون تخزين — CFG-000001` | Noisier than necessary; brief specifies `160×200 سم` | Render as `90×190 سم` (trim trailing zeros, append unit) |
| I-19 | LOW | Seeding / QA | — | sales, purchasing, receivables | — | Three of nine roles have no seeded user account | `RoleAndPermissionSeeder` defines 9 roles; `DevelopmentSeeder` creates 6 users | Those roles cannot be tested and have likely never been exercised through the UI | Add `sales.user`, `purchasing.user`, `receivables.user` to `DevelopmentSeeder` |
| I-20 | LOW | Shell | All authenticated pages | All | All | Two duplicate logout forms in the DOM | `document.querySelectorAll('form')` → indices 0 and 1 both `POST /logout` | Harmless, but a scripting/testing hazard | Render one logout form and reference it from both toggles |
| I-21 | LOW | Shell | `/login` vs app shell | All | All | Font stack differs between login and app | Login: `Cairo, Tajawal, "Segoe UI"…`; app: `Cairo, "Segoe UI"…` | Minor inconsistency; Cairo loads in both | Unify the stack |

---

## 23. Viewport Matrix

| Screen | 1920 | 1440 | 1366 | 1024 | 768 | 390 |
|---|---|---|---|---|---|---|
| Login | PASS | — | — | — | — | PASS |
| Dashboard | PASS | PASS | PASS | PASS | PASS | PASS |
| Customer Order (create/show) | PASS | PASS | PASS | PASS | PASS | PASS |
| Production Review | — | PASS | PASS | PASS | PASS | PASS |
| Production Order | — | PASS | PASS | PASS | **FAIL** | PASS |
| Material Receipt | PASS | PASS | PASS | PASS | PASS | PASS |
| Procurement (planning) | — | WARNING | WARNING | WARNING | WARNING | WARNING |
| Procurement (create screens) | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** |
| Delivery (my-tasks) | — | PASS | PASS | PASS | PASS | PASS |
| Delivery (order detail) | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED |
| Receivables (dashboard) | — | PASS | PASS | PASS | PASS | PASS |

PASS = rendered and inspected, no layout defect. WARNING = renders correctly but has accessibility/target-size defects (I-16). FAIL = page-level horizontal scroll (I-12). BLOCKED = could not render (HTTP 500) or no data exists. "—" = not tested at that viewport.

---

## 24. Role UX Matrix

| Role | UX Status | Main Problems |
|---|---|---|
| Admin | GOOD | Dashboard leads with master-data census (I-14); procurement create screens 500 (I-02) |
| Production Manager | ACCEPTABLE | Production Review missing fabric spec and payment eligibility (I-06); sees full purchasing section and master-data block — clutter; sees customer financials on order screen (I-03) |
| Customer Service | GOOD | Best-scoped role. Untranslated validation on every order form (I-01); duplicate error summary (I-09); sees customer financials (I-03) |
| Warehouse Keeper | ACCEPTABLE | Receipt totals leak on index (I-08); "كمية المستند" always 0.0000 (I-07); fulfilment untestable — no material requests |
| Production Worker | NEEDS WORK | Dashboard shows factory-wide counts, not their department queue (I-13). Cost masking is correct (`سري`) — good |
| Delivery / Installation | NEEDS WORK | Dashboard shows a production-review metric and no delivery work (I-13); reads customer outstanding balance (I-03); DRAFT orders stuck (I-05); no data to validate against |
| Sales | NOT TESTED | No seeded account (I-19) |
| Purchasing | NOT TESTED / BROKEN | No seeded account (I-19); their three primary create screens return 500 (I-02) |
| Receivables | NOT TESTED | No seeded account (I-19) |

---

## 25. Module UX Health Scores

| Module | Score | Comment |
|---|---|---|
| Shell / Navigation | 9 | Excellent. Correct off-canvas drawer, scroll lock released properly, section auto-expansion, permission-matched links. Minor: duplicate logout forms |
| Dashboard | **4** | Permissions correct and no dead links, but priorities inverted, three roles get nothing actionable, and zero-cards carry no guidance (I-13, I-14) |
| Master Data | 8 | Clean, consistent, correct empty states |
| Materials | 8 | Renders cleanly; catalogue and categories fine |
| Inventory | **6** | Receipt fabric-colour logic is exemplary, but document quantity is always 0.0000 (I-07) and totals leak on the index (I-08) |
| Products | 8 | Good. Model/configuration separation is clear. Dimension formatting is noisy (I-18) |
| Recipes / BOM | **7** | Clean rendering, no defects found — but only two recipes exist, so version states, approval and immutability were not exercised |
| Sales | **7** | The strongest interaction work in the app; held down by untranslated validation (I-01), duplicate error blocks (I-09) and the financial panel leak (I-03) |
| Production | **6** | Production Order Show is well-structured with correct cost masking; Production Review is missing the fabric spec (I-06); 768 px overflow (I-12) |
| Quality | **7** | Index screens clean with clear labels; detected-vs-responsible department distinction present in the data model but not exercised — no incidents exist |
| Procurement | **2** | Three create screens return 500 today and the show screens will fail as soon as data exists (I-02). Planning screen has significant a11y gaps (I-16) |
| Finished Goods | **7** | Renders cleanly with good empty states; handover flow untestable — no completed production |
| Delivery | **5** | Transition gating is correct and the empty states are the best in the app, but DRAFT is a dead end (I-05) and nothing could be tested against real data |
| Receivables | **6** | Screens render well and action permissions are correct, but the module is untestable (no account, one payment) and its data leaks via the order screen (I-03) |
| Mobile UX | 8 | Genuinely good at 390 px. No page scroll anywhere, correct stacking, 44 px touch targets on form actions |
| Accessibility | **5** | Good semantics and keyboard support, undermined by missing label associations, missing combobox ARIA and unnamed icon buttons (I-10, I-11) |
| RTL consistency | **7** | Core RTL is correct and codes are isolated in the typeahead; codes inside Arabic sentence text and `<option>` labels are not (I-15) |

Scores below 8 are explained in the comment column; the drivers are, in order of weight: the procurement 500s, the dashboard's inverted priorities, the accessibility gaps, and the large amount of the system that simply has no data to exercise.

---

## 26. Improvement Backlog

### P0 — Must fix before GO

1. **I-01** Add `lang/ar/validation.php` with a full Arabic message set and an `attributes` map, plus an `en` fallback. Verify on at least Customer Order Create, Material Receipt Create and Purchase Request Create.
2. **I-02** Replace `unitOfMeasure` with `baseUnit` at all nine sites, or add an alias relationship on `Material`. Then re-test all `/purchasing/*` create and show routes **with data present**, not just empty.
3. **I-03** Gate the receivables value block on `/sales/orders/{id}` behind `receivables.view`; expose only an operational eligibility badge to production, delivery and sales roles.
4. **I-04** Render `errorMessage` in every typeahead dropdown, visually distinct from the empty state.
5. **I-05** Add the `markReady` route and a status-gated button so DRAFT delivery orders can be advanced.
6. **I-06** Add fabric supplier, fabric type and colour code — plus a payment-eligibility badge — to Production Review.
7. **I-07** Fix `received_quantity` → `quantity_received` on the receipt show view.

### P1 — Important

8. **I-08** Gate receipt totals on the receipts index behind `costing.view`.
9. **I-09** Remove the 15 per-view error blocks; keep the global flash partial.
10. **I-13** Add delivery, department-queue, procurement and receivables dashboard metrics.
11. **I-10** Add `id`/`for` label associations across forms, starting with Procurement Planning, Customer Order Create and Recipe Components.
12. **I-12** Fix the 768 px production-order overflow (move the sidebar breakpoint to 991.98 px).

### P2 — Improvement

13. **I-14** Re-order the dashboard so operational metrics lead; add guidance text to zero-value cards.
14. **I-11** Add combobox ARIA semantics to `typeaheadSelect`.
15. **I-15** Add bidi isolation for technical codes inside Arabic text.
16. **I-16** Raise touch targets and label inputs on Procurement Planning.
17. **I-19** Seed `sales_user`, `purchasing_user` and `receivables_user` accounts so those roles can be validated at all.

### P3 — Polish

18. **I-18** Format dimensions as `90×190 سم`.
19. **I-17** Replace bare 404s on context-required create routes with an explanatory page.
20. **I-20** De-duplicate the logout form.
21. **I-21** Unify the font stack between login and the app shell.

### Terminology

Operational Arabic terminology is **consistent** across the modules audited. `طلب عميل`, `طلب شراء`, `سند استلام`, `ترحيل`, `وصفة تصنيع`, `تكوين تصنيعي`, `المنتجات الجاهزة`, `أمر توصيل`, `دفعة`, `المتبقي` and `رقم / كود اللون` are each used with a single meaning throughout. No inconsistent translations were found. This is a genuine strength and should be protected during the fixes above.

---

## 27. Final Stage 13.5 UI Decision

## **NO-GO — UI stabilization required**

Stage 13.5 cannot be upgraded to "GO — Safe to Continue".

The GO criteria require no Critical UI issue, no unresolved High issue preventing a core workflow, all critical user journeys working, and correct role visibility. Three of those four fail:

- **Two Critical issues are open.** Untranslated validation messages (I-01) affect every form in the system. The procurement `unitOfMeasure` defect (I-02) returns HTTP 500 on three create screens today and will extend to the show screens as soon as procurement data exists.
- **A core workflow is blocked.** Journey 4 (Purchasing) cannot start. Journey 6 (Delivery) has an unrecoverable dead end at DRAFT.
- **Role visibility is not correct.** A delivery driver can read a customer's outstanding balance, payment terms and payment history (I-03), despite being correctly blocked from every receivables route at the backend.

Two of the four criteria do pass, and they pass convincingly: the browser console is clean across twenty-nine screens with the `tr is not defined` regression resolved, and responsive behaviour is acceptable at all six viewports with a single boundary defect at 768 px.

**The path to GO is short.** The seven P0 items are localised fixes — one language file, one relationship rename across nine call sites, one permission wrapper, one error-state render, one route plus one button, one set of fields added to a review screen, and one property-name correction. None requires redesign, none touches business logic, and none touches the database schema.

**One condition on the re-audit.** A meaningful share of this system could not be exercised because it has no data: no delivery orders, no purchase orders or requests or supplier quotations, no material requests, one production order in draft, one customer, and no accounts for three of nine roles. Re-testing after the P0 fixes must be done against a seeded dataset that covers delivery, procurement, material requests and receivables — otherwise the same classes of defect that are currently masked by empty result sets (such as the procurement show screens) will surface in production instead of in the audit.

---

*Findings were verified in a running browser against the live application and correlated with source. Where a hypothesis did not survive verification — the mobile drawer, dashboard permission filtering, production-order cost masking — it was discarded rather than reported. Two effects were traced to the audit method itself (a session drop caused by parallel request sweeps against the file session driver, and frozen CSS transitions caused by a hidden browser pane) and are documented as limitations rather than defects.*
