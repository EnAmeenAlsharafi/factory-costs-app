# Stage 4 — Materials Catalog & Material Specifications

## Overview

Stage 4 establishes the central raw materials catalog and technical material specifications for the **Sadir Furniture Factory Production Management System**.

This stage provides the foundation for future inventory receipts, stock balances, purchase orders, product BOMs (Recipes), and manufacturing cost calculations.

---

## Key Modules & Models

1. **Material Categories (`material_categories`)**
   - Central categories: `WOOD`, `FOAM`, `FABRIC`, `ACCESSORY`, `PACKAGING`, `ADHESIVE`, `CONSUMABLE`, `OTHER`.
   - Supports active/inactive status toggling and Arabic/English display names.

2. **Materials Catalog (`materials`)**
   - Unique code (`MAT-XXXXXX`), Arabic name, English name, category reference, base unit of measure (`base_unit_id`), and optional default purchasing unit (`purchase_unit_id`).
   - Stock thresholds: `min_stock_level` (minimum stock) and `reorder_point` (reorder level).
   - Concurrency-safe sequential code generator `Material::generateNextCode()`.

3. **Domain-Specific Material Specifications (1-to-1 relations)**
   - **Wood Specifications (`wood_material_specs`)**: `wood_type` (e.g. Beech, Swedish, MDF, Counter), `thickness_mm`, `width_cm`, `length_cm`, `grade`.
   - **Foam Specifications (`foam_material_specs`)**: `foam_type` (e.g. Density 30 Premium), `density_kg_m3`, `hardness_rating`, `thickness_mm`, `width_cm` (numeric), `length_cm` (numeric), `block_dimensions` (optional text note).
   - **Fabric Specifications (`fabric_material_specs`)**: `fabric_type` (e.g. Velvet, Linen, Leatherette), `width_cm` (roll width e.g. 140cm). Additional optional technical attributes: `pattern_type`, `weight_gsm`, `composition`, `martindale_rub_count`.

4. **Fabric Color Variations (`fabric_colors`)**
   - 1-to-many relationship with fabric materials.
   - Stores `color_code`, `color_name_ar`, `color_name_en`, `hex_code`, `pattern`, `is_active`, and notes.

5. **Material Supplier Mapping (`material_supplier`)**
   - Many-to-many relationship mapping approved vendors to raw materials.
   - Stores `supplier_item_code`, `lead_time_days`, `minimum_order_qty`, `is_preferred` toggle.
   - **Rule #39 Compliance**: Does NOT store current purchase prices or single cost numbers as authoritative material costs.

6. **Material Unit Conversions (`material_unit_conversions`)**
   - Material-specific conversion factors (e.g., 1 Roll = 50 Meters for Fabric X).
   - Enables flexible inventory receipts and BOM consumptions while maintaining strict base UOM accuracy.

---

## Authorization & Security

- `materials.view`: View materials catalog, category list, material details, specs, colors, suppliers, and conversions.
- `materials.manage`: Create, edit, and toggle status of material categories, materials, wood/foam/fabric specs, fabric colors, supplier links, and unit conversions.

---

## Verification & Test Coverage

- Feature tests in `tests/Feature/MasterData/MaterialTest.php` cover:
  - Viewing categories and materials catalog
  - Creating and updating categories
  - Creating materials with separate base and purchase units
  - Creating Wood materials with Wood specs
  - Creating Foam materials with numeric dimensions (`width_cm`, `length_cm`)
  - Creating Fabric materials with Fabric specs and adding fabric colors
  - Linking approved suppliers to materials without price columns (Rule #39)
  - Creating material-specific unit conversions
  - Concurrency-safe sequential code generation
  - Authorization boundary enforcement
- Full suite passes 100% (59 tests, 234 assertions).
