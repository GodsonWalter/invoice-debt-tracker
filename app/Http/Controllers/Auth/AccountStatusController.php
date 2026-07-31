<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    /**
     * Display the account availability notice after a blocked login attempt.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $status = $request->session()->pull('account_status');

        if (! in_array($status, ['deactivated', 'deleted'], true)) {
            return redirect()->route('login');
        }

        return view('auth.account-status', ['status' => $status]);
    }
}
