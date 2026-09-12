# وثيقة المصادقة والمستخدمين والأدوار والصلاحيات (Stage 2)
## Users, Roles & Permissions Specification (docs/04-users-roles-permissions.md)

تحدد هذه الوثيقة تفاصيل نظام المصادقة وإدارة المستخدمين وتوزيع الصلاحيات في نظام مصنع مفروشات سدير الداخلي، وفق أفضل المعايير المتوافقة مع طبيعة عمل المصانع واستضافات Hostinger Shared Hosting.

---

### 1. منهجية المصادقة وحماية الجلسات (Authentication Architecture)

1. **مصادقة معتمدة على الجلسات (Session-Based Auth):**
   - استخدام محرك الجلسات الأصلي في لاراڤيل (`SESSION_DRIVER=file`).
   - تجنب خدمات المصادقة الخارجية أو التوكنات عديمة الحالة (Stateless JWT/Sanctum Tokens) لعدم الحاجة إليها ولتلافي التعقيد.
2. **تسجيل الدخول باسم المستخدم (Username-Centric Login):**
   - تفضيل اسم المستخدم (`username`) كمعرف دخول أساسي لفنيي وموظفي المصنع، مع إتاحة البريد الإلكتروني كحقل اختياري.
3. **تجديد معرف الجلسة وحماية CSRF (Session Regeneration & CSRF):**
   - تجديد معرف الجلسة (`$request->session()->regenerate()`) عند كل تسجيل دخول ناجح لمنع هجمات تثبيت الجلسة (Session Fixation).
   - تفعيل حماية رموز CSRF على كافة النماذج.
4. **تحديد معدل محاولات الدخول (Rate Limiting):**
   - حماية تسجيل الدخول بحد أقصى 5 محاولات متتالية لكل اسم مستخدم وعنوان IP، مع إيقاف مؤقت لمدة 60 ثانية عند التجاوز.
5. **منع التسجيل العام (No Public Registration):**
   - النظام نظام داخلي مغلق للمصنع؛ يتم إنشاء حسابات الموظفين حصرياً من قبل مدير النظام (`admin`).

---

### 2. دورة حياة المستخدم وحالة الحساب (User Lifecycle & Status)

1. **إنشاء الحساب (User Provisioning):**
   - يقوم مدير النظام بإنشاء الحساب وتحديد الدور الوظيفي وكلمة المرور الابتدائية.
2. **تتبع النشاط (Audit Timestamp):**
   - تسجيل وقت وتاريخ آخر تسجيل دخول لكل مستخدم (`last_login_at`) لأغراض الرقابة وتتبع الحسابات غير النشطة.
3. **التعطيل بدلاً من الحذف (Deactivation over Deletion):**
   - **قاعدة حازمة:** لا يتم حذف حسابات الموظفين نهائياً من قاعدة البيانات لتجنب كسر وتشويه السجلات التاريخية للإنتاج وحركات صرف المواد وعمليات الاعتماد.
   - يتم تفعيل/تعطيل الحساب عبر الحقل (`is_active`).
4. **سلوك المستخدم المعطل (Inactive User Behavior):**
   - يمنع المستخدم المعطل من تسجيل الدخول وتظهر له رسالة خطأ عربية واضحة: `"تم تعطيل هذا الحساب. يرجى مراجعة إدارة النظام."`.
   - يقوم الميدل وير (`EnsureUserIsActive`) بفحص حالة التفعيل في كل طلب؛ فإذا عُطّل الحساب أثناء جلسة نشطة يتم تسجيل خروجه فوراً وإبطال جلسته.
5. **حماية الحساب الذاتي للمشرف:**
   - يمنع مدير النظام من إلغاء تفعيل حسابه الشخصي المسجل به حالياً لمنع الإغلاق العرضي للنظام (Lockout Prevention).

---

### 3. المبدأ المعماري: الفصل الصارم بين الدور والقسم (Role vs. Department)

من أهم القواعد التصميمية المؤصلة للنظام التفريق بين **الدور الوظيفي** و **القسم التشغيلي**:
- **الدور (Role):** يعبر عن مستوى **الصلاحيات والأذونات الأمنية** في النظام (مثل: `موظف قسم إنتاج`، `مدير الإنتاج`).
- **القسم (Department):** يعبر عن **التبعية التشغيلية الميدانية** في صالة الإنتاج (مثل: النجارة، التسفيج، التنجيد، التجميع، التغليف، إنتاج البوكسات).

