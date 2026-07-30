<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreDeletedUserRequest;
use App\Models\User;
use App\Models\UserAccountAudit;
use App\Services\UserAccountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformUserRecoveryController extends Controller
{
    public function __construct(private readonly UserAccountService $userAccountService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = $validated['search'] ?? null;

        $users = User::onlyTrashed()
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('deleted_at')
            ->paginate(25)
            ->withQueryString();

        return view('platform.user-recovery.index', compact('users'));
    }

    public function show(int $userId): View
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        return view('platform.user-recovery.show', [
            'user' => $user,
            'audits' => UserAccountAudit::query()
                ->where('target_user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function restore(RestoreDeletedUserRequest $request, int $userId): RedirectResponse
    {
        $reason = $request->validated()['reason'] ?? null;

        $this->userAccountService->restoreDeletedUser(
            $request->user(),
            $userId,
            $reason,
        );

        return redirect()
            ->route('platform.user-recovery.index')
            ->with('success', 'The user account was restored. The user must sign in again.');
    }
}
