<?php

namespace App\Services\Concerns;

use App\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Server-side employee/department integrity for shop-floor execution (spec §9).
 *
 * An employee may act on an operation only when active, holding the relevant
 * permission, and either an administrator / production manager (override) or a
 * member of the operation's work-center department.
 */
trait AuthorizesOperationExecution
{
    protected function assertEmployeeMayExecute(User $employee, ProductionOrderOperation $operation, string $permission): void
    {
        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'authorization' => 'الموظف غير مُفعّل ولا يمكن إسناد مهام إليه أو تنفيذها.',
            ]);
        }

        if (! $employee->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'authorization' => 'لا تملك الصلاحية اللازمة لتنفيذ هذا الإجراء.',
            ]);
        }

        if ($this->hasDepartmentOverride($employee)) {
            return;
        }

        $departmentId = $operation->workCenter?->department_id;

        if ($departmentId && $employee->department_id !== $departmentId) {
            throw ValidationException::withMessages([
                'authorization' => 'لا يمكن تنفيذ عمليات خارج قسمك التشغيلي.',
            ]);
        }
    }

    protected function hasDepartmentOverride(User $employee): bool
    {
        return $employee->isAdministrator() || $employee->hasRole('production_manager');
    }
}
