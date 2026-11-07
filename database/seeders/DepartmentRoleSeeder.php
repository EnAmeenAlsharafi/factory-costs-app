<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Services\DepartmentRoleService;
use Illuminate\Database\Seeder;

class DepartmentRoleSeeder extends Seeder
{
    /**
     * Give every production department its own worker and supervisor roles,
     * then move legacy production_worker users onto their department's worker role.
     */
    public function run(DepartmentRoleService $departmentRoles): void
    {
        Department::production()->each(fn (Department $department) => $departmentRoles->ensureDefaultRoles($department));

        $departmentRoles->migrateLegacyWorkers();
    }
}
