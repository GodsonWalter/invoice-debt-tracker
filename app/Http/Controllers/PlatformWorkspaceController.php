<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePlatformWorkspaceRequest;
use App\Http\Requests\PlatformWorkspaceIndexRequest;
use App\Http\Requests\StorePlatformWorkspaceRequest;
use App\Http\Requests\UpdatePlatformWorkspaceRequest;
use App\Models\Workspace;
use App\Services\PlatformWorkspaceManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformWorkspaceController extends Controller
{
    public function __construct(private readonly PlatformWorkspaceManagementService $workspaceManagementService) {}

    public function index(PlatformWorkspaceIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('platform.workspaces.index', [
            'workspaces' => $this->workspaceManagementService->paginatedWorkspaces($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('platform.workspaces.create', [
            'owners' => $this->workspaceManagementService->ownerOptions(),
            'currencies' => $this->workspaceManagementService->currencyOptions(),
        ]);
    }

    public function store(StorePlatformWorkspaceRequest $request): RedirectResponse
    {
        $this->workspaceManagementService->create($request->user(), $request->validated());

        return redirect()
            ->route('platform.workspaces.index')
            ->with('success', 'Platform workspace created successfully.');
    }

    public function edit(Workspace $workspace): View
    {
        return view('platform.workspaces.edit', [
            'workspace' => $workspace,
            'owners' => $this->workspaceManagementService->ownerOptions(),
            'currencies' => $this->workspaceManagementService->currencyOptions(),
        ]);
    }

    public function update(UpdatePlatformWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaceManagementService->update($request->user(), $workspace, $request->validated());

        return redirect()
            ->route('platform.workspaces.index')
            ->with('success', 'Platform workspace updated successfully.');
    }

    public function destroy(DeletePlatformWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['workspace_name'] !== $workspace->name) {
            return back()->withErrors([
                'workspace_name' => 'The workspace name must match exactly.',
            ]);
        }

        $this->workspaceManagementService->softDelete($request->user(), $workspace);

        return redirect()
            ->route('platform.workspaces.index')
            ->with('success', 'Workspace moved to platform recovery.');
    }
}