#### لماذا هذا الفصل؟
- موظف قسم النجارة وموظف قسم التنجيد يملكان نفس الدور التقني (`production_worker`)، لكن كل منهما يعمل في محطة تشغيلية مختلفة.
- **تجهيز المخطط:** تم تضمين حقل اختياري (`department_id`) في جدول المستخدمين لربط التوزيع التشغيلي لاحقاً في مراحل الإنتاج دون خلق أدوار أمنية زائدة لا مبرر لها.

---

### 4. تعريف الأدوار الوظيفية القياسية (Core Roles)

| رمز الدور (Slug) | المسمى العربي | الوصف والمسؤوليات |
|---|---|---|
| `admin` | **مدير النظام** | الإشراف الإداري والتقني، إدارة المستخدمين، وتعديل الصلاحيات والإعدادات. |
| `production_manager` | **مدير الإنتاج** | مراجعة واعتماد الطلبات، إطلاق أوامر التصنيع، متابعة WIP، وقرارات الهدر وإعادة التصنيع. |
| `customer_service` | **خدمة العملاء** | إدخال طلبات المتجر والمعارض والعملاء، متابعة الألوان والأقمشة (دون صلاحية اعتماد التصنيع). |
| `warehouse_keeper` | **أمين المستودع** | استلام المواد، فحص الجودة المبدئي، تتبع لوت القماش والخشب، وصرف وإرجاع الخامات. |
| `production_worker` | **موظف قسم إنتاج** | تسجيل وتحديث تقدم إنجاز مراحل التصنيع الخمس (WIP) في صالة المصنع. |
| `delivery_user` | **التوصيل والتركيب** | متابعة جدول التوصيل والتركيب الميداني وتحديث حالة التسليم. |
| `sales_user` | **المبيعات** | إعداد ومتابعة عروض الأسعار ومبيعات الجملة والمعارض والعملاء المباشرين. |

---

### 5. اتفاقية تسمية الصلاحيات (Permission Naming Convention)

تتبع الصلاحيات نسقاً قياسياً موحداً بالإنجليزية: `{module}.{action}`
مثال:
- `orders.view`: استعراض قائمة وتفاصيل الطلبات.
- `orders.approve_production`: اعتماد الطلب وبدء مرحلة التصنيع.
- `inventory.issue`: صرف مواد من المستودع لأمر إنتاج.

---

### 6. مصفوفة الصلاحيات الافتراضية الكاملة (Default Permission Matrix)

يحتوي النظام على **44 صلاحية قياسية** موزعة على 13 وحدة وظيفية:

