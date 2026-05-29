<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Workspace;
use App\Services\CurrencyService;
use App\Services\InvoiceService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
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

        $invoices = $workspace->invoices()
            ->with(['client', 'currency'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('invoice.index', [
            'workspace' => $workspace,
            'invoices' => $invoices,
        ]);
    }

    public function create(Workspace $workspace, CurrencyService $currencyService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $clients = $workspace->clients()->orderBy('name')->get();
        $invoiceNumber = app(InvoiceService::class)->generateInvoiceNumber($workspace);

        return view('invoice.create', [
            'workspace' => $workspace,
            'clients' => $clients,
            'invoiceNumber' => $invoiceNumber,
            'currencies' => $currencyService->activeCurrencies(),
            'defaultCurrency' => $currencyService->defaultCurrencyForWorkspace($workspace),
        ]);
    }

    public function store(Request $request, Workspace $workspace, InvoiceService $invoiceService, CurrencyService $currencyService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'currency_id' => ['required', $currencyService->activeCurrencyRule()],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', 'in:draft,sent,paid,overdue'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $invoice = $invoiceService->createInvoice($workspace, $validated);

            return redirect()->route('invoices.show', [$workspace, $invoice])->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            return back()->with(
                'error',
                'Failed to create invoice: '.$e->getMessage(),
            );
        }
    }

    public function show(Workspace $workspace, Invoice $invoice)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()
            ->with([
                'client',
                'currency',
                'items',
                'payments' => fn ($query) => $query
                    ->orderByDesc('payment_date')
                    ->orderByDesc('created_at'),
            ])
            ->where('id', $invoice->id)
            ->firstOrFail();

        return view('invoice.show', [
            'workspace' => $workspace,
            'invoice' => $invoice,
        ]);
    }

    public function edit(Workspace $workspace, Invoice $invoice, CurrencyService $currencyService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->with(['currency', 'items'])->where('id', $invoice->id)->firstOrFail();
        $clients = $workspace->clients()->orderBy('name')->get();

        return view('invoice.edit', [
            'workspace' => $workspace,
            'invoice' => $invoice,
            'clients' => $clients,
            'currencies' => $currencyService->activeCurrencies(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Invoice $invoice, InvoiceService $invoiceService, CurrencyService $currencyService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->where('id', $invoice->id)->firstOrFail();

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', 'unique:invoices,invoice_number,'.$invoice->id.',id,workspace_id,'.$workspace->id],
            'client_id' => ['required', 'exists:clients,id'],
            'currency_id' => ['required', $currencyService->activeCurrencyRule()],
            'issue_date' => ['required', 'date'],

            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', 'in:draft,sent,paid,overdue'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $invoice = $invoiceService->updateInvoice($workspace, $validated, $invoice);

            return redirect()->route('invoices.show', [$workspace, $invoice])->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            return back()->with(
                'error',
                'Failed to update invoice: '.$e->getMessage(),
            );
        }

        return redirect()->route('invoices.show', [$workspace, $invoice])->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Workspace $workspace, Invoice $invoice)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->where('id', $invoice->id)->firstOrFail();

        $invoice->delete();

        return redirect()->route('invoices.index', $workspace)->with('success', 'Invoice deleted successfully.');
    }
}
