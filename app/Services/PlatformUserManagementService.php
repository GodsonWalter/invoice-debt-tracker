<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformUserManagementService
{
    public function __construct(private readonly UserAccountService $userAccountService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedUsers(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = User::query()
            ->with([
                'ownedWorkspaces' => function (Relation $workspaceQuery): void {
                    $workspaceQuery
                        ->where('workspaces.is_active', true)
                        ->orderBy('workspaces.name');
                },
            ])
            ->withCount([
                'workspaces as active_workspaces_count' => function (Builder $workspaceQuery): void {
                    $workspaceQuery
                        ->where('workspaces.is_active', true)
                        ->where('workspace_user.is_active', true);
                },
                'ownedWorkspaces as owned_workspaces_count',
            ])
            ->when($filters['search'] ?? null, function (Builder $userQuery, string $search): void {
                $userQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, function (Builder $userQuery, string $role): void {
                $userQuery->where('role', $role);
            });

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->whereIn('is_active', [true, false]);
        } elseif ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query
            ->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $this->ensurePlatformManagementAccess($actor);
        $this->ensureRoleAssignmentAllowed($actor, (string) $data['role']);

        return DB::transaction(function () use ($data): User {
            return User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $this->ensurePlatformManagementAccess($actor);

        if (! $this->canModifyTarget($actor, $target)) {
            abort(403, 'You are not authorized to modify this platform account.');
        }

        return DB::transaction(function () use ($actor, $target, $data): User {
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->id);

            if (array_key_exists('role', $data) && $data['role'] !== $lockedTarget->role) {
                $this->ensureRoleAssignmentAllowed($actor, (string) $data['role']);
            }

            $wasActive = (bool) $lockedTarget->is_active;
            $willBeActive = (bool) $data['is_active'];

            if ($wasActive && ! $willBeActive) {
                $this->userAccountService->assertCanDeactivate($lockedTarget);
            }

            $lockedTarget->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => (bool) $data['is_active'],
            ]);

            if (! empty($data['password'])) {
                $lockedTarget->password = $data['password'];
            }

            $lockedTarget->save();

            if ($wasActive !== $willBeActive) {
                $this->userAccountService->syncOwnedWorkspaces($lockedTarget, $willBeActive);
            }

            return $lockedTarget->fresh();
        });
    }

    public function softDelete(User $actor, User $target): void
    {
        $this->ensurePlatformManagementAccess($actor);

        $this->userAccountService->softDeleteManagedUser($actor, $target);
    }

    public function canModifyTarget(User $actor, User $target): bool
    {
        return $this->userAccountService->canManageTarget($actor, $target);
    }

    private function ensurePlatformManagementAccess(User $actor): void
    {
        abort_unless($actor->canManagePlatformUsers(), 403, 'You are not authorized to manage platform users.');
    }

    private function ensureRoleAssignmentAllowed(User $actor, string $role): void
    {
        if (! in_array($role, User::PLATFORM_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'The selected platform role is invalid.']);
        }

        if ($role === User::PLATFORM_ROLE_OWNER && ! $actor->isPlatformOwner()) {
            throw ValidationException::withMessages(['role' => 'Only the global platform owner can assign the owner role.']);
        }

        if (! $actor->isPlatformOwner() && $this->userAccountService->platformRoleLevel($role) > $this->userAccountService->platformRoleLevel($actor->role)) {
            throw ValidationException::withMessages(['role' => 'You cannot assign a role higher than your own platform role.']);
        }
    }
}
