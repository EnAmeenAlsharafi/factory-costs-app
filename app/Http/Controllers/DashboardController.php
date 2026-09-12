<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Department;
use App\Models\Material;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionReworkAction;
use App\Models\QualityIncident;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the main factory production management dashboard.
     */
    public function index(Request $request): View
    {
        // Live Master Data Statistics
        $masterDataStats = [
            'customers_count' => Customer::count(),
            'suppliers_count' => Supplier::count(),
            'departments_count' => Department::active()->count(),
            'units_count' => UnitOfMeasure::active()->count(),
            'sales_channels_count' => SalesChannel::active()->count(),
            'users_count' => User::active()->count(),
        ];

        $user = $request->user();
        $metrics = collect([
            $user->can('orders.view') ? [
                'label' => 'طلبات بانتظار المراجعة',
                'value' => CustomerOrder::where('status', 'PENDING_PRODUCTION_REVIEW')->count(),
                'unit' => 'طلب',
                'icon' => 'clipboard-check',
                'route' => 'sales.orders.index',
                'class' => 'warning',
            ] : null,
            $user->can('production.view') ? [
                'label' => 'أوامر الإنتاج النشطة',
                'value' => ProductionOrder::whereIn('status', ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED', 'ON_HOLD'])->count(),
                'unit' => 'أمر إنتاج',
                'icon' => 'industry',
                'route' => 'production.orders.index',
                'class' => 'primary',
            ] : null,
            $user->can('inventory.view') ? [
                'label' => 'طلبات مواد تنتظر الصرف',
                'value' => ProductionMaterialRequest::whereIn('status', ['SUBMITTED', 'PARTIALLY_FULFILLED'])->count(),
                'unit' => 'طلب مواد',
                'icon' => 'clipboard-list',
                'route' => 'production.material-requests.index',
                'class' => 'info',
            ] : null,
            $user->can('inventory.view') ? [
                'label' => 'مواد منخفضة المخزون',
                'value' => Material::where('is_active', true)
                    ->where('reorder_point', '>', 0)
                    ->whereRaw('(SELECT COALESCE(SUM(inventory_lots.remaining_quantity), 0) FROM inventory_lots WHERE inventory_lots.material_id = materials.id AND inventory_lots.status = ?) <= materials.reorder_point', ['ACTIVE'])
                    ->count(),
                'unit' => 'صنف خام',
                'icon' => 'exclamation-triangle',
                'route' => 'inventory.balances.index',
                'class' => 'danger',
            ] : null,
            $user->can('production.view') ? [
                'label' => 'حوادث الجودة المفتوحة',
                'value' => QualityIncident::whereNotIn('status', ['RESOLVED', 'CANCELLED'])->count(),
                'unit' => 'حادثة',
                'icon' => 'triangle-exclamation',
                'route' => 'production.quality-incidents.index',
                'class' => 'warning',
            ] : null,
            $user->can('production.view') ? [
                'label' => 'حالات إعادة التصنيع المفتوحة',
                'value' => ProductionReworkAction::whereNotIn('status', ['COMPLETED', 'CANCELLED'])->count(),
                'unit' => 'حالة',
                'icon' => 'tools',
                'route' => 'production.rework.index',
                'class' => 'danger',
            ] : null,
        ])->filter()->values();

        return view('dashboard', compact('metrics', 'masterDataStats'));
    }
}
