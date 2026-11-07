<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    /**
     * Arabic label for each permission module shown in the roles screens.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'users' => 'المستخدمون',
        'roles' => 'الأدوار والصلاحيات',
        'customers' => 'العملاء',
        'quotations' => 'عروض الأسعار',
        'orders' => 'طلبات العملاء',
        'production' => 'الإنتاج',
        'inventory' => 'المخزون',
        'materials' => 'المواد الخام',
        'products' => 'المنتجات',
        'suppliers' => 'الموردون',
        'costing' => 'التكاليف',
        'sales_imports' => 'استيراد طلبات سلة',
        'ready_products' => 'المنتجات الجاهزة',
        'delivery' => 'التوصيل',
        'reports' => 'التقارير',
        'sales_channels' => 'قنوات البيع',
        'customer_types' => 'أنواع العملاء',
        'units' => 'وحدات القياس',
        'departments' => 'أقسام المصنع',
        'recipes' => 'وصفات التصنيع',
        'manufacturing_templates' => 'قوالب التصنيع',
        'semi_finished_components' => 'المكونات نصف المصنعة',
        'purchasing' => 'المشتريات',
        'receivables' => 'التحصيل والذمم',
    ];

    protected $fillable = [
        'name',
        'display_name',
        'module',
    ];

    /**
     * Roles that possess this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
