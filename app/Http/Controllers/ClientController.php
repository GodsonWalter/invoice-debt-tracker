<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClientController extends Controller
{
    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        // deny access if the user is not the workspace owner or admin
        if (! $workspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    public function index(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $clients = $workspace->clients()->orderBy('created_at', 'desc')->paginate(10);

        return view('client.index', [
            'workspace' => $workspace,
            'clients' => $clients,
        ]);
    }

    public function create(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('client.create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $email = $validated['email'] ?? null;

        // Prevent duplicate email within the same workspace
        if ($email) {
            $alreadyExists = $workspace->clients()
                ->where('email', $email)
                ->exists();

            if ($alreadyExists) {
                return back()
                    ->withInput()
                    ->withErrors(['email' => 'A client with this email already exists in the current workspace.'])
                    ->with('error', 'Failed to create client. Please fix the errors and try again.');
            }
        }

        $workspace->clients()->create([
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);


        return redirect()->route('clients.index', $workspace)->with('success', 'Client created successfully.');
    }

    public function show(Workspace $workspace, Client $client)
    {
        $this->authorizeWorkspaceUser($workspace);

        $client = $workspace->clients()->where('id', $client->id)->firstOrFail();

        return view('client.show', [
            'workspace' => $workspace,
            'client' => $client,
        ]);
    }

    public function edit(Workspace $workspace, Client $client)
    {
        $this->authorizeWorkspaceUser($workspace);

        $client = $workspace->clients()->where('id', $client->id)->firstOrFail();

        return view('client.edit', [
            'workspace' => $workspace,
            'client' => $client,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Client $client)
    {
        $this->authorizeWorkspaceUser($workspace);

        $client = $workspace->clients()->where('id', $client->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $email = $validated['email'] ?? null;

        // Prevent duplicate email within the same workspace (excluding the current client)
        if ($email) {
            $emailAlreadyUsed = $workspace->clients()
                ->where('email', $email)
                ->where('id', '!=', $client->id)
                ->exists();

            if ($emailAlreadyUsed) {
                return back()
                    ->withInput()
                    ->withErrors(['email' => 'A client with this email already exists in the current workspace.'])
                    ->with('error', 'Failed to update client. Please fix the errors and try again.');
            }
        }

        $client->update([
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);


        return redirect()->route('clients.show', [$workspace, $client])
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Workspace $workspace, Client $client)
    {
        $this->authorizeWorkspaceUser($workspace);

        $client = $workspace->clients()->where('id', $client->id)->firstOrFail();

        $client->delete();

        return redirect()->route('clients.index', $workspace)->with('success', 'Client deleted successfully.');
    }
}

