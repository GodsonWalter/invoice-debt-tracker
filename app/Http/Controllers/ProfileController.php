<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountDeletionBlocked;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\CurrencyService;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, CurrencyService $currencyService): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'currencies' => $currencyService->activeCurrencies(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('profile-avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, UserAccountService $userAccountService): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (! $userAccountService->canDeleteAccount($user)) {
            abort(403, 'Platform-level accounts cannot be deleted through self-service.');
        }

        try {
            $userAccountService->softDelete($user);
        } catch (AccountDeletionBlocked $exception) {
            return back()
                ->withInput()
                ->withErrors(['account' => $exception->getMessage()], 'userDeletion');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Account deleted successfully.');
    }
}
