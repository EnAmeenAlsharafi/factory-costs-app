# وثيقة الموديلات وتوصيفات المنتجات والمسميات الترويجية (Stage 6)

## 1. نظرة عامة والهدف المعماري
تهدف هذه المرحلة إلى تعريف المنتجات التي يقوم مصنع سدير بتصنيعها، وذلك بالثلاثية المعمارية التالية:
1. **موديل المصنع الداخلي (`product_models`)**: الكيان المستقل للتصميم (مثل: موديل أڤالون، ريڤا).
2. **التوصيف المصنعي والتكوين الموحد (`product_configurations`)**: الأبعاد والمقاسات الخشبية وخيار السحارة (تخزين)، مع الاستبعاد التام للقماش واللون من التكوين الدائم.
3. **مسميات ورموز العملاء (`customer_product_aliases`)**: الربط المرن بين الموديل الداخلي ومسميات العملاء، بما في ذلك معرض سدير كعميل رئيسي (60% من الإنتاج) والعملاء الآخرين (40%).

---

## 2. الهيكل والمخطط في قاعدة البيانات (`Database Schema`)

### 1. `standard_bed_sizes` (المقاسات القياسية لأسرة المصنع)
- `id` (PK)
- `code` (string, unique) - مثل `SIZE-160X200`
- `name_ar` (string) - `160 × 200 سم`
- `name_en` (string, nullable)
- `width_cm` (decimal 8,2) - `160.00`
- `length_cm` (decimal 8,2) - `200.00`
- `sort_order` (unsigned integer)
- `is_active` (boolean)

### 2. `product_models` (موديلات المصنع)
- `id` (PK)
- `model_code` (string, unique) - كود تلقائي آمن `MOD-XXXXXX`
- `name_ar` (string) - اسم الموديل بالرمز العربي
- `name_en` (string, nullable)
- `description` (text, nullable)
- `design_notes` (text, nullable) - ملاحظات الفنيين والتصنيع
- `reference_image_path` (string, nullable) - صورة الموديل المرجعية
- `is_custom_template` (boolean) - هجين لتصاميم العملاء الخاصة
- `is_active` (boolean)
- Unique: `[model_code]`

### 3. `product_configurations` (تكوينات وأبعاد التصنيع)
- `id` (PK)
- `configuration_code` (string, unique) - كود تلقائي آمن `CFG-XXXXXX`
- `product_model_id` (FK -> `product_models`)
- `standard_bed_size_id` (FK -> `standard_bed_sizes`, nullable)
- `width_cm` (decimal 8,2) - العرض الفعلي
- `length_cm` (decimal 8,2) - الطول الفعلي
- `has_storage` (boolean) - خيار السحارة (مع تخزين / بدون تخزين)
- `configuration_name` (string, nullable)
- `notes` (text, nullable)
- `is_active` (boolean)
- Unique: `[product_model_id, width_cm, length_cm, has_storage]`

### 4. `customer_product_aliases` (ربط ومسميات المنتجات لدى العملاء)
- `id` (PK)
- `customer_id` (FK -> `customers`)
- `product_model_id` (FK -> `product_models`)
- `customer_product_code` (string, nullable) - رمز المنتج لدى العميل
- `customer_product_name` (string) - مسمى المنتج لدى العميل
- `is_default` (boolean) - المسمى الافتراضي لهذا الموديل لدى العميل
- `notes` (text, nullable)
- `is_active` (boolean)
- Unique: `[customer_id, customer_product_code]`

---

## 3. القواعد والتصاميم المعمارية (ADRs)

1. **فصل القماش واللون عن التكوين المصنعي**:
   - القماش واللون خيارات متغيرة على مستوى أومر الإنتاج والبيع وليست أبعاداً مصنعية ثابتة.
2. **فصل السحارة (`has_storage`) كتعديل هيكلي**:
   - وجود السحارة يغير الهيكل الخشبي الداخلي وتكلفة المواد، ولذلك تُعد بعداً ثابتاً في `product_configurations`.
3. **إدارة معرض سدير كعميل سياقي**:
   - تم ربط مسميات "معرض سدير" كـ `CustomerProductAlias` كأي عميل خارجي لضمان اتساق المعمارية وعدم التمييز الصلب في الجداول.
4. **تشفير وتوليد الأكواد الآمنة**:
   - استخدام `MOD-XXXXXX` للموديلات و`CFG-XXXXXX` للتكوينات لمنع التضارب وحماية المعاملات بالتوازي.

---

## 4. القيود والمراحل المستقبلية (Stage Boundaries)
- **ممنوع في Stage 6**: بناء معادلات BOM، الوصفات، التكاليف، أسعار البيع، طلبات العملاء، أو أمر الإنتاج.
- **المرحلة القادمة (Stage 7)**: المعادلات الشجرية ووصفات تصنيع المواد (BOM - Bill of Materials).
