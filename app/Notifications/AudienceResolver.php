<?php

namespace App\Notifications;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns a notification type's audience keys into the people who should actually be told.
 *
 * Resolution always goes through the models' own rules (Role::hasPermission, User::productionDepartmentId)
 * so that notification targeting can never drift away from what the screens themselves allow.
 */
class AudienceResolver
{
    private ?Collection $roles = null;

    /**
     * Staff for the call being resolved. Held only for the length of one resolve(), so an employee
     * added moments ago is never missed, while one call never reads the table twice.
     */
    private ?Collection $staff = null;

    /**
     * User ids the given audiences resolve to.
     *
     * @param  list<string>  $audienceKeys
     * @param  array{users?: iterable<User|int>, department_id?: int|null}  $context
     * @return list<int>
     */
    public function resolve(array $audienceKeys, array $context = []): array
    {
        $ids = collect();
        $this->staff = null;

        foreach ($audienceKeys as $key) {
            $ids = $ids->merge(match (true) {
                $key === 'user' => $this->explicitUsers($context),
                $key === 'department' => $this->department($context['department_id'] ?? null),
                $key === 'customer' => $this->customerAccounts($context['customer_id'] ?? null),
                str_starts_with($key, 'permission:') => $this->withPermission(substr($key, 11)),
                str_starts_with($key, 'role:') => $this->withRole(substr($key, 5)),
                default => [],
            });
        }

        return $ids->unique()->values()->all();
    }

    /**
     * The people the caller named: an assignee, an order's creator, the chosen recipients of an announcement.
     *
     * @return list<int>
     */
    private function explicitUsers(array $context): array
    {
        return collect($context['users'] ?? [])
            ->map(fn (User|int $user) => $user instanceof User ? $user->id : (int) $user)
            ->filter(fn (int $id) => $this->staff()->contains('id', $id))
            ->values()->all();
    }

    /**
     * The customer's own portal accounts. They are not staff, so they are looked up separately and
     * never appear in any other audience.
     *
     * @return list<int>
     */
    private function customerAccounts(?int $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->where('customer_id', $customerId)
            ->whereHas('customer', fn ($customer) => $customer->where('is_active', true))
            ->pluck('id')->all();
    }

    /**
     * Everyone working in the department, whether through a department role or their own department field.
     *
     * @return list<int>
     */
    private function department(?int $departmentId): array
    {
        if (! $departmentId) {
            return [];
        }

        return $this->staff()
            ->filter(fn (User $user) => $user->productionDepartmentId() === $departmentId)
            ->pluck('id')->all();
    }

    /**
     * Everyone whose role holds the permission.
     *
     * The admin role is left out: it holds every permission by definition, so including it would
     * deliver every notification in the factory to the administrator. Types meant for them say so
     * with a role:admin audience, and `notifications.manage` opens the view of all notifications.
     *
     * @return list<int>
     */
    private function withPermission(string $permission): array
    {
        $roleIds = $this->roles()
            ->reject(fn (Role $role) => $role->name === 'admin')
            ->filter(fn (Role $role) => $role->hasPermission($permission))
            ->pluck('id');

        return $this->staff()->whereIn('role_id', $roleIds)->pluck('id')->all();
    }

    /**
     * @return list<int>
     */
    private function withRole(string $roleName): array
    {
        $roleId = $this->roles()->firstWhere('name', $roleName)?->id;

        return $roleId ? $this->staff()->where('role_id', $roleId)->pluck('id')->all() : [];
    }

    /**
     * Active internal users. Customer portal accounts are never an audience of operational notifications.
     *
     * Read fresh every time: an employee added moments ago must still be told, and the factory has
     * tens of users, not thousands.
     */
    private function staff(): Collection
    {
        return $this->staff ??= User::query()
            ->where('is_active', true)
            ->whereNull('customer_id')
            ->with('role')
            ->get()
            ->reject(fn (User $user) => $user->hasRole('customer_portal'));
    }

    private function roles(): Collection
    {
        return $this->roles ??= Role::with('permissions')->get();
    }
}