| الوحدة (Module) | الصلاحية (Permission) | المسمى العربي | الأدوار الممنوحة افتراضياً |
|---|---|---|---|
| **Users** | `users.view` | عرض سجل المستخدمين | `admin` |
| | `users.create` | إنشاء مستخدم جديد | `admin` |
| | `users.update` | تعديل بيانات المستخدمين | `admin` |
| | `users.activate` | تفعيل حساب مستخدم | `admin` |
| | `users.deactivate` | تعطيل حساب مستخدم | `admin` |
| **Roles** | `roles.view` | عرض الأدوار والصلاحيات | `admin` |
| | `roles.manage` | تعديل مصفوفة صلاحيات الأدوار | `admin` |
| **Customers** | `customers.view` | عرض سجل العملاء | `admin`, `production_manager`, `customer_service`, `sales_user` |
| | `customers.create` | إضافة عميل جديد | `admin`, `customer_service`, `sales_user` |
| | `customers.update` | تعديل بيانات العميل | `admin`, `customer_service`, `sales_user` |
| **Quotations** | `quotations.view` | عرض عروض الأسعار | `admin`, `production_manager`, `customer_service`, `sales_user` |
| | `quotations.create` | إنشاء عرض سعر جديد | `admin`, `sales_user` |
| | `quotations.update` | تعديل عروض الأسعار | `admin`, `sales_user` |
| | `quotations.approve` | اعتماد عروض الأسعار | `admin` |
| **Orders** | `orders.view` | عرض طلبات العملاء | `admin`, `production_manager`, `customer_service`, `delivery_user`, `sales_user` |
| | `orders.create` | إنشاء طلب عميل جديد | `admin`, `customer_service` |
| | `orders.update` | تعديل بيانات الطلب | `admin`, `customer_service` |
| | `orders.request_change`| طلب تعديل على أمر تصنيع | `admin`, `customer_service` |
| | `orders.review_production`| مراجعة الجدوى والمواصفات | `admin`, `production_manager` |
| | `orders.approve_production`| اعتماد الطلب للتصنيع | `admin`, `production_manager` |
| **Production** | `production.view` | استعراض أوامر وخطوط الإنتاج | `admin`, `production_manager`, `warehouse_keeper`, `production_worker` |
| | `production.release` | إطلاق أوامر الإنتاج للورشة | `admin`, `production_manager` |
| | `production.update_progress`| تحديث إنجاز المراحل (WIP) | `admin`, `production_manager`, `production_worker` |
| | `production.complete` | تأكيد اكتمال أمر الإنتاج | `admin`, `production_manager` |
| | `production.manage_rework`| قرارات الهدر والتصنيع المعاد | `admin`, `production_manager` |
| **Inventory** | `inventory.view` | عرض أرصدة وحركات المستودع | `admin`, `production_manager`, `warehouse_keeper` |
| | `inventory.receive` | استلام توريد خامات | `admin`, `warehouse_keeper` |
| | `inventory.issue` | صرف المواد لأوامر الإنتاج | `admin`, `warehouse_keeper` |
| | `inventory.return` | تسجيل مرتجع خامات صالحة | `admin`, `warehouse_keeper` |
| | `inventory.adjust` | تسوية وجرد المخزون | `admin` |
| **Materials** | `materials.view` | عرض كتالوج الخامات واللوت | `admin`, `production_manager`, `warehouse_keeper` |
| | `materials.manage` | توصيف المواد واللوت | `admin` |
| **Products** | `products.view` | عرض دليل الموديلات والأسرة | `admin`, `production_manager`, `customer_service`, `production_worker` |
| | `products.manage` | إدارة الموديلات والوصفات | `admin` |
| **Suppliers** | `suppliers.view` | عرض قائمة الموردين | `admin`, `warehouse_keeper` |
| | `suppliers.manage` | إدارة بيانات الموردين | `admin` |
| **Costing** | `costing.view` | استعراض تقارير التكلفة | `admin`, `production_manager` |
| | `costing.manage` | إدارة معايير التكلفة | `admin` |
| **Delivery** | `delivery.view` | استعراض جدول التوصيل | `admin`, `customer_service`, `delivery_user` |
| | `delivery.update` | تحديث حالة التسليم والتركيب | `admin`, `delivery_user` |
| **Reports** | `reports.view` | عرض التقارير العامة | `admin`, `production_manager`, `sales_user` |
| | `reports.financial` | عرض التقارير المالية | `admin` |
| | `reports.production` | عرض تقارير إنتاجية الأقسام | `admin`, `production_manager` |
| | `reports.inventory` | عرض تقارير حركة المخزون | `admin`, `production_manager`, `warehouse_keeper` |

---

### 7. تطبيق الصلاحيات على مستوى الكود والواجهات

1. **على مستوى الباك إند (Controllers & Requests):**
   - استخدام `$request->user()->can('permission.name')` أو ميثود التحقق المباشر `abort_if(! $request->user()->can(...), 403)`.
   - استخدام Form Requests التي تفحص الصلاحية في دالة `authorize()`.
2. **على مستوى التوجيه (Routes):**
   - استخدام الميدل وير المخصص `permission:permission.name` أو مجموعات التوجيه المحمية.
3. **على مستوى الواجهات (Blade Views):**
   - إخفاء الأزرار والروابط الحساسة باستخدام توجيه لاراڤيل الشرطي `@can('permission.name')`.
   - **قاعدة أمنية:** إخفاء الزر في الواجهة هو لتحسين تجربة المستخدم فقط، بينما الباك إند هو الحارس الأمني الإلزامي.

---

### 8. حسابات التطوير المجهزة (Development Seed Accounts)

| اسم المستخدم | كلمة المرور الابتدائية | الدور المسند | الاستخدام |
|---|---|---|---|
| `admin` | `admin123` | مدير النظام (`admin`) | حساب مدير النظام لاختبار كافة الصلاحيات. |
| `prod.manager` | `password` | مدير الإنتاج (`production_manager`) | اختبار مراجعة واعتماد أوامر الإنتاج والهدر. |
| `cs.agent` | `password` | خدمة العملاء (`customer_service`) | اختبار إدخال الطلبات والتحقق من منع اعتماد التصنيع. |
| `warehouse.keeper` | `password` | أمين المستودع (`warehouse_keeper`) | اختبار استلام وصرف الخامات والتحقق من منع إدارة المستخدمين. |
| `inactive.user` | `password` | موظف قسم إنتاج (`production_worker`) | اختبار رفض تسجيل دخول الحسابات المعطلة. |
