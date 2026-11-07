<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    /**
     * Permissions a department-scoped role may hold. Production actions here are enforced
     * against the operation's department; the rest is read-only reference data.
     *
     * @var list<string>
     */
    public const DEPARTMENT_ASSIGNABLE_PERMISSIONS = [
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
        // Read-only reference data workers consult; it exposes no department's work.
        'products.view',
        'departments.view',
        'recipes.view',
        'semi_finished_components.view',
    ];

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'department_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Production department this role belongs to (NULL for global roles).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Determine whether the role is scoped to a single production department.
     */
    public function isDepartmentRole(): bool
    {
        return $this->department_id !== null;
    }

    /**
     * Scope to roles that are not tied to a department.
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('department_id');
    }

    /**
     * Scope to the roles of one department.
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Users assigned this primary role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Determine whether the role has a given permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        // System admin has all permissions inherently
        if ($this->name === 'admin') {
            return true;
        }

        if ($this->isDepartmentRole() && ! in_array($permissionName, self::DEPARTMENT_ASSIGNABLE_PERMISSIONS, true)) {
            return false;
        }

        return $this->permissions->contains('name', $permissionName);
    }
}
