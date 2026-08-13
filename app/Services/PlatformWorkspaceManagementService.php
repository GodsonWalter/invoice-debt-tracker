<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformWorkspaceManagementService
{
    public function __construct(
        private readonly WorkspaceLifecycleService $lifecycleService,
        private readonly WorkspaceDefaultsService $workspaceDefaultsService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedWorkspaces(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'all';
        $sort = in_array($filters['sort'] ?? null, ['name', 'slug', 'subdomain', 'is_active', 'created_at', 'deleted_at'], true)
            ? $filters['sort']
            : 'created_at';
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        $query = Workspace::query()
            ->with([
                'owner' => fn (BelongsTo $ownerQuery) => $ownerQuery
                    ->withTrashed()
                    ->select(['users.id', 'users.name', 'users.email', 'users.is_active']),
                'currency:id,code,name,symbol',
                'businessProfile:workspace_id,business_name',
            ])
            ->withCount([
                'users as active_members_count' => function (Builder $memberQuery): void {
                    $memberQuery->where('workspace_user.is_active', true);
                },
                'clients',
                'invoices',
            ])
            ->when($filters['search'] ?? null, function (Builder $workspaceQuery, string $search): void {
                $workspaceQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('workspaces.name', 'like', '%'.$search.'%')
                        ->orWhere('workspaces.slug', 'like', '%'.$search.'%')
                        ->orWhere('workspaces.subdomain', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', function (Builder $ownerQuery) use ($search): void {
                            $ownerQuery
                                ->withTrashed()
                                ->where(function (Builder $ownerSearchQuery) use ($search): void {
                                    $ownerSearchQuery
                                        ->where('users.name', 'like', '%'.$search.'%')
                                        ->orWhere('users.email', 'like', '%'.$search.'%');
                                });
                        });
                });
            });

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('workspaces.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('workspaces.is_active', false);
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('workspaces.id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * @return Collection<int, User>
     */
    public function ownerOptions(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function currencyOptions(): Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Workspace
    {
        $this->ensureManagementAccess($actor);

        return DB::transaction(function () use ($data): Workspace {
            $workspace = Workspace::query()->create($this->workspaceAttributes($data));
            $workspace->users()->attach($workspace->owner_id, [
                'role' => 'owner',
                'is_active' => true,
            ]);
            $this->workspaceDefaultsService->provision($workspace);

            return $workspace->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Workspace $workspace, array $data): Workspace
    {
        $this->ensureManagementAccess($actor);

        return DB::transaction(function () use ($workspace, $data): Workspace {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $previousOwnerId = $lockedWorkspace->owner_id;
            $attributes = $this->workspaceAttributes($data);

            $lockedWorkspace->fill($attributes);
            $lockedWorkspace->save();

            if ((int) $previousOwnerId !== (int) $lockedWorkspace->owner_id && $previousOwnerId) {
                DB::table('workspace_user')
                    ->where('workspace_id', $lockedWorkspace->id)
                    ->where('user_id', $previousOwnerId)
                    ->where('role', 'owner')
                    ->update([
                        'role' => 'admin',
                        'updated_at' => now(),
                    ]);
            }

            $lockedWorkspace->users()->syncWithoutDetaching([
                $lockedWorkspace->owner_id => [
                    'role' => 'owner',
                    'is_active' => true,
                ],
            ]);

            return $lockedWorkspace->fresh();
        });
    }

    public function softDelete(User $actor, Workspace $workspace): void
    {
        if (! $actor->isPlatformOwner()) {
            throw new AuthorizationException('Only the global platform owner can delete a workspace through platform management.');
        }

        $this->lifecycleService->softDelete($workspace, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function workspaceAttributes(array $data): array
    {
        return [
            'owner_id' => (int) $data['owner_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'subdomain' => $data['subdomain'] ?? null,
            'invoice_prefix' => $data['invoice_prefix'] ?? 'INV',
            'metadata' => isset($data['metadata']) ? json_decode($data['metadata'], true, 512, JSON_THROW_ON_ERROR) : null,
            'currency_id' => isset($data['currency_id']) ? (int) $data['currency_id'] : null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function ensureManagementAccess(User $actor): void
    {
        if (! $actor->canManagePlatformUsers()) {
            throw new AuthorizationException('You are not authorized to manage platform workspaces.');
        }
    }
}
