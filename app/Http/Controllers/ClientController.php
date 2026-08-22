<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientIndexRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        // deny access if the user is not the workspace owner or admin
        if (! $workspace->canBeManagedBy(Auth::user())) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    public function index(ClientIndexRequest $request, Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $filters = array_merge([
            'search' => null,
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => 10,
        ], $request->validated());

        $clients = $workspace->clients()
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('clients.'.$filters['sort'], $filters['direction'])
            ->orderBy('clients.id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('client.index', [
            'workspace' => $workspace,
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function create(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('client.create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(StoreClientRequest $request, Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validated();

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

    public function update(UpdateClientRequest $request, Workspace $workspace, Client $client)
    {
        $this->authorizeWorkspaceUser($workspace);

        $client = $workspace->clients()->where('id', $client->id)->firstOrFail();

        $validated = $request->validated();

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
