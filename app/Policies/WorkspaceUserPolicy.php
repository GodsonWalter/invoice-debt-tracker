<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceUserPolicy
{
    /**
     * Determine whether the authenticated user can remove another workspace user.
     */
    public function delete(User $authUser, Workspace $workspace, User $workspaceUser): bool
    {
        if (! $workspace->canBeManagedBy($authUser)) {
            return false;
        }

        if ($workspaceUser->id === $workspace->owner_id) {
            return false;
        }

        return $workspace->users()
            ->whereKey($workspaceUser->getKey())
            ->wherePivot('is_active', true)
            ->exists();
    }
}
