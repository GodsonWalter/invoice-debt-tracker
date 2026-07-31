<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkspaceRecoveryExpiredException;
use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Services\WorkspaceLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class WorkspaceRecoveryController extends Controller
{
    public function __construct(private WorkspaceLifecycleService $lifecycleService) {}

    public function index()
    {
        $workspaces = Workspace::onlyTrashed()
            ->where('owner_id', Auth::id())
            ->latest('deleted_at')
            ->paginate(10)
            ->withQueryString();

        $workspaces->getCollection()->transform(fn (Workspace $workspace): array => [
            'workspace' => $workspace,
            'lifecycle' => $this->lifecycleService->summary($workspace),
        ]);

        return view('workspace-recovery.index', compact('workspaces'));
    }

    public function show(int $workspaceId)
    {
        $workspace = $this->ownedDeletedWorkspace($workspaceId);

        return view('workspace-recovery.show', [
            'workspace' => $workspace,
            'lifecycle' => $this->lifecycleService->summary($workspace),
            'audits' => $workspace->id
                ? WorkspaceLifecycleAudit::query()->where('workspace_id', $workspace->id)->latest()->get()
                : collect(),
        ]);
    }

    public function restore(int $workspaceId)
    {
        $workspace = $this->ownedDeletedWorkspace($workspaceId);

        try {
            $this->lifecycleService->restoreAsOwner($workspace, Auth::user());
        } catch (WorkspaceRecoveryExpiredException|AuthorizationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.recovery.index')
            ->with('success', 'Workspace restored. Switch to it from the workspace list when you are ready.');
    }

    public function audit(int $workspaceId)
    {
        $workspace = $this->ownedDeletedWorkspace($workspaceId);

        return view('workspace-recovery.audit', [
            'workspace' => $workspace,
            'audits' => WorkspaceLifecycleAudit::query()
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    private function ownedDeletedWorkspace(int $workspaceId): Workspace
    {
        return Workspace::onlyTrashed()
            ->where('owner_id', Auth::id())
            ->findOrFail($workspaceId);
    }
}
