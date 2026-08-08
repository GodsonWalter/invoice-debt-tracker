<?php

namespace App\Services;

use App\Models\ImpersonationAudit;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonation';

    public const EVENT_STARTED = 'started';

    public const EVENT_ENDED = 'ended';

    public const EVENT_ACTION = 'action';

    public function isImpersonating(): bool
    {
        return app()->bound('session.store')
            && is_array(session()->get(self::SESSION_KEY));
    }

    /**
     * @return array{impersonator: User, workspaceOwner: User, workspace: Workspace, sessionId: string, startedAt: Carbon, startedAuditId: ?int}|null
     */
    public function context(): ?array
    {
        $data = $this->sessionData();

        if ($data === null) {
            return null;
        }

        $impersonator = User::query()->find($data['impersonator_id']);
        $workspaceOwner = User::query()->find($data['impersonated_user_id']);
        $workspace = Workspace::query()->find($data['workspace_id']);

        if (! $impersonator || ! $workspaceOwner || ! $workspace) {
            return null;
        }

        return [
            'impersonator' => $impersonator,
            'workspaceOwner' => $workspaceOwner,
            'workspace' => $workspace,
            'sessionId' => (string) $data['session_id'],
            'startedAt' => Carbon::parse((string) $data['started_at']),
            'startedAuditId' => isset($data['started_audit_id']) ? (int) $data['started_audit_id'] : null,
        ];
    }

    public function start(User $impersonator, User $workspaceOwner, Workspace $workspace): void
    {
        $this->assertCanStart($impersonator, $workspaceOwner, $workspace);

        $sessionId = (string) Str::uuid();
        $startedAt = now();
        $audit = $this->recordAudit(
            sessionId: $sessionId,
            impersonatorId: $impersonator->id,
            workspaceOwnerId: $workspaceOwner->id,
            workspaceId: $workspace->id,
            event: self::EVENT_STARTED,
            startedAt: $startedAt,
            metadata: ['route' => request()->route()?->getName()],
        );

        session()->put(self::SESSION_KEY, [
            'session_id' => $sessionId,
            'impersonator_id' => $impersonator->id,
            'impersonated_user_id' => $workspaceOwner->id,
            'workspace_id' => $workspace->id,
            'started_at' => $startedAt->toIso8601String(),
            'started_audit_id' => $audit->id,
        ]);

        Auth::guard('web')->setUser($workspaceOwner);
    }

    /**
     * @return array{impersonator: User, workspaceOwner: User, workspace: Workspace, sessionId: string, startedAt: Carbon, endedAt: Carbon}|null
     */
    public function end(): ?array
    {
        $data = $this->sessionData();

        if ($data === null) {
            return null;
        }

        $endedAt = now();
        if (! empty($data['started_audit_id'])) {
            ImpersonationAudit::query()
                ->whereKey((int) $data['started_audit_id'])
                ->update(['ended_at' => $endedAt]);
        }

        $this->recordAudit(
            sessionId: (string) $data['session_id'],
            impersonatorId: (int) $data['impersonator_id'],
            workspaceOwnerId: (int) $data['impersonated_user_id'],
            workspaceId: (int) $data['workspace_id'],
            event: self::EVENT_ENDED,
            action: 'impersonation.exit',
            startedAt: Carbon::parse((string) $data['started_at']),
            endedAt: $endedAt,
            metadata: ['started_audit_id' => $data['started_audit_id'] ?? null],
        );

        session()->forget(self::SESSION_KEY);

        $impersonator = User::query()->find((int) $data['impersonator_id']);
        if ($impersonator) {
            Auth::guard('web')->setUser($impersonator);
        }

        $workspaceOwner = User::withTrashed()->find((int) $data['impersonated_user_id']);
        $workspace = Workspace::withTrashed()->find((int) $data['workspace_id']);

        if (! $impersonator || ! $workspaceOwner || ! $workspace) {
            return null;
        }

        return [
            'impersonator' => $impersonator,
            'workspaceOwner' => $workspaceOwner,
            'workspace' => $workspace,
            'sessionId' => (string) $data['session_id'],
            'startedAt' => Carbon::parse((string) $data['started_at']),
            'endedAt' => $endedAt,
        ];
    }

    public function activate(Request $request): ?array
    {
        $context = $this->context();

        if (! $this->isImpersonating()) {
            return null;
        }

        $authenticatedUser = Auth::guard('web')->user();
        if (! $context || ! $authenticatedUser || ! $authenticatedUser->is($context['impersonator'])) {
            $this->end();

            throw new AuthorizationException('The impersonation session is no longer valid.');
        }

        if (! $this->isValidContext($context)) {
            $this->end();

            throw new AuthorizationException('The impersonation session is no longer valid.');
        }

        Auth::guard('web')->setUser($context['workspaceOwner']);
        $request->setUserResolver(fn (?string $guard = null): ?User => Auth::guard($guard ?: 'web')->user());
        $request->attributes->set('impersonationContext', $context);
        view()->share('impersonationContext', $context);

        return $context;
    }

    public function allowsRequest(Request $request, ?Workspace $currentWorkspace, Workspace $impersonatedWorkspace): bool
    {
        if ($request->routeIs('impersonation.exit', 'logout')) {
            return true;
        }

        if (! $currentWorkspace || ! $currentWorkspace->is($impersonatedWorkspace)) {
            return false;
        }

        return ! $request->routeIs(
            'platform.*',
            'currencies.*',
            'workspace.recovery.*',
            'workspace.index',
            'workspace.create',
            'workspace.store',
            'workspace.switch',
            'workspace.exit',
            'workspace.users.accept',
        );
    }

    public function recordAction(Request $request, ?int $statusCode = null): void
    {
        $context = $this->context();

        if (! $context || $request->routeIs('impersonation.exit', 'logout') || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $route = $request->route()?->getName() ?: $request->method().' '.$request->path();

        $this->recordAudit(
            sessionId: $context['sessionId'],
            impersonatorId: $context['impersonator']->id,
            workspaceOwnerId: $context['workspaceOwner']->id,
            workspaceId: $context['workspace']->id,
            event: self::EVENT_ACTION,
            action: $route,
            startedAt: $context['startedAt'],
            statusCode: $statusCode,
            metadata: ['method' => $request->method()],
        );
    }

    public function workspaceDashboardUrl(Workspace $workspace): string
    {
        $baseDomain = trim((string) config('app.base_domain'), '"');

        return request()->getScheme().'://'.$workspace->subdomain.'.'.$baseDomain.route('workspace.dashboard', $workspace, false);
    }

    private function assertCanStart(User $impersonator, User $workspaceOwner, Workspace $workspace): void
    {
        if ($this->isImpersonating()) {
            throw new AuthorizationException('Impersonation sessions cannot be chained.');
        }

        if (! in_array($impersonator->role, ['owner', 'admin'], true)) {
            throw new AuthorizationException('Only platform owners and admins can impersonate workspace owners.');
        }

        if ($impersonator->is($workspaceOwner)) {
            throw new AuthorizationException('You cannot impersonate yourself.');
        }

        $this->assertWorkspaceOwnerTarget($workspaceOwner, $workspace);
    }

    private function assertWorkspaceOwnerTarget(User $workspaceOwner, Workspace $workspace): void
    {

        if ($workspaceOwner->trashed() || ! $workspaceOwner->is_active) {
            throw new AuthorizationException('The workspace owner is not active.');
        }

        if ($workspace->trashed() || ! $workspace->is_active || (int) $workspace->owner_id !== (int) $workspaceOwner->id) {
            throw new AuthorizationException('The selected workspace is not available for impersonation.');
        }

        $isOwner = $workspace->users()
            ->whereKey($workspaceOwner->id)
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true)
            ->exists();

        if (! $isOwner) {
            throw new AuthorizationException('The selected user is not an active owner of this workspace.');
        }
    }

    /**
     * @param  array{impersonator: User, workspaceOwner: User, workspace: Workspace, sessionId: string, startedAt: Carbon, startedAuditId: ?int}  $context
     */
    private function isValidContext(array $context): bool
    {
        try {
            if (! in_array($context['impersonator']->role, ['owner', 'admin'], true)
                || $context['impersonator']->is($context['workspaceOwner'])) {
                return false;
            }

            $this->assertWorkspaceOwnerTarget($context['workspaceOwner'], $context['workspace']);
        } catch (AuthorizationException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sessionData(): ?array
    {
        $data = session()->get(self::SESSION_KEY);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(
        string $sessionId,
        int $impersonatorId,
        int $workspaceOwnerId,
        int $workspaceId,
        string $event,
        Carbon $startedAt,
        ?Carbon $endedAt = null,
        ?string $action = null,
        ?int $statusCode = null,
        array $metadata = [],
    ): ImpersonationAudit {
        return ImpersonationAudit::query()->create([
            'session_id' => $sessionId,
            'impersonator_user_id' => $impersonatorId,
            'impersonated_user_id' => $workspaceOwnerId,
            'workspace_id' => $workspaceId,
            'event' => $event,
            'action' => $action,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'status_code' => $statusCode,
            'metadata' => $metadata,
            'ip_address' => app()->bound('request') ? request()->ip() : null,
            'user_agent' => app()->bound('request') ? request()->userAgent() : null,
        ]);
    }
}
