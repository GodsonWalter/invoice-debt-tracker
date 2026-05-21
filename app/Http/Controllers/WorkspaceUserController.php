<?php

namespace App\Http\Controllers;

use App\Mail\WorkspaceUserInvitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WorkspaceUserController extends Controller
{
    private function authorizeWorkspace(Workspace $workspace): void
    {
        abort_unless(Auth::id() === $workspace->owner_id, 403);
    }

    public function index(Workspace $workspace)
    {
        $this->authorizeWorkspace($workspace);

        $users = $workspace->users()->orderBy('name')->paginate(10);

        return view('workspace-users.index', compact('workspace', 'users'));
    }

    public function create(Workspace $workspace)
    {
        $this->authorizeWorkspace($workspace);

        return view('workspace-users.create', compact('workspace'));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:owner,admin,member,viewer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $isNewUser = false;
        $user = User::firstWhere('email', $validated['email']);

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Str::random(32),
            ]);

            $isNewUser = true;
            $user->sendEmailVerificationNotification();
        }

        if ($workspace->users()->where('user_id', $user->id)->exists()) {
            $workspace->users()->updateExistingPivot($user->id, [
                'role' => $validated['role'],
                'is_active' => $validated['is_active'],
            ]);
        } else {
            $workspace->users()->attach($user->id, [
                'role' => $validated['role'],
                'is_active' => $validated['is_active'],
            ]);
        }

        // Send workspace invitation email
        Mail::to($user->email)->send(new WorkspaceUserInvitation($workspace, $user, $validated['role']));

        $message = $isNewUser
            ? 'User created successfully. Verification and invitation emails have been sent to ' . $user->email
            : 'User invited to workspace successfully. Invitation email has been sent to ' . $user->email;

        return redirect()->route('workspace.users.index', $workspace)
            ->with('success', $message);
    }

    public function show(Workspace $workspace, User $user)
    {
        $this->authorizeWorkspace($workspace);

        $workspaceUser = $workspace->users()->where('user_id', $user->id)->firstOrFail();

        return view('workspace-users.show', [
            'workspace' => $workspace,
            'user' => $workspaceUser,
        ]);
    }

    public function edit(Workspace $workspace, User $user)
    {
        $this->authorizeWorkspace($workspace);

        $workspaceUser = $workspace->users()->where('user_id', $user->id)->firstOrFail();

        return view('workspace-users.edit', [
            'workspace' => $workspace,
            'user' => $workspaceUser,
        ]);
    }

    public function update(Request $request, Workspace $workspace, User $user)
    {
        $this->authorizeWorkspace($workspace);

        if ($user->id === $workspace->owner_id) {
            return back()->with('error', 'The workspace owner cannot be modified.');
        }

        abort_unless($workspace->users()->where('user_id', $user->id)->exists(), 404);

        
        $validated = $request->validate([            
            'role' => ['required', 'in:owner,admin,member,viewer'],
            'is_active' => ['required', 'boolean'],
        ]);        

        $workspace->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('workspace.users.show', [$workspace, $user])
            ->with('success', 'Workspace user updated successfully.');
    }

    public function destroy(Workspace $workspace, User $user)
    {
        $this->authorizeWorkspace($workspace);

        if ($user->id === $workspace->owner_id) {
            return back()->with('error', 'The workspace owner cannot be removed.');
        }

        $workspace->users()->detach($user->id);

        return redirect()->route('workspace.users.index', $workspace)
            ->with('success', 'Workspace user removed successfully.');
    }

    public function lookup(Request $request, Workspace $workspace)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::firstWhere('email', $request->email);

        if (! $user) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'verified' => (bool) $user->hasVerifiedEmail(),
            ],
        ]);
    }
}
