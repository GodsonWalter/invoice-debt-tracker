<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Workspace;
use App\Services\PaymentService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        if (! $workspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    public function store(Request $request, Workspace $workspace, Invoice $invoice, PaymentService $paymentService): RedirectResponse
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->where('id', $invoice->id)->firstOrFail();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentService->createPayment($workspace, $invoice, $validated);

        return redirect()
            ->route('invoices.show', [$workspace, $invoice])
            ->with('success', 'Payment recorded successfully.');

    }
}
