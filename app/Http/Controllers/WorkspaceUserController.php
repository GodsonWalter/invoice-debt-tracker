<?php

namespace App\Http\Controllers;

use App\Mail\WorkspaceUserInvitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class WorkspaceUserController extends Controller
{
    private function authorizeWorkspaceUser(Workspace $workspace): void
    {      // deny access if the user is not the workspace owner or admin
        if (! $workspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
        
        
    }

    public function index(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $users = $workspace->users()->orderBy('name')->paginate(10);

        return view('workspace-users.index', compact('workspace', 'users'));
    }

    public function create(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('workspace-users.create', compact('workspace'));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:owner,admin,member,viewer'],
            // 'is_active' => ['required', 'boolean'],
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

        // Prevent inviting the workspace owner
        if ($user->id === $workspace->owner_id) {
            return back()->with('error', 'The workspace owner cannot be invited to their own workspace.');
        }

        // For invitations: require acceptance before activation.
        $activationToken = Str::random(64);

        $pivotData = [
            'role' => $validated['role'],
            // invited users must accept before becoming active
            'is_active' => false,
            'activation_token' => $activationToken,
        ];

        if ($workspace->users()->where('user_id', $user->id)->exists()) {
            $workspace->users()->updateExistingPivot($user->id, $pivotData);
        } else {
            $workspace->users()->attach($user->id, $pivotData);
        }

        // Send workspace invitation email with activation link
        Mail::to($user->email)->send(new WorkspaceUserInvitation($workspace, $user, $validated['role'], $activationToken));

        $message = $isNewUser
            ? 'User created successfully. Verification and invitation emails have been sent to ' . $user->email
            : 'User invited to workspace successfully. Invitation email has been sent to ' . $user->email;

        return redirect()->route('workspace.users.index', $workspace)
            ->with('success', $message);
    }

    public function show(Workspace $workspace, User $user)
    {
        $this->authorizeWorkspaceUser($workspace);

        $workspaceUser = $workspace->users()->where('user_id', $user->id)->firstOrFail();

        return view('workspace-users.show', [
            'workspace' => $workspace,
            'user' => $workspaceUser,
        ]);
    }

    public function edit(Workspace $workspace, User $user)
    {
        $this->authorizeWorkspaceUser($workspace);

        $workspaceUser = $workspace->users()->where('user_id', $user->id)->firstOrFail();

        return view('workspace-users.edit', [
            'workspace' => $workspace,
            'user' => $workspaceUser,
        ]);
    }

    public function update(Request $request, Workspace $workspace, User $user)
    {
        $this->authorizeWorkspaceUser($workspace);

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
        $this->authorizeWorkspaceUser($workspace);

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

    /**
     * Accept an invitation using the activation token.
     */
    public function acceptInvitation(Request $request, string $token)
    {
        // find pivot row by token
        $row = DB::table('workspace_user')->where('activation_token', $token)->first();

        if (! $row) {
            abort(404);
        }

        // require authentication (route also has auth middleware)
        if (! auth()->check()) {
            return redirect()->route('login')->with('warning', 'Please log in to accept the invitation.');
        }

        // ensure the logged in user matches the invited user
        if (auth()->id() !== $row->user_id) {
            return redirect()->route('dashboard')->with('error', 'This invitation is not for your account.');
        }

        // activate the pivot
        $updated = DB::table('workspace_user')->where('id', $row->id)->update([
            'is_active' => true,
            'activation_token' => null,
            'updated_at' => now(),
        ]);

        if (! $updated) {
            return redirect()->route('dashboard')->with('error', 'Unable to activate invitation.');
        }

        return redirect()->route('workspace.show', $row->workspace_id)->with('success', 'You have joined the workspace.');
    }
}
