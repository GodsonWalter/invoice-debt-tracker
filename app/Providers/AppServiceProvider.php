<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PlatformUserManagementService;
use App\Services\UserAccountService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        Gate::define('manage-platform-workspace-recovery', function (?User $user): bool {
            return $user?->isPlatformOwner() ?? false;
        });

        Gate::define('manage-platform-user-recovery', function (?User $user): bool {
            return $user instanceof User
                && app(UserAccountService::class)->canRestoreDeletedUsers($user);
        });

        Gate::define('restore-deleted-user', function (?User $user): bool {
            return $user instanceof User
                && app(UserAccountService::class)->canRestoreDeletedUsers($user);
        });

        Gate::define('delete-own-account', function (?User $user): bool {
            return $user instanceof User
                && app(UserAccountService::class)->canDeleteAccount($user);
        });

        Gate::define('manage-platform-users', function (?User $user): bool {
            return $user instanceof User && $user->canManagePlatformUsers();
        });

        Gate::define('manage-platform-user-target', function (User $user, User $target): bool {
            return app(PlatformUserManagementService::class)->canModifyTarget($user, $target);
        });
    }
}
