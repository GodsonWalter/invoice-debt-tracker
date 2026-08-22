<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceIndexRequest;
use App\Models\Invoice;
use App\Models\Workspace;
use App\Services\CurrencyService;
use App\Services\InvoiceEmailService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\WhatsAppMessageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
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

    public function index(InvoiceIndexRequest $request, Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        $filters = array_merge([
            'search' => null,
            'status' => null,
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => 10,
        ], $request->validated());

        $invoices = $workspace->invoices()
            ->with(['client', 'currency'])
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereHas('client', function (Builder $clientQuery) use ($search): void {
                            $clientQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->orderBy('invoices.'.$filters['sort'], $filters['direction'])
            ->orderBy('invoices.id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('invoice.index', [
            'workspace' => $workspace,
            'invoices' => $invoices,
            'filters' => $filters,
            'statuses' => Invoice::STATUSES,
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
            'status' => ['required', Rule::in([Invoice::STATUS_DRAFT, Invoice::STATUS_SENT])],
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

    public function show(Workspace $workspace, Invoice $invoice, PaymentService $paymentService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()
            ->with([
                'client',
                'currency',
                'items',
                'workspace.businessProfile',
                'workspace.currency',
                'emailLogs' => fn ($query) => $query
                    ->with('sender')
                    ->orderByDesc('created_at'),
                'payments' => fn ($query) => $query
                    ->orderByDesc('payment_date')
                    ->orderByDesc('created_at'),
            ])
            ->where('id', $invoice->id)
            ->firstOrFail();

        return view('invoice.show', [
            'workspace' => $workspace,
            'invoice' => $invoice,
            'paymentTimeline' => $paymentService->paymentTimeline($invoice),
        ]);
    }

    public function downloadPdf(Workspace $workspace, Invoice $invoice, InvoicePdfService $invoicePdfService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()
            ->with([
                'client',
                'items',
                'payments',
                'workspace.businessProfile',
                'workspace.currency',
                'currency',
            ])
            ->where('id', $invoice->id)
            ->firstOrFail();

        return response($invoicePdfService->content($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$invoicePdfService->filename($invoice),
        ]);
    }

    public function send(Workspace $workspace, Invoice $invoice, InvoiceEmailService $invoiceEmailService)
    {
        $this->authorizeWorkspaceUser($workspace);

        try {
            $invoiceEmailService->queueInvoice($workspace, $invoice, Auth::user());
        } catch (ValidationException $exception) {
            return redirect()->route('invoices.show', [$workspace, $invoice])
                ->withErrors(new MessageBag($exception->errors()))
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()
            ->route('invoices.show', [$workspace, $invoice])
            ->with('success', 'Invoice email has been queued for sending.');
    }

    public function sendToWhatsApp(
        Workspace $workspace,
        Invoice $invoice,
        WhatsAppMessageService $whatsappMessageService,
    ): RedirectResponse {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()
            ->with(['client', 'currency', 'workspace.currency'])
            ->whereKey($invoice->id)
            ->firstOrFail();

        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID], true)) {
            return redirect()
                ->route('invoices.index', $workspace)
                ->with('error', 'Draft and void invoices cannot be sent to WhatsApp.');
        }

        try {
            $whatsappUrl = $whatsappMessageService->invoiceShareUrl($invoice);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('invoices.index', $workspace)
                ->withErrors(new MessageBag($exception->errors()))
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->away($whatsappUrl);
    }

    public function edit(Workspace $workspace, Invoice $invoice, CurrencyService $currencyService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->with(['currency', 'items'])->where('id', $invoice->id)->firstOrFail();
        app(InvoiceService::class)->assertCanEdit($invoice);
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

        $rules = [
            'notes' => ['nullable', 'string'],
        ];

        if ($invoice->status !== Invoice::STATUS_PAID) {
            $rules = array_merge($rules, [
                'invoice_number' => ['required', 'string', 'max:255', 'unique:invoices,invoice_number,'.$invoice->id.',id,workspace_id,'.$workspace->id],
                'client_id' => ['required', Rule::exists('clients', 'id')->where('workspace_id', $workspace->id)],
                'currency_id' => ['required', $currencyService->activeCurrencyRule()],
                'issue_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
                'status' => ['required', Rule::in([
                    Invoice::STATUS_DRAFT,
                    Invoice::STATUS_SENT,
                    Invoice::STATUS_PARTIAL,
                    Invoice::STATUS_OVERDUE,
                ])],
                'tax_amount' => ['nullable', 'numeric', 'min:0'],
                'discount_amount' => ['nullable', 'numeric', 'min:0'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.item_name' => ['required', 'string', 'max:255'],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            ]);
        } else {
            $rules = array_merge($rules, [
                'invoice_number' => ['sometimes', 'string', 'max:255'],
                'client_id' => ['sometimes', 'integer'],
                'currency_id' => ['sometimes', 'integer'],
                'issue_date' => ['sometimes', 'date'],
                'due_date' => ['sometimes', 'date'],
                'status' => ['sometimes', 'string'],
                'tax_amount' => ['sometimes', 'numeric', 'min:0'],
                'discount_amount' => ['sometimes', 'numeric', 'min:0'],
                'total_amount' => ['sometimes', 'numeric', 'min:0'],
                'items' => ['sometimes', 'array'],
                'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
                'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            ]);
        }

        $validated = $request->validate($rules);

        try {
            $invoice = $invoiceService->updateInvoice($workspace, $validated, $invoice, Auth::user());

            return redirect()->route('invoices.show', [$workspace, $invoice])->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            return back()->with(
                'error',
                'Failed to update invoice: '.$e->getMessage(),
            );
        }

    }

    public function destroy(Workspace $workspace, Invoice $invoice, InvoiceService $invoiceService)
    {
        $this->authorizeWorkspaceUser($workspace);

        $invoice = $workspace->invoices()->where('id', $invoice->id)->firstOrFail();

        try {
            $invoiceService->softDeleteDraft($workspace, $invoice, Auth::user());
        } catch (ValidationException $exception) {
            return redirect()->route('invoices.show', [$workspace, $invoice])
                ->withErrors(new MessageBag($exception->errors()))
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('invoices.index', $workspace)->with('success', 'Draft invoice moved to the recycle bin.');
    }

    public function deleted(Workspace $workspace)
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('invoice.deleted', [
            'workspace' => $workspace,
            'invoices' => $workspace->invoices()->onlyTrashed()->where('status', Invoice::STATUS_DRAFT)->latest('deleted_at')->paginate(15),
        ]);
    }

    public function restore(Workspace $workspace, int $invoice, InvoiceService $invoiceService)
    {
        $this->authorizeWorkspaceUser($workspace);
        $invoiceService->restoreDraft($workspace, $invoice, Auth::user());

        return redirect()->route('invoices.deleted', $workspace)->with('success', 'Draft invoice restored successfully.');
    }

    public function forceDelete(Workspace $workspace, int $invoice, InvoiceService $invoiceService)
    {
        $this->authorizeWorkspaceUser($workspace);

        try {
            $invoiceService->forceDeleteDraft($workspace, $invoice, Auth::user());
        } catch (ValidationException $exception) {
            return redirect()->route('invoices.deleted', $workspace)
                ->withErrors(new MessageBag($exception->errors()))
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('invoices.deleted', $workspace)->with('success', 'Draft invoice permanently deleted.');
    }

    public function void(Request $request, Workspace $workspace, Invoice $invoice, InvoiceService $invoiceService)
    {
        $this->authorizeWorkspaceUser($workspace);
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $invoiceService->voidInvoice($workspace, $invoice, $validated['void_reason'], Auth::user());
        } catch (ValidationException $exception) {
            return redirect()->route('invoices.show', [$workspace, $invoice])
                ->withErrors(new MessageBag($exception->errors()))
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('invoices.show', [$workspace, $invoice])->with('success', 'Invoice voided successfully.');
    }
}
