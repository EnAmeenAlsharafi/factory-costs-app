<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRoleStoreRequest;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DepartmentRoleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(protected DepartmentRoleService $departmentRoles) {}

    /**
     * Display global roles and, per production department, that department's own roles.
     */
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('roles.view'), 403, 'غير مصرح لك بعرض الأدوار والصلاحيات.');

        $roles = Role::global()->withCount(['users', 'permissions'])->get();

        $departments = Department::production()
            ->orderBy('sort_order')
            ->with(['roles' => fn ($q) => $q->with('department')->withCount(['users', 'permissions'])->orderBy('id')])
            ->get();

        $activeTab = (string) $request->query('department', 'global');

        return view('roles.index', compact('roles', 'departments', 'activeTab'));
    }

    /**
     * Show the form for adding a role to a production department.
     */
    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بإضافة أدوار.');

        $department = Department::production()->findOrFail($request->query('department_id'));
        $department->load('roles');
        $defaultCopyFromId = (string) ($this->departmentRoles->defaultRole($department, 'worker')?->id ?? '');

        return view('roles.create', compact('department', 'defaultCopyFromId'));
    }

    /**
     * Store a new department role.
     */
    public function store(DepartmentRoleStoreRequest $request): RedirectResponse
    {
        $department = Department::findOrFail($request->validated('department_id'));
        $copyFrom = $request->filled('copy_from_role_id') ? Role::find($request->validated('copy_from_role_id')) : null;

        $role = $this->departmentRoles->createRole($department, $request->safe()->only(['display_name', 'description']), $copyFrom);

        return redirect()->route('roles.edit', $role)->with('success', "تم إنشاء الدور ({$role->display_name}) في قسم {$department->name_ar}. حدد صلاحياته الآن.");
    }

    /**
     * Display the specified role and its active permissions.
     */
    public function show(Request $request, Role $role): View
    {
        abort_if(! $request->user()->can('roles.view'), 403, 'غير مصرح لك بعرض تفاصيل الدور.');

        $role->load(['permissions', 'department']);
        $groupedPermissions = $this->permissionsFor($role)->groupBy('module');

        return view('roles.show', compact('role', 'groupedPermissions'));
    }

    /**
     * Show the form for editing role permissions.
     */
    public function edit(Request $request, Role $role): View
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بتعديل صلاحيات الأدوار.');

        $role->load('department');
        $groupedPermissions = $this->permissionsFor($role)->groupBy('module');
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return view('roles.edit', compact('role', 'groupedPermissions', 'rolePermissionIds'));
    }

    /**
     * Update the permissions (and, for department roles, the name) of the role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بتعديل صلاحيات الأدوار.');

        // Guard the built-in system administrator role
        if ($role->name === 'admin') {
            return redirect()->route('roles.index')->with('info', 'دور مدير النظام يمتلك كافة صلاحيات النظام بصورة تلقائية وشاملة.');
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
            'display_name' => [$role->isDepartmentRole() ? 'required' : 'prohibited', 'string', 'max:255'],
            'description' => [$role->isDepartmentRole() ? 'nullable' : 'prohibited', 'string', 'max:1000'],
        ], [
            'display_name.required' => 'يرجى إدخال اسم الدور.',
        ]);

        if ($role->isDepartmentRole()) {
            $role->update([
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        $this->departmentRoles->syncPermissions($role, $validated['permissions'] ?? []);

        return redirect()->route('roles.index', $this->tabFor($role))->with('success', "تم تحديث مصفوفة صلاحيات ({$role->display_name}) بنجاح.");
    }

    /**
     * Copy a department role, with its permissions, inside the same department.
     */
    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بإضافة أدوار.');
        abort_unless($role->isDepartmentRole(), 422, 'يمكن نسخ أدوار الأقسام فقط.');

        $copy = $this->departmentRoles->createRole(
            $role->department,
            ['display_name' => "نسخة من {$role->display_name}", 'description' => $role->description],
            $role
        );

        return redirect()->route('roles.edit', $copy)->with('success', 'تم نسخ الدور، عدّل اسمه وصلاحياته.');
    }

    /**
     * Delete a department role that no user holds.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بحذف الأدوار.');

        if (! $role->isDepartmentRole() || $role->is_system) {
            return back()->with('error', 'لا يمكن حذف الأدوار العامة أو أدوار النظام.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'لا يمكن حذف دور مسند لموظفين. انقل الموظفين لدور آخر أولاً.');
        }

        $tab = $this->tabFor($role);
        $role->delete();

        return redirect()->route('roles.index', $tab)->with('success', 'تم حذف الدور بنجاح.');
    }

    /**
     * Permissions offered for a role: department roles only see what is enforced per department.
     *
     * @return Collection<int, Permission>
     */
    private function permissionsFor(Role $role): Collection
    {
        return Permission::query()
            ->when($role->isDepartmentRole(), fn ($q) => $q->whereIn('name', Role::DEPARTMENT_ASSIGNABLE_PERMISSIONS))
            ->orderBy('module')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{department?: int}
     */
    private function tabFor(Role $role): array
    {
        return $role->department_id ? ['department' => $role->department_id] : [];
    }
}
