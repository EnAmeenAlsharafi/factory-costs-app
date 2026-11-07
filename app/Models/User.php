<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'is_active',
        'last_login_at',
        'department_id',
        'customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Primary operational role of the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Factory operational department of the user (nullable).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isCustomerAccount(): bool
    {
        return $this->customer_id !== null || $this->hasRole('customer_portal');
    }

    /**
     * Shop-floor operations currently assigned to this employee (spec §33, §77).
     */
    public function assignedOperations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class, 'assigned_user_id');
    }

    /**
     * Determine if the user is a system administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->role?->name === 'admin';
    }

    /**
     * Determine whether the user oversees every production department.
     */
    public function hasProductionDepartmentOverride(): bool
    {
        return $this->isAdministrator() || $this->hasRole('production_manager');
    }

    /**
     * The production department the user's work is confined to (a department role wins over the user's own field).
     */
    public function productionDepartmentId(): ?int
    {
        return $this->role?->department_id ?? $this->department_id;
    }

    /**
     * Determine whether the user only sees and acts on their own department's operations.
     */
    public function isDepartmentRestricted(): bool
    {
        return ! $this->hasProductionDepartmentOverride()
            && ($this->role?->isDepartmentRole() || $this->department_id !== null);
    }

    /**
     * Determine whether the user may see or act on work belonging to the given department.
     */
    public function canAccessDepartment(?int $departmentId): bool
    {
        if (! $this->isDepartmentRestricted() || $departmentId === null) {
            return true;
        }

        return $this->productionDepartmentId() === $departmentId;
    }

    /**
     * Check if user has a specific role or any role from a list.
     */
    public function hasRole(string|array $roles): bool
    {
        if (! $this->role) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($this->role->name, $roles, true);
        }

        return $this->role->name === $roles;
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * Scope query to only active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query for user management filters.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['role_id']) && $filters['role_id'] !== '', function ($q) use ($filters) {
                $q->where('role_id', $filters['role_id']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('is_active', $filters['status'] === 'active' || $filters['status'] === '1');
            });
    }
}
