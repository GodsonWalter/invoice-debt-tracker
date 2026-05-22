<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {       
        $workspace = Auth::user()->workspaces()->orderBy('created_at', 'desc');
        $data['workspaces'] = $workspace->paginate(10);
        $data['activeWorkSpaces'] = $workspace->whereNotNull('subdomain')->where('workspaces.is_active', true)->get();

        return view('workspace.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('workspace.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
        abort_unless(Auth::id() === $workspace->owner_id, 403);

        return view('workspace.show', [
            'workspace' => $workspace,
            'currentWorkspace' => $workspace,
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workspace $workspace)
    {
        abort_unless(Auth::id() === $workspace->owner_id, 403);

        return view('workspace.edit', [
            'workspace' => $workspace,
            'currentWorkspace' => $workspace,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Workspace $workspace)
    {
        abort_unless(Auth::id() === $workspace->owner_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:workspaces,slug,' . $workspace->id],
            'subdomain' => ['nullable', 'string', 'max:255', 'unique:workspaces,subdomain,' . $workspace->id],
            'metadata' => ['nullable', 'json'],
            'is_active' => ['required', 'boolean'],
        ]);

        $workspace->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'subdomain' => $validated['subdomain'] ?? null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('workspace.show', $workspace)->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workspace $workspace)
    {
        abort_unless(Auth::id() === $workspace->owner_id, 403);

        $workspace->delete();

        return redirect()->route('workspace.index')->with('success', 'Workspace deleted successfully');
    }

    /**
     * Switch to the specified workspace.
     */
    public function switch(string $workspace)
    {
        $workspace = Workspace::where('subdomain', $workspace)->firstOrFail();

        abort_unless(Auth::id() === $workspace->owner_id, 403);

        return redirect()->route('dashboard')->with('success', 'Switched to workspace: ' . $workspace->name);
       
        }
}
