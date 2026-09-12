<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Display a listing of factory roles.
     */
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('roles.view'), 403, 'غير مصرح لك بعرض الأدوار والصلاحيات.');

        $roles = Role::withCount(['users', 'permissions'])->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Display the specified role and its active permissions.
     */
    public function show(Request $request, Role $role): View
    {
        abort_if(! $request->user()->can('roles.view'), 403, 'غير مصرح لك بعرض تفاصيل الدور.');

        $role->load('permissions');
        $allPermissions = Permission::orderBy('module')->orderBy('id')->get();
        $groupedPermissions = $allPermissions->groupBy('module');

        return view('roles.show', compact('role', 'groupedPermissions'));
    }

    /**
     * Show the form for editing role permissions.
     */
    public function edit(Request $request, Role $role): View
    {
        abort_if(! $request->user()->can('roles.manage'), 403, 'غير مصرح لك بتعديل صلاحيات الأدوار.');

        $allPermissions = Permission::orderBy('module')->orderBy('id')->get();
        $groupedPermissions = $allPermissions->groupBy('module');
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return view('roles.edit', compact('role', 'groupedPermissions', 'rolePermissionIds'));
    }

    /**
     * Update the permissions assigned to the role.
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
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', "تم تحديث مصفوفة صلاحيات ({$role->display_name}) بنجاح.");
    }
}
