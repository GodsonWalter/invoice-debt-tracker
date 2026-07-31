<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePlatformUserRequest;
use App\Http\Requests\PlatformUserIndexRequest;
use App\Http\Requests\StorePlatformUserRequest;
use App\Http\Requests\UpdatePlatformUserRequest;
use App\Models\User;
use App\Services\PlatformUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformUserController extends Controller
{
    public function __construct(private readonly PlatformUserManagementService $userManagementService) {}

    public function index(PlatformUserIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('platform.users.index', [
            'users' => $this->userManagementService->paginatedUsers($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('platform.users.create', [
            'roles' => User::PLATFORM_ROLES,
        ]);
    }

    public function store(StorePlatformUserRequest $request): RedirectResponse
    {
        $user = $this->userManagementService->create($request->user(), $request->validated());
        $user->sendEmailVerificationNotification();

        return redirect()
            ->route('platform.users.index')
            ->with('success', 'Platform user created successfully.');
    }

    public function edit(User $user): View
    {
        abort_unless($this->userManagementService->canModifyTarget(request()->user(), $user), 403);

        return view('platform.users.edit', [
            'user' => $user,
            'roles' => User::PLATFORM_ROLES,
        ]);
    }

    public function update(UpdatePlatformUserRequest $request, User $user): RedirectResponse
    {
        $this->userManagementService->update($request->user(), $user, $request->validated());

        return redirect()
            ->route('platform.users.index')
            ->with('success', 'Platform user updated successfully.');
    }

    public function destroy(DeletePlatformUserRequest $request, User $user): RedirectResponse
    {
        $this->userManagementService->softDelete($request->user(), $user);

        return redirect()
            ->route('platform.users.index')
            ->with('success', 'Platform user and owned workspaces were deactivated.');
    }
}
