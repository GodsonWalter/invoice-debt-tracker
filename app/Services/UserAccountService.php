<?php

namespace App\Services;

use App\Exceptions\AccountDeletionBlocked;
use App\Models\User;
use App\Models\UserAccountAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class UserAccountService
{
    public const EVENT_DELETED = 'user_account_deleted';

    public const EVENT_RESTORED = 'user_account_restored';

    public function canRestoreDeletedUsers(User $actor): bool
    {
        return $actor->isPlatformOwner();
    }

    public function canDeleteAccount(User $user): bool
    {
        return $user->role === 'user';
    }

    public function softDelete(User $user): void
    {
        if (! $this->canDeleteAccount($user)) {
            throw new AuthorizationException('Platform-level accounts cannot be deleted through self-service.');
        }

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->trashed()) {
                return;
            }

            if ($lockedUser->ownedWorkspaces()->lockForUpdate()->exists()) {
                throw new AccountDeletionBlocked(
                    'Transfer ownership of all non-deleted workspaces before deleting this account.',
                );
            }

            $this->revokeUserSessionsAndTokens($lockedUser);
            $this->recordAudit(
                target: $lockedUser,
                actor: $lockedUser,
                actorType: 'self_service',
                event: self::EVENT_DELETED,
                metadata: ['session_driver' => config('session.driver')],
            );

            $lockedUser->forceFill(['remember_token' => null])->save();
            $lockedUser->delete();
        });

        $user->setRememberToken(null);
    }

    public function restoreDeletedUser(User $actor, int $userId, ?string $reason = null): User
    {
        if (! $this->canRestoreDeletedUsers($actor)) {
            throw new AuthorizationException('Only the global platform owner can restore deleted user accounts.');
        }

        return DB::transaction(function () use ($actor, $userId, $reason): User {
            $deletedUser = User::onlyTrashed()->lockForUpdate()->findOrFail($userId);

            if (User::query()->where('email', $deletedUser->email)->exists()) {
                throw new AuthorizationException('The account cannot be restored because its email address is already in use.');
            }

            $deletedUser->restore();
            $this->recordAudit(
                target: $deletedUser,
                actor: $actor,
                actorType: 'platform_owner',
                event: self::EVENT_RESTORED,
                reason: $reason,
            );

            return $deletedUser->fresh();
        });
    }

    private function revokeUserSessionsAndTokens(User $user): void
    {
        DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
            ->where('email', $user->email)
            ->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(
        User $target,
        User $actor,
        string $actorType,
        string $event,
        ?string $reason = null,
        array $metadata = [],
    ): UserAccountAudit {
        return UserAccountAudit::query()->create([
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'actor_user_id' => $actor->id,
            'actor_global_role' => $actor->role,
            'actor_type' => $actorType,
            'event' => $event,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => app()->bound('request') ? request()->ip() : null,
            'user_agent' => app()->bound('request') ? request()->userAgent() : null,
        ]);
    }
}
