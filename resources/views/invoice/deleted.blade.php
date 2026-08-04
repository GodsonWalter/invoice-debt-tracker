@extends('layouts.app')

@section('page_title', 'Deleted Draft Invoices')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Deleted draft invoices</h1>
                <p class="text-muted mb-0">Restore or permanently remove draft invoices for {{ $workspace->name }}.</p>
            </div>
            <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Active invoices
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice</th>
                            <th>Deleted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->deleted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <form action="{{ route('invoices.restore', [$workspace, $invoice->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                        </button>
                                    </form>
                                    <form action="{{ route('invoices.force-delete', [$workspace, $invoice->id]) }}" method="POST" class="d-inline" data-delete-confirm>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash3 me-1"></i> Permanently delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">There are no deleted draft invoices.</td>
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
