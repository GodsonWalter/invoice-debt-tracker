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
        if ($authUser->id !== $workspace->owner_id) {
            return false;
        }

        if ($workspaceUser->id === $workspace->owner_id) {
            return false;
        }

        return $workspace->users()->where('user_id', $workspaceUser->id)->exists();
    }
}
