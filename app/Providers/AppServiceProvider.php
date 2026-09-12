<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure standard Bootstrap 5 pagination for RTL Arabic UI
        Paginator::useBootstrapFive();

        // Global Gate rule: Inactive users are denied, Admin has super-pass,
        // otherwise resolve against role permissions.
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            if ($user->isAdministrator()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
