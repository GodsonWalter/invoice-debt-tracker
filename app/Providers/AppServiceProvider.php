<?php

namespace App\Providers;

use App\Models\Testimonial;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TestimonialPolicy;
use App\Services\ImpersonationService;
use App\Services\PlatformConfigurationService;
use App\Services\PlatformUserManagementService;
use App\Services\UserAccountService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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
        Gate::policy(Testimonial::class, TestimonialPolicy::class);

        Gate::define('view-testimonials', function (User $user, Workspace $workspace): bool {
            return app(TestimonialPolicy::class)->viewAny($user, $workspace);
        });

        Gate::define('manage-testimonials', function (User $user, Workspace $workspace): bool {
            return app(TestimonialPolicy::class)->create($user, $workspace);
        });

        Gate::define('manage-platform-workspace-recovery', function (?User $user): bool {
            return ($user?->isPlatformOwner() ?? false)
                && ! app(ImpersonationService::class)->isImpersonating();
        });

        Gate::define('manage-platform-user-recovery', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && app(UserAccountService::class)->canRestoreDeletedUsers($user);
        });

        Gate::define('restore-deleted-user', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && app(UserAccountService::class)->canRestoreDeletedUsers($user);
        });

        Gate::define('delete-own-account', function (?User $user): bool {
            return $user instanceof User
                && app(UserAccountService::class)->canDeleteAccount($user);
        });

        Gate::define('manage-platform-users', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && $user->canManagePlatformUsers();
        });

        Gate::define('manage-platform-workspaces', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && $user->canManagePlatformUsers();
        });

        Gate::define('manage-platform-workspace-lifecycle', function (?User $user): bool {
            return ($user?->isPlatformOwner() ?? false)
                && ! app(ImpersonationService::class)->isImpersonating();
        });

        Gate::define('view-platform-dashboard', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && $user->canManagePlatformUsers();
        });

        Gate::define('manage-platform-testimonials', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && $user->canManagePlatformUsers();
        });

        Gate::define('manage-platform-settings', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && in_array($user->role, ['owner', 'admin'], true);
        });

        View::composer('*', function (ViewContract $view): void {
            $view->with('platformSettings', app(PlatformConfigurationService::class)->viewData());
        });

        Gate::define('impersonate-platform-user', function (?User $user): bool {
            return $user instanceof User
                && ! app(ImpersonationService::class)->isImpersonating()
                && in_array($user->role, ['owner', 'admin'], true);
        });

        Gate::define('manage-platform-user-target', function (User $user, User $target): bool {
            return ! app(ImpersonationService::class)->isImpersonating()
                && app(PlatformUserManagementService::class)->canModifyTarget($user, $target);
        });
    }
}
