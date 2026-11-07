<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and maintains the roles that belong to a single production department.
 */
class DepartmentRoleService
{
    /**
     * Default roles every production department starts with: suffix => definition.
     *
     * @var array<string, array{display_name: string, description: string, permissions: list<string>}>
     */
    public const DEFAULT_ROLES = [
        'worker' => [
            'display_name' => 'عامل قسم',
            'description' => 'تنفيذ مهام القسم، استلامها وبدؤها وتحديث الإنجاز وتسجيل العوائق والجودة والهدر.',
            'permissions' => [
                'production.view',
                'production.update_progress',
                'production.assignment.view',
                'production.operation.start',
                'production.operation.claim',
                'production.operation.block',
                'production.report_quality',
                'production.record_waste',
                'products.view',
                'departments.view',
                'recipes.view',
                'semi_finished_components.view',
            ],
        ],
        'supervisor' => [
            'display_name' => 'مشرف قسم',
            'description' => 'صلاحيات العامل مع إسناد مهام القسم وحل العوائق وتصحيح الإنجاز داخل القسم فقط.',
            'permissions' => [
                'production.view',
                'production.update_progress',
                'production.correct_progress',
                'production.assignment.view',
                'production.assignment.manage',
                'production.operation.start',
                'production.operation.claim',
                'production.operation.block',
                'production.operation.unblock',
                'production.report_quality',
                'production.record_waste',
                'products.view',
                'departments.view',
                'recipes.view',
                'semi_finished_components.view',
            ],
        ],
    ];

    /**
     * Create the department's default roles if missing; existing roles keep their permissions.
     */
    public function ensureDefaultRoles(Department $department): void
    {
        if (! $department->is_production_department) {
            return;
        }

        foreach (self::DEFAULT_ROLES as $suffix => $definition) {
            if ($this->defaultRole($department, $suffix)) {
                continue;
            }

            $name = $this->roleName($department, $suffix);

            $role = Role::create([
                'name' => Role::where('name', $name)->exists() ? "{$name}_{$department->id}" : $name,
                'display_name' => "{$definition['display_name']} {$department->name_ar}",
                'description' => $definition['description'],
                'is_system' => false,
                'department_id' => $department->id,
            ]);

            $role->permissions()->sync(Permission::whereIn('name', $definition['permissions'])->pluck('id'));
        }
    }

    /**
     * Move users still on the legacy global production_worker role onto their department's worker role.
     */
    public function migrateLegacyWorkers(): int
    {
        $legacyRole = Role::where('name', 'production_worker')->first();

        if (! $legacyRole) {
            return 0;
        }

        $moved = 0;

        User::where('role_id', $legacyRole->id)
            ->whereHas('department', fn ($q) => $q->where('is_production_department', true))
            ->with('department')
            ->each(function (User $user) use (&$moved) {
                $workerRole = $this->defaultRole($user->department, 'worker');

                if ($workerRole) {
                    $user->update(['role_id' => $workerRole->id]);
                    $moved++;
                }
            });

        return $moved;
    }

    /**
     * Create a new custom role inside a department, optionally copying another role's permissions.
     *
     * @param  array{display_name: string, description?: string|null}  $attributes
     */
    public function createRole(Department $department, array $attributes, ?Role $copyFrom = null): Role
    {
        return DB::transaction(function () use ($department, $attributes, $copyFrom) {
            $role = Role::create([
                'name' => $this->uniqueRoleName($department),
                'display_name' => $attributes['display_name'],
                'description' => $attributes['description'] ?? null,
                'is_system' => false,
                'department_id' => $department->id,
            ]);

            if ($copyFrom) {
                $this->syncPermissions($role, $copyFrom->permissions()->pluck('permissions.id')->all());
            }

            return $role;
        });
    }

    /**
     * Sync a role's permissions, dropping anything a department role is not allowed to hold.
     *
     * @param  list<int|string>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $query = Permission::whereIn('id', $permissionIds);

        if ($role->isDepartmentRole()) {
            $query->whereIn('name', Role::DEPARTMENT_ASSIGNABLE_PERMISSIONS);
        }

        $role->permissions()->sync($query->pluck('id'));
    }

    /**
     * The department's default role for a suffix (worker / supervisor), matched by department rather than code.
     */
    public function defaultRole(Department $department, string $suffix): ?Role
    {
        return Role::forDepartment($department->id)
            ->where(fn ($q) => $q->where('name', 'like', "%.{$suffix}")->orWhere('name', 'like', "%.{$suffix}_{$department->id}"))
            ->orderBy('id')
            ->first();
    }

    private function roleName(Department $department, string $suffix): string
    {
        return Str::lower($department->code).'.'.$suffix;
    }

    private function uniqueRoleName(Department $department): string
    {
        do {
            $name = $this->roleName($department, 'custom_'.Str::lower(Str::random(6)));
        } while (Role::where('name', $name)->exists());

        return $name;
    }
}
