@extends('layouts.app')

@section('page_title', 'Invoices')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Invoices</h2>
                <p class="text-muted small mb-0">Manage invoices for <strong>{{ $workspace->name }}</strong>.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('clients.index', $workspace) }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Clients
                </a>
                <a href="{{ route('invoices.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Invoice
                </a>
            </div>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0 fs-6">Invoice List</h5>
                    <input type="text" id="search-box" class="form-control form-control-sm w-auto"
                        placeholder="Search invoices..." style="max-width: 320px;">
                </div>
            </div>

            <div class="card-body p-3 p-md-4">
                @if ($invoices->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <p class="mb-1">No invoices found.</p>
                        <a href="{{ route('invoices.create', $workspace) }}" class="btn btn-sm btn-primary">Create your first
                            invoice</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="invoice-table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">SN</th>
                                    <th class="px-4 py-3">Invoice #</th>
                                    <th class="px-4 py-3">Client</th>
                                    <th class="px-4 py-3">Issue Date</th>
                                    <th class="px-4 py-3">Due Date</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="text-end px-4 py-3">Total</th>
                                    <th class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $key => $invoice)
                                    <tr>
                                        <td class="px-4 py-3">{{ $key + 1 }}</td>
                                        <td class="px-4 py-3">{{ $invoice->invoice_number }}</td>
                                        <td class="px-4 py-3">{{ $invoice->client?->name ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                                        <td class="px-4 py-3">{{ $invoice->due_date->format('Y-m-d') }}</td>
                                        <td class="px-4 py-3">
                                            @php
                                                $badge = match ($invoice->status) {
                                                    'draft' => 'secondary',
                                                    'sent' => 'info',
                                                    'partial' => 'warning',
                                                    'paid' => 'success',
                                                    'overdue' => 'danger',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $badge }}">{{ ucfirst($invoice->status) }}</span>
                                        </td>
                                        <td class="text-end px-4 py-3">{{ $invoice->formatMoney($invoice->total_amount) }}</td>
                                        <td class="text-end px-4 py-3">
                                            <a href="{{ route('invoices.show', [$workspace, $invoice]) }}"
                                                class="btn btn-sm btn-outline-primary me-1" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('invoices.edit', [$workspace, $invoice]) }}"
                                                class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="{{ route('invoices.pdf', [$workspace, $invoice]) }}"
                                                class="btn btn-sm btn-outline-primary me-1" title="Download PDF">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                            <form action="{{ route('invoices.send', [$workspace, $invoice]) }}" method="POST"
                                                class="d-inline-block me-1">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary" title="Send Invoice">
                                                    <i class="bi bi-send"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('invoices.destroy', [$workspace, $invoice]) }}" method="POST"
                                                class="d-inline-block" onsubmit="return confirm('Delete this invoice?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i
                                                        class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        {{ $invoices->links() ?? '' }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('search-box');
                const table = document.getElementById('invoice-table');
                if (!searchInput || !table) return;

                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                searchInput.addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    rows.forEach(row => {
                        const text = row.innerText.toLowerCase();
                        row.style.display = term === '' || text.includes(term) ? '' : 'none';
                    });
                });
            });
        </script>
    @endpush
@endsection
