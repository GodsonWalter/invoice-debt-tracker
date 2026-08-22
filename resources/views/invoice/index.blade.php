@extends('layouts.app')

@section('page_title', 'Invoices')

@section('content')
    @php
        $statusOptions = collect($statuses)->mapWithKeys(fn (string $status): array => [$status => ucfirst($status)])->all();
        $sortOptions = [
            'created_at' => 'Created date',
            'invoice_number' => 'Invoice number',
            'issue_date' => 'Issue date',
            'due_date' => 'Due date',
            'total_amount' => 'Total amount',
            'status' => 'Status',
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Invoices</h1>
                <p class="text-muted mb-0">Manage invoices for <strong>{{ $workspace->name }}</strong>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('clients.index', $workspace) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Clients
                </a>
                <a href="{{ route('invoices.create', $workspace) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add invoice
                </a>
                <a href="{{ route('invoices.deleted', $workspace) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-archive me-1"></i> Deleted drafts
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('invoices.index', $workspace) }}" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="invoice-search" class="form-label">Search</label>
                        <input id="invoice-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" maxlength="100" placeholder="Invoice number or client">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="invoice-status" class="form-label">Status</label>
                        <select id="invoice-status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="invoice-sort" class="form-label">Sort by</label>
                        <select id="invoice-sort" name="sort" class="form-select">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="invoice-direction" class="form-label">Order</label>
                        <select id="invoice-direction" name="direction" class="form-select">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-grow-1" type="submit">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        @if (($filters['search'] ?? '') || ($filters['status'] ?? '') || ($filters['sort'] ?? 'created_at') !== 'created_at' || ($filters['direction'] ?? 'desc') !== 'desc')
                            <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-outline-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 fw-bold mb-1">Invoice list</h2>
                    <p class="text-muted small mb-0">Review invoice status, dates, totals, and available actions.</p>
                </div>
                @if ($invoices->total() > 0)
                    <span class="text-muted small">Showing {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} of {{ $invoices->total() }}</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0" id="invoice-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Invoice</th>
                            <th scope="col">Client</th>
                            <th scope="col">Issue date</th>
                            <th scope="col">Due date</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            @php
                                $statusTone = match ($invoice->status) {
                                    'draft' => 'secondary',
                                    'sent' => 'info',
                                    'partial' => 'warning',
                                    'paid' => 'success',
                                    'overdue' => 'danger',
                                    'void' => 'dark',
                                    default => 'secondary',
                                };
                            @endphp
                            <tr>
                                <th scope="row">{{ ($invoices->firstItem() ?? 1) + $loop->index }}</th>
                                <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->client?->name ?? '—' }}<br>
                                    {{ $invoice->client?->phone ?? '—' }}
                                </td>
                                <td>{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</td>
                                <td><span class="badge text-bg-{{ $statusTone }}">{{ ucfirst($invoice->status) }}</span></td>
                                <td class="text-end text-nowrap">{{ $invoice->formatMoney($invoice->total_amount) }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('invoices.show', [$workspace, $invoice]) }}"
                                        class="btn btn-sm btn-outline-primary" title="View invoice">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                    @if ($invoice->status !== \App\Models\Invoice::STATUS_VOID)
                                        <a href="{{ route('invoices.edit', [$workspace, $invoice]) }}"
                                            class="btn btn-sm btn-outline-secondary" title="Edit invoice">
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </a>
                                    @endif
                                    <a href="{{ route('invoices.pdf', [$workspace, $invoice]) }}"
                                        class="btn btn-sm btn-outline-primary" title="Download PDF">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                    </a><br>
                                    @if (! in_array($invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_VOID], true))
                                        <form action="{{ route('invoices.send', [$workspace, $invoice]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="my-1 btn btn-sm btn-primary" title="Send invoice by email">
                                                <i class="bi bi-envelope me-1"></i> Send to Mail
                                            </button>
                                        </form>
                                        @if (filled($invoice->client?->phone))
                                            <form action="{{ route('invoices.send-whatsapp', [$workspace, $invoice]) }}" method="POST" class="d-inline" target="_blank">
                                                @csrf
                                                <button type="submit" class="my-1 btn btn-sm btn-success" title="Send invoice to WhatsApp">
                                                    <i class="fa-brands fa-whatsapp me-1"></i> Send to WhatsApp
                                                </button>
                                            </form>
                                            <form action="{{ route('invoices.send-sms', [$workspace, $invoice]) }}" method="POST" class="d-inline"
                                                data-lifecycle-confirm data-lifecycle-title="Send invoice by SMS?" data-lifecycle-text="This invoice will be queued for SMS delivery to the client." data-lifecycle-confirm-text="Queue SMS">
                                                @csrf
                                                <button type="submit" class="my-1 btn btn-sm btn-info" title="Send invoice by SMS">
                                                    <i class="bi bi-chat-text me-1"></i> Send by SMS
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                                        <form action="{{ route('invoices.destroy', [$workspace, $invoice]) }}" method="POST" class="d-inline"
                                            data-delete-confirm>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete invoice">
                                                <i class="bi bi-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-receipt fs-3 d-block mb-2"></i>
                                    No invoices match these filters.
                                    <div class="mt-3">
                                        <a href="{{ route('invoices.create', $workspace) }}" class="btn btn-sm btn-primary">Create invoice</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="card-footer bg-white">{{ $invoices->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
