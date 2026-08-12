<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ImpersonationService;

class TestimonialPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasActiveMember($user);
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $testimonial->workspace?->hasActiveMember($user) ?? false;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->canBeManagedBy($user);
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $this->canManageWorkspaceTestimonial($user, $testimonial)
            && in_array($testimonial->status, Testimonial::WORKSPACE_EDITABLE_STATUSES, true);
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->update($user, $testimonial);
    }

    public function submit(User $user, Testimonial $testimonial): bool
    {
        return $this->canManageWorkspaceTestimonial($user, $testimonial)
            && in_array($testimonial->status, Testimonial::WORKSPACE_EDITABLE_STATUSES, true);
    }

    public function moderate(User $user, Testimonial $testimonial): bool
    {
        return $user->canManagePlatformUsers()
            && ! app(ImpersonationService::class)->isImpersonating();
    }

    private function canManageWorkspaceTestimonial(User $user, Testimonial $testimonial): bool
    {
        return $testimonial->workspace?->canBeManagedBy($user) ?? false;
    }
}
