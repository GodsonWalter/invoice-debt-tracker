<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\CurrencyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
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
    public function index(Request $request, Workspace $workspace)
    {
        $workspace = Auth::user()->workspaces()->orderBy('created_at', 'desc');
        $data['workspaces'] = $workspace->paginate(10);
        $data['activeWorkSpaces'] = $workspace->whereNotNull('subdomain')->where('workspaces.is_active', true)->get();

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
    public function store(Request $request, Workspace $workspace, CurrencyService $currencyService)
    {
        //  $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:workspaces,slug'],
            'subdomain' => ['nullable', 'string', 'max:255', 'unique:workspaces,subdomain'],
            'metadata' => ['nullable', 'json'],
        ]);

        $workspace = Workspace::create(
            [
                'owner_id' => Auth::id(),
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'subdomain' => $validated['subdomain'] ?? null,
                'currency_id' => $currencyService->defaultCurrencyForUser(Auth::user())?->id,
                'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
            ]
        );

        $workspace->users()->attach(Auth::id(), ['role' => 'owner', 'is_active' => true]);

        return redirect()->route('workspace.index')->with('success', 'Workspace created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace)
    {
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

        return redirect()->route('workspace.show', $workspace)->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $workspace->delete();

        return redirect()->route('workspace.index')->with('success', 'Workspace deleted successfully');
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

        if (! $workspace->users()->where('user_id', Auth::id())->exists()) {
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
        $workspace = Workspace::where('subdomain', $workspace)->firstOrFail();

        return redirect()->route('dashboard')->with('success', 'Switched to workspace: '.$workspace->name);
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
