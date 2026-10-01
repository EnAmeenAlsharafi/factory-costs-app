<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Department;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\OperationalDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected OperationalDashboardService $dashboardService) {}

    /**
     * Display the role-oriented operational dashboard ("what do I need to do now?").
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $sections = $this->dashboardService->sectionsFor($user);

        return view('dashboard', [
            'sections' => $sections,
            'masterDataStats' => $this->masterDataStatsFor($user),
        ]);
    }

    /**
     * Reference-data counts, computed only for records the user is allowed to open.
     *
     * @return list<array{label: string, value: ?int, icon: string, route: string}>
     */
    private function masterDataStatsFor(User $user): array
    {
        $candidates = [
            ['permission' => 'users.view', 'label' => 'المستخدمون النشطون', 'icon' => 'fa-users-gear', 'route' => 'users.index', 'count' => fn () => User::active()->count()],
            ['permission' => 'customers.view', 'label' => 'العملاء', 'icon' => 'fa-users', 'route' => 'customers.index', 'count' => fn () => Customer::count()],
            ['permission' => 'suppliers.view', 'label' => 'الموردون', 'icon' => 'fa-truck', 'route' => 'suppliers.index', 'count' => fn () => Supplier::count()],
            ['permission' => 'departments.view', 'label' => 'الأقسام النشطة', 'icon' => 'fa-building', 'route' => 'departments.index', 'count' => fn () => Department::active()->count()],
            ['permission' => 'units.view', 'label' => 'وحدات القياس النشطة', 'icon' => 'fa-ruler-combined', 'route' => 'units.index', 'count' => fn () => UnitOfMeasure::active()->count()],
            ['permission' => 'sales_channels.view', 'label' => 'قنوات البيع النشطة', 'icon' => 'fa-store', 'route' => 'sales-channels.index', 'count' => fn () => SalesChannel::active()->count()],
            ['permission' => 'customer_types.view', 'label' => 'أنواع العملاء', 'icon' => 'fa-id-badge', 'route' => 'customer-types.index', 'count' => null],
            ['permission' => 'roles.view', 'label' => 'الأدوار والصلاحيات', 'icon' => 'fa-shield-halved', 'route' => 'roles.index', 'count' => null],
        ];

        $stats = [];
        foreach ($candidates as $candidate) {
            if (! $user->can($candidate['permission'])) {
                continue;
            }

            $stats[] = [
                'label' => $candidate['label'],
                'value' => $candidate['count'] ? ($candidate['count'])() : null,
                'icon' => $candidate['icon'],
                'route' => $candidate['route'],
            ];
        }

        return $stats;
    }
}
