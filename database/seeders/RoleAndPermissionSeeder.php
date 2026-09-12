<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Core Roles Definitions
        $rolesData = [
            [
                'name' => 'admin',
                'display_name' => 'مدير النظام',
                'description' => 'المسؤول التقني والإداري للنظام، يمتلك كامل الصلاحيات وإدارة المستخدمين والإعدادات.',
                'is_system' => true,
            ],
            [
                'name' => 'production_manager',
                'display_name' => 'مدير الإنتاج',
                'description' => 'إدارة خطوط الإنتاج، مراجعة واعتماد الطلبات، إطلاق أوامر التصنيع، وقرارات الهدر وإعادة التصنيع.',
                'is_system' => true,
            ],
            [
                'name' => 'customer_service',
                'display_name' => 'خدمة العملاء',
                'description' => 'إدخال طلبات المتجر والمعارض والعملاء المباشرين، متابعة التعديلات دون صلاحية اعتماد التصنيع.',
                'is_system' => true,
            ],
            [
                'name' => 'warehouse_keeper',
                'display_name' => 'أمين المستودع',
                'description' => 'استلام الخامات، فحص الجودة المبدئية، تتبع اللوت، وصرف وإرجاع المواد الخام للإنتاج.',
                'is_system' => true,
            ],
            [
                'name' => 'production_worker',
                'display_name' => 'موظف قسم إنتاج',
                'description' => 'مشغلو الأقسام المصنعية التشغيلية (نجارة، تسفيج، تنجيد، تجميع، تغليف) لتحديث تقدم الإنجاز.',
                'is_system' => true,
            ],
            [
                'name' => 'delivery_user',
                'display_name' => 'التوصيل والتركيب',
                'description' => 'فريق التوصيل الميداني للمصنع لمتابعة المهام المسندة وتحديث حالة التسليم للعميل.',
                'is_system' => true,
            ],
            [
                'name' => 'sales_user',
                'display_name' => 'المبيعات',
                'description' => 'إنشاء عروض الأسعار، متابعة مبيعات الجملة والمعارض والعملاء المباشرين.',
                'is_system' => true,
            ],
        ];

        $roles = [];
        foreach ($rolesData as $roleItem) {
            $roles[$roleItem['name']] = Role::firstOrCreate(
                ['name' => $roleItem['name']],
                $roleItem
            );
        }

        // 2. Granular Permissions (44 permissions across 13 modules)
        $permissionsData = [
            // Users Management
            ['name' => 'users.view', 'display_name' => 'عرض سجل المستخدمين', 'module' => 'users'],
            ['name' => 'users.create', 'display_name' => 'إنشاء مستخدم جديد', 'module' => 'users'],
            ['name' => 'users.update', 'display_name' => 'تعديل بيانات المستخدمين', 'module' => 'users'],
            ['name' => 'users.activate', 'display_name' => 'تفعيل حساب مستخدم', 'module' => 'users'],
            ['name' => 'users.deactivate', 'display_name' => 'تعطيل حساب مستخدم', 'module' => 'users'],

            // Roles Management
            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار والصلاحيات', 'module' => 'roles'],
            ['name' => 'roles.manage', 'display_name' => 'تعديل مصفوفة الصلاحيات للأدوار', 'module' => 'roles'],

            // Customers
            ['name' => 'customers.view', 'display_name' => 'عرض سجل العملاء', 'module' => 'customers'],
            ['name' => 'customers.create', 'display_name' => 'إضافة عميل جديد', 'module' => 'customers'],
            ['name' => 'customers.update', 'display_name' => 'تعديل بيانات العميل', 'module' => 'customers'],

            // Quotations
            ['name' => 'quotations.view', 'display_name' => 'عرض عروض الأسعار', 'module' => 'quotations'],
            ['name' => 'quotations.create', 'display_name' => 'إنشاء عرض سعر جديد', 'module' => 'quotations'],
            ['name' => 'quotations.update', 'display_name' => 'تعديل عروض الأسعار', 'module' => 'quotations'],
            ['name' => 'quotations.approve', 'display_name' => 'اعتماد عروض الأسعار', 'module' => 'quotations'],

            // Orders
            ['name' => 'orders.view', 'display_name' => 'عرض طلبات العملاء', 'module' => 'orders'],
            ['name' => 'orders.create', 'display_name' => 'إنشاء طلب عميل جديد', 'module' => 'orders'],
            ['name' => 'orders.update', 'display_name' => 'تعديل بيانات الطلب', 'module' => 'orders'],
            ['name' => 'orders.request_change', 'display_name' => 'طلب تعديل على أمر تصنيع', 'module' => 'orders'],
            ['name' => 'orders.review_production', 'display_name' => 'مراجعة الجدوى والمواصفات للتصنيع', 'module' => 'orders'],
            ['name' => 'orders.approve_production', 'display_name' => 'اعتماد الطلب لبدء التصنيع', 'module' => 'orders'],

            // Production
            ['name' => 'production.view', 'display_name' => 'عرض أوامر وخطوط الإنتاج', 'module' => 'production'],
            ['name' => 'production.release', 'display_name' => 'إطلاق أوامر الإنتاج للورشة', 'module' => 'production'],
            ['name' => 'production.update_progress', 'display_name' => 'تحديث إنجاز المراحل (WIP)', 'module' => 'production'],
            ['name' => 'production.complete', 'display_name' => 'تأكيد اكتمال أمر الإنتاج', 'module' => 'production'],
            ['name' => 'production.manage_routing', 'display_name' => 'إدارة مسارات وتوليفات التصنيع', 'module' => 'production'],
            ['name' => 'production.hold', 'display_name' => 'تعليق وإيقاف أمر إنتاج', 'module' => 'production'],
            ['name' => 'production.correct_progress', 'display_name' => 'تصحيح وتعديل كميات الإنجاز', 'module' => 'production'],
            ['name' => 'production.manage_rework', 'display_name' => 'إدارة قرارات التلف وإعادة التصنيع', 'module' => 'production'],
            ['name' => 'production.material_requests', 'display_name' => 'إنشاء وتقديم طلبات مواد الإنتاج', 'module' => 'production'],
            ['name' => 'production.report_quality', 'display_name' => 'تسجيل ملاحظات وحوادث الجودة', 'module' => 'production'],
            ['name' => 'production.manage_quality', 'display_name' => 'إدارة قرارات حوادث الجودة', 'module' => 'production'],
            ['name' => 'production.record_waste', 'display_name' => 'تسجيل هدر الإنتاج', 'module' => 'production'],
            ['name' => 'production.approve_waste', 'display_name' => 'اعتماد سجلات هدر الإنتاج', 'module' => 'production'],

            // Inventory
            ['name' => 'inventory.view', 'display_name' => 'عرض أرصدة وحركات المستودع', 'module' => 'inventory'],
            ['name' => 'inventory.receive', 'display_name' => 'تسجيل استلام توريد خامات', 'module' => 'inventory'],
            ['name' => 'inventory.issue', 'display_name' => 'صرف المواد لأوامر الإنتاج', 'module' => 'inventory'],
            ['name' => 'inventory.return', 'display_name' => 'تسجيل إرجاع الخامات الصالحة للمستودع', 'module' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'تسوية وجرد المخزون', 'module' => 'inventory'],

            // Materials
            ['name' => 'materials.view', 'display_name' => 'عرض كتالوج المواد الخام', 'module' => 'materials'],
            ['name' => 'materials.manage', 'display_name' => 'إدارة وتوصيف المواد الخام واللوت', 'module' => 'materials'],

            // Products
            ['name' => 'products.view', 'display_name' => 'عرض دليل الموديلات والمنتجات', 'module' => 'products'],
            ['name' => 'products.manage', 'display_name' => 'إدارة الموديلات والمسميات والوصفات', 'module' => 'products'],

            // Suppliers
            ['name' => 'suppliers.view', 'display_name' => 'عرض قائمة الموردين', 'module' => 'suppliers'],
            ['name' => 'suppliers.manage', 'display_name' => 'إدارة وتحديث بيانات الموردين', 'module' => 'suppliers'],

            // Costing
            ['name' => 'costing.view', 'display_name' => 'عرض تقارير التكاليف وحساب الربحية', 'module' => 'costing'],
            ['name' => 'costing.manage', 'display_name' => 'إدارة معايير التكلفة والأسعار القياسية', 'module' => 'costing'],

            // Delivery
            ['name' => 'delivery.view', 'display_name' => 'عرض جدول وجاهزية التوصيل', 'module' => 'delivery'],
            ['name' => 'delivery.update', 'display_name' => 'تحديث حالة خروج وإتمام التوصيل', 'module' => 'delivery'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'عرض التقارير العامة', 'module' => 'reports'],
            ['name' => 'reports.financial', 'display_name' => 'عرض التقارير المالية والربحية', 'module' => 'reports'],
            ['name' => 'reports.production', 'display_name' => 'عرض تقارير كفاءة الإنتاج والهدر', 'module' => 'reports'],
            ['name' => 'reports.inventory', 'display_name' => 'عرض تقارير حركة المخزون واللوت', 'module' => 'reports'],

            // Sales Channels
            ['name' => 'sales_channels.view', 'display_name' => 'عرض قنوات البيع', 'module' => 'sales_channels'],
            ['name' => 'sales_channels.manage', 'display_name' => 'إدارة قنوات البيع', 'module' => 'sales_channels'],

            // Customer Types
            ['name' => 'customer_types.view', 'display_name' => 'عرض تصنيفات العملاء', 'module' => 'customer_types'],
            ['name' => 'customer_types.manage', 'display_name' => 'إدارة تصنيفات العملاء', 'module' => 'customer_types'],

            // Units of Measure
            ['name' => 'units.view', 'display_name' => 'عرض وحدات القياس', 'module' => 'units'],
            ['name' => 'units.manage', 'display_name' => 'إدارة وحدات القياس', 'module' => 'units'],

            // Factory Departments
            ['name' => 'departments.view', 'display_name' => 'عرض أقسام المصنع', 'module' => 'departments'],
            ['name' => 'departments.manage', 'display_name' => 'إدارة أقسام المصنع', 'module' => 'departments'],

            // Manufacturing Recipes & BOM
            ['name' => 'recipes.view', 'display_name' => 'عرض وصفات التصنيع ووصفات المكونات', 'module' => 'recipes'],
            ['name' => 'recipes.manage', 'display_name' => 'إنشاء وتعديل وصفات التصنيع والإصدارات', 'module' => 'recipes'],
            ['name' => 'recipes.approve', 'display_name' => 'اعتماد إصدارات وصفات التصنيع', 'module' => 'recipes'],
            ['name' => 'manufacturing_templates.view', 'display_name' => 'عرض قوالب التصنيع', 'module' => 'manufacturing_templates'],
            ['name' => 'manufacturing_templates.manage', 'display_name' => 'إدارة قوالب التصنيع', 'module' => 'manufacturing_templates'],
            ['name' => 'semi_finished_components.view', 'display_name' => 'عرض المكونات نصف المصنعة', 'module' => 'semi_finished_components'],
            ['name' => 'semi_finished_components.manage', 'display_name' => 'إدارة المكونات نصف المصنعة', 'module' => 'semi_finished_components'],
        ];

        $permissions = [];
        foreach ($permissionsData as $permItem) {
            $permissions[$permItem['name']] = Permission::firstOrCreate(
                ['name' => $permItem['name']],
                $permItem
            );
        }

        // 3. Default Role-Permission Mappings (Section 8 of Specification)

        // Production Manager
        $prodManagerPerms = [
            'customers.view',
            'quotations.view',
            'orders.view',
            'orders.review_production',
            'orders.approve_production',
            'production.view',
            'production.release',
            'production.update_progress',
            'production.complete',
            'production.manage_routing',
            'production.hold',
            'production.correct_progress',
            'production.manage_rework',
            'production.material_requests',
            'production.report_quality',
            'production.manage_quality',
            'production.record_waste',
            'production.approve_waste',
            'inventory.view',
            'materials.view',
            'products.view',
            'suppliers.view',
            'costing.view',
            'reports.view',
            'reports.production',
            'reports.inventory',
            'sales_channels.view',
            'customer_types.view',
            'units.view',
            'departments.view',
            'recipes.view',
            'recipes.manage',
            'recipes.approve',
            'manufacturing_templates.view',
            'manufacturing_templates.manage',
            'semi_finished_components.view',
            'semi_finished_components.manage',
        ];
        $roles['production_manager']->permissions()->syncWithoutDetaching(
            collect($prodManagerPerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // Customer Service
        $customerServicePerms = [
            'customers.view',
            'customers.create',
            'customers.update',
            'quotations.view',
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.request_change',
            'products.view',
            'delivery.view',
            'sales_channels.view',
            'customer_types.view',
        ];
        $roles['customer_service']->permissions()->syncWithoutDetaching(
            collect($customerServicePerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // Warehouse Keeper
        $warehouseKeeperPerms = [
            'inventory.view',
            'inventory.receive',
            'inventory.issue',
            'inventory.return',
            'materials.view',
            'suppliers.view',
            'production.view',
            'reports.inventory',
            'units.view',
            'departments.view',
            'recipes.view',
        ];
        $roles['warehouse_keeper']->permissions()->syncWithoutDetaching(
            collect($warehouseKeeperPerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // Production Department User
        $productionWorkerPerms = [
            'production.view',
            'production.update_progress',
            'production.report_quality',
            'production.record_waste',
            'products.view',
            'departments.view',
            'recipes.view',
            'semi_finished_components.view',
        ];
        $roles['production_worker']->permissions()->syncWithoutDetaching(
            collect($productionWorkerPerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // Delivery / Installation User
        $deliveryUserPerms = [
            'delivery.view',
            'delivery.update',
            'orders.view',
        ];
        $roles['delivery_user']->permissions()->syncWithoutDetaching(
            collect($deliveryUserPerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // Sales / Commercial User
        $salesUserPerms = [
            'customers.view',
            'customers.create',
            'customers.update',
            'quotations.view',
            'quotations.create',
            'quotations.update',
            'quotations.approve',
            'orders.view',
            'orders.create',
            'reports.view',
            'sales_channels.view',
            'customer_types.view',
        ];
        $roles['sales_user']->permissions()->syncWithoutDetaching(
            collect($salesUserPerms)->map(fn ($p) => $permissions[$p]->id)->toArray()
        );

        // System Administrator inherits all permissions in database and via Gate::before
        $roles['admin']->permissions()->syncWithoutDetaching(collect($permissions)->pluck('id')->toArray());
    }
}
