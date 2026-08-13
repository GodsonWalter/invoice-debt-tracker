<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\Workspace;
use App\Services\CurrencyService;
use App\Services\WorkspaceDefaultsService;
use App\Services\WorkspaceLifecycleService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceLifecycleService $lifecycleService,
        private readonly WorkspaceDefaultsService $workspaceDefaultsService,
    ) {}

    private function authorizeActiveWorkspace(Workspace $workspace): void
    {
        $currentWorkspace = request()->currentWorkspace;

        if (! $currentWorkspace instanceof Workspace) {
            throw new HttpResponseException(
                redirect()->route('workspace.index')->with('error', 'Please switch to a workspace before accessing it.')
            );
        }

        if (! $currentWorkspace->is($workspace)) {
            throw new HttpResponseException(
                redirect()->route('workspace.index')->with('error', 'Please switch to this workspace before accessing it.')
            );
        }
    }

    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        if (! $workspace->canBeManagedBy(Auth::user())) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(WorkspaceIndexRequest $request)
    {
        $filters = array_merge([
            'search' => null,
            'status' => 'all',
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => 10,
        ], $request->validated());

        $query = Auth::user()->workspaces()
            ->when($filters['search'], function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('workspaces.name', 'like', '%'.$search.'%')
                        ->orWhere('workspaces.slug', 'like', '%'.$search.'%')
                        ->orWhere('workspaces.subdomain', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('workspaces.is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('workspaces.is_active', false));

        $sortColumn = $filters['sort'] === 'role'
            ? 'workspace_user.role'
            : 'workspaces.'.$filters['sort'];

        $workspaces = $query
            ->orderBy($sortColumn, $filters['direction'])
            ->orderBy('workspaces.id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        $activeWorkSpaces = Auth::user()->workspaces()
            ->wherePivot('is_active', true)
            ->whereNotNull('workspaces.subdomain')
            ->where('workspaces.subdomain', '<>', '')
            ->where('workspaces.is_active', true)
            ->orderBy('workspaces.name')
            ->get();

        $data['workspaces'] = $workspaces;
        $data['activeWorkSpaces'] = $activeWorkSpaces;
        $data['currentWorkspace'] = request()->currentWorkspace ?? null;
        $data['filters'] = $filters;

        return view('workspace.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Workspace $workspace, CurrencyService $currencyService)
    {
        // $this->authorizeWorkspaceUser($workspace);
        return view('workspace.create', [
            'defaultCurrency' => $currencyService->defaultCurrencyForUser(Auth::user()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Workspace $workspace, CurrencyService $currencyService): RedirectResponse
    {
        //  $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:workspaces,slug'],
            'subdomain' => ['nullable', 'string', 'max:255', 'unique:workspaces,subdomain'],
            'metadata' => ['nullable', 'json'],
        ]);

        DB::transaction(function () use ($validated, $currencyService): void {
            $workspace = Workspace::create([
                'owner_id' => Auth::id(),
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'subdomain' => $validated['subdomain'] ?? null,
                'currency_id' => $currencyService->defaultCurrencyForUser(Auth::user())?->id,
                'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
            ]);

            $workspace->users()->attach(Auth::id(), ['role' => 'owner', 'is_active' => true]);
            $this->workspaceDefaultsService->provision($workspace);
        });

        return redirect()->route('workspace.index')->with('success', 'Workspace created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace)
    {
        $this->authorizeActiveWorkspace($workspace);
        $this->authorizeWorkspaceUser($workspace);

        return view('workspace.show', [
            'workspace' => $workspace,
            'currentWorkspace' => $workspace,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workspace $workspace, CurrencyService $currencyService)
    {
        $this->authorizeActiveWorkspace($workspace);
        $this->authorizeWorkspaceUser($workspace);

        return view('workspace.edit', [
            'workspace' => $workspace,
            'currentWorkspace' => $workspace,
            'currencies' => $currencyService->activeCurrencies(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Workspace $workspace, CurrencyService $currencyService)
    {
        $this->authorizeActiveWorkspace($workspace);
        $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:workspaces,slug,'.$workspace->id],
            'subdomain' => ['nullable', 'string', 'max:255', 'unique:workspaces,subdomain,'.$workspace->id],
            'metadata' => ['nullable', 'json'],
            'invoice_prefix' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['required', $currencyService->activeCurrencyRule()],
            'is_active' => ['required', 'boolean'],
        ]);

        $workspace->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'subdomain' => $validated['subdomain'] ?? null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
            'invoice_prefix' => $validated['invoice_prefix'] ?? null,
            'currency_id' => $validated['currency_id'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->to($this->workspaceUrl($workspace, 'workspace.show', $workspace))
            ->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Workspace $workspace)
    {
        if ($workspace->trashed()) {
            abort(404);
        }

        $this->authorizeActiveWorkspace($workspace);

        if ($workspace->owner_id !== Auth::id()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'Only the workspace owner can delete the workspace.')
            );
        }

        $validated = $request->validate([
            'workspace_name' => ['required', 'string'],
        ]);

        if ($validated['workspace_name'] !== $workspace->name) {
            return back()->withErrors([
                'workspace_name' => 'The workspace name must match exactly.',
            ]);
        }

        $this->lifecycleService->softDelete($workspace, Auth::user());

        return redirect()->away($this->baseDomainUrl('dashboard'))
            ->with('success', 'Workspace moved to recovery status.');
    }

    /**
     * Exit the specified workspace for the current user.
     */
    public function exitWorkspace(Workspace $workspace)
    {
        if ($workspace->owner_id === Auth::id()) {
            return redirect()->route('workspace.index')
                ->with('error', 'Workspace owners cannot exit their own workspace.');
        }

        if (! $workspace->users()
            ->whereKey(Auth::id())
            ->wherePivot('is_active', true)
            ->exists()) {
            return redirect()->route('workspace.index')
                ->with('error', 'You are not a member of this workspace.');
        }

        $workspace->users()->detach(Auth::id());

        return redirect()->route('workspace.index')->with('success', 'You have exited the workspace.');
    }

    /**
     * Switch to the specified workspace.
     */
    public function switch(string $workspace)
    {
        $workspace = Auth::user()->workspaces()
            ->wherePivot('is_active', true)
            ->where('workspaces.subdomain', $workspace)
            ->where('workspaces.is_active', true)
            ->first();

        if (! $workspace) {
            return redirect()->route('workspace.index')
                ->with('error', 'You are not authorized to switch to this workspace.');
        }

        return redirect()->to($this->workspaceUrl($workspace, 'dashboard'))
            ->with('success', 'Switched to workspace: '.$workspace->name);
    }

    private function workspaceUrl(Workspace $workspace, string $routeName, mixed $parameters = []): string
    {
        $path = route($routeName, $parameters, false);
        $baseDomain = trim((string) config('app.base_domain'), '"');

        return request()->getScheme().'://'.$workspace->subdomain.'.'.$baseDomain.$path;
    }

    private function baseDomainUrl(string $routeName, mixed $parameters = []): string
    {
        $path = route($routeName, $parameters, false);
        $baseDomain = trim((string) config('app.base_domain'), '"');

        return request()->getScheme().'://'.$baseDomain.$path;
    }

    // /**
    //  * Join a workspace via a shareable link.
    //  */
    // public function join(Workspace $workspace)
    // {
    //     if ($workspace->users()->where('user_id', auth()->id())->exists()) {
    //         return redirect()->route('dashboard')
    //             ->with('info', 'You are already a member of this workspace.');
    //     }

    //     $workspace->users()->attach(auth()->id(), [
    //         'role' => 'member',
    //         'is_active' => true,
    //     ]);

    //     return redirect()->route('workspace.show', $workspace)
    //         ->with('success', 'You have joined the workspace.');
    // }
}
