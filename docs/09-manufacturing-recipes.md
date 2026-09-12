# وثيقة وصفات التصنيع وإصدارات BOM والمكونات نصف المصنعة (Stage 7)

## 1. نظرة عامة والهدف المعماري
تهدف هذه المرحلة إلى تعريف كيفية تصنيع تكوينات المنتجات والمكونات نصف المصنعة عبر بناء محرك شجري لإدارة قائمة المواد (Bill of Materials - BOM).

تعتمد المعمارية على التمييز الصارم بين المفاهيم التالية:
- **الموديل (`ProductModel`)**: ماهية المنتج وتصميمه (مثل: موديل أڤالون).
- **التكوين المصنعي (`ProductConfiguration`)**: الأبعاد والمقاسات الخشبية وخيار السحارة (مثل: 160×200 سحارة).
- **وصفة التصنيع (`ManufacturingRecipe`)**: الكيفية والمواد والمكونات اللازمة لبناء هذا التكوين تحديداً.

---

## 2. الهيكل والمخطط في قاعدة البيانات (`Database Schema`)

### 1. `semi_finished_components` (المكونات نصف المصنعة)
- `id` (PK)
- `component_code` (string, unique) - كود تلقائي آمن `SFC-XXXXXX`
- `name_ar` (string) - `بوكس 160×200 سحارة`
- `name_en` (string, nullable)
- `width_cm` (decimal 8,2, nullable)
- `length_cm` (decimal 8,2, nullable)
- `has_storage` (boolean, nullable)
- `notes` (text, nullable)
- `is_active` (boolean)

### 2. `manufacturing_recipes` (رأس وصفة التصنيع)
- `id` (PK)
- `recipe_code` (string, unique) - كود تلقائي آمن `RCP-XXXXXX`
- `target_type` (enum: `PRODUCT_CONFIGURATION`, `SEMI_FINISHED_COMPONENT`)
- `product_configuration_id` (FK -> `product_configurations`, nullable)
- `semi_finished_component_id` (FK -> `semi_finished_components`, nullable)
- `name` (string)
- `description` (text, nullable)
- `is_active` (boolean)

### 3. `manufacturing_recipe_versions` (إصدارات وصفة التصنيع)
- `id` (PK)
- `manufacturing_recipe_id` (FK -> `manufacturing_recipes`)
- `version_number` (unsigned integer) - 1, 2, 3...
- `status` (enum: `DRAFT`, `APPROVED`, `SUPERSEDED`, `INACTIVE`)
- `effective_from` (timestamp, nullable)
- `effective_to` (timestamp, nullable)
- `notes` (text, nullable)
- `approved_by_user_id` (FK -> `users`, nullable)
- `approved_at` (timestamp, nullable)
- Unique Index: `[manufacturing_recipe_id, version_number]` (`mfg_recipe_ver_unique`)

### 4. `manufacturing_recipe_items` (بنود وصفة التصنيع)
- `id` (PK)
- `recipe_version_id` (FK -> `manufacturing_recipe_versions`)
- `item_type` (enum: `MATERIAL`, `SEMI_FINISHED_COMPONENT`)
- `material_id` (FK -> `materials`, nullable)
- `semi_finished_component_id` (FK -> `semi_finished_components`, nullable)
- `quantity` (decimal 12,4) - الكمية الصافية Required
- `unit_id` (FK -> `units_of_measure`)
- `waste_percentage` (decimal 5,2) - نسبة الهدر المئوية
- `notes` (text, nullable)
- `sort_order` (integer)

### 5. `manufacturing_templates` (قوالب التصنيع المسبقة)
- `id` (PK)
- `template_code` (string, unique) - كود تلقائي `TPL-XXXXXX`
- `name_ar` (string) - مثل `قالب حواجز علب`
- `name_en` (string, nullable)
- `description` (text, nullable)
- `is_active` (boolean)

### 6. `manufacturing_template_items` (بنود قوالب التصنيع)
- `id` (PK)
- `manufacturing_template_id` (FK -> `manufacturing_templates`)
- `item_type` (enum: `MATERIAL`, `SEMI_FINISHED_COMPONENT`)
- `material_id` (FK -> `materials`, nullable)
- `semi_finished_component_id` (FK -> `semi_finished_components`, nullable)
- `quantity` (decimal 12,4)
- `unit_id` (FK -> `units_of_measure`)
- `waste_percentage` (decimal 5,2)
- `notes` (text, nullable)
- `sort_order` (integer)

---

## 3. القواعد والتصاميم المعمارية (ADRs)

1. **الاستقلالية المالية والتعامل بالكميات الفيزيائية**:
   - لا يتم تخزين أي قيم مالية أو أسعار شراء داخل بنود الوصفة.
   - يتم تخزين الكمية الصافية المادية فقط ونسبة الهدر المئوية. الكمية المخططة تحسب ديناميكياً `quantity * (1 + waste_percentage / 100)`.
2. **استقلالية المورد ولون القماش**:
   - ترتبط البنود بالخامة الأساسية (مثل قماش مخمل) دون تحديد المورد أو لون القماش الدقيق (الذي يعتبر خياراً عند إدخال طلب البيع والإنتاج).
3. **عدم التعديل المباشر للإصدارات المعتمدة (Immutable Approved Versions)**:
   - الإصدار المعتمد `APPROVED` لا يجوز تعديله هيدروليكياً مباشرة.
   - للتعديل يتم إنشاء إصدار جديد "نسخ كإصدار جديد" `V2 Draft` وتعديله ثم اعتماده، ليتحول `V1` تلقائياً إلى `SUPERSEDED` لحفظ السجل التاريخي.
4. **استقلالية قوالب التصنيع (`Manufacturing Templates`)**:
   - القالب هو نقطة بداية فقط لتعبئة البنود عند إنشاء وصفة جديدة، ولا يرتبط بالوصفة بعد إنشائها.
5. **معاينة التكلفة المعيارية غير الرسمية (`Standard Cost Preview`)**:
   - حساب تقديري `planned_quantity * current_lot_unit_cost` لأغراض المعاينة التقديرية فقط دون حفظ التكلفة التاريخية في الوصفة.

---

## 4. القيود والمراحل المستقبلية (Stage Boundaries)
- **ممنوع في Stage 7**: بناء أوامر العملاء، عروض الأسعار، أوامر الإنتاج، المسارات التشغيلية الورشية (Routing)، تكاليف العمالة والآلات، أو مخزون المنتجات التامة.
- **المرحلة القادمة (Stage 8)**: مراكز التكلفة والمجموعات والمسارات التشغيلية للإنتاج (Routing & Work Centers).
