<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Services\WorkspaceLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformWorkspaceRecoveryController extends Controller
{
    public function __construct(private WorkspaceLifecycleService $lifecycleService) {}

    public function index(Request $request)
    {
        $query = Workspace::onlyTrashed()->with('owner')->latest('deleted_at');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $this->applyStateFilter($query, $request->string('state')->toString());

        $workspaces = $query->paginate(25)->withQueryString();
        $workspaces->getCollection()->transform(fn (Workspace $workspace): array => [
            'workspace' => $workspace,
            'lifecycle' => $this->lifecycleService->summary($workspace),
        ]);

        return view('platform.workspace-recovery.index', compact('workspaces'));
    }

    public function show(int $workspaceId)
    {
        $workspace = $this->deletedWorkspace($workspaceId);

        return view('platform.workspace-recovery.show', [
            'workspace' => $workspace,
            'lifecycle' => $this->lifecycleService->summary($workspace),
            'audits' => WorkspaceLifecycleAudit::query()->where('workspace_id', $workspace->id)->latest()->get(),
        ]);
    }

    public function restore(Request $request, int $workspaceId)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $workspace = $this->deletedWorkspace($workspaceId);

        try {
            $this->lifecycleService->restoreAsPlatformOwner($workspace, Auth::user(), $validated['reason']);
        } catch (AuthorizationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('platform.recovery.index')
            ->with('success', 'Workspace restored through platform support.');
    }

    public function audits(Request $request)
    {
        $query = WorkspaceLifecycleAudit::query()->latest();

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        return view('platform.workspace-recovery.audits', [
            'audits' => $query->paginate(50)->withQueryString(),
        ]);
    }

    private function deletedWorkspace(int $workspaceId): Workspace
    {
        return Workspace::onlyTrashed()->findOrFail($workspaceId);
    }

    private function applyStateFilter($query, string $state): void
    {
        $now = CarbonImmutable::now();
        $restoreCutoff = $now->subDays((int) config('workspace-lifecycle.self_service_restore_days'));
        $permanentCutoff = $now->subDays((int) config('workspace-lifecycle.permanent_deletion_days'));
        $warningCutoff = $permanentCutoff->addDays(max(config('workspace-lifecycle.permanent_deletion_warning_days', [30])));

        match ($state) {
            WorkspaceLifecycleService::STATE_RECOVERABLE => $query->where('deleted_at', '>=', $restoreCutoff),
            WorkspaceLifecycleService::STATE_RESTORE_EXPIRED => $query->where('deleted_at', '<', $restoreCutoff)->where('deleted_at', '>', $warningCutoff),
            WorkspaceLifecycleService::STATE_PENDING_PERMANENT_DELETION => $query->where('deleted_at', '<=', $warningCutoff)->where('deleted_at', '>', $permanentCutoff),
            WorkspaceLifecycleService::STATE_PERMANENT_DELETION_DUE => $query->where('deleted_at', '<=', $permanentCutoff),
            default => null,
        };
    }
}
