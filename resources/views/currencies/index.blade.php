@extends('layouts.app')

@section('page_title', 'Currencies')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Currencies</h2>
                <p class="text-muted small mb-0">Manage system-level currencies available to users, workspaces, and invoices.</p>
            </div>

            <a href="{{ route('currencies.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Currency
            </a>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <form class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center" method="GET" action="{{ route('currencies.index') }}">
                    <h5 class="fw-bold text-dark mb-0 fs-6">Currency List</h5>
                    <div class="d-flex gap-2">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            class="form-control form-control-sm"
                            placeholder="Search currencies..."
                            style="max-width: 320px;"
                        >
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-body p-3 p-md-4">
                @if ($currencies->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <p class="mb-1">No currencies found.</p>
                        <a href="{{ route('currencies.create') }}" class="btn btn-sm btn-primary">Create your first currency</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Symbol</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($currencies as $currency)
                                    <tr>
                                        <td class="fw-semibold">{{ $currency->code }}</td>
                                        <td>{{ $currency->symbol }}</td>
                                        <td>{{ $currency->name }}</td>
                                        <td>
                                            <span class="badge bg-{{ $currency->is_active ? 'success' : 'secondary' }}">
                                                {{ $currency->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $currency->created_at?->format('Y-m-d') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('currencies.edit', $currency) }}" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <form action="{{ route('currencies.toggle', $currency) }}" method="POST" class="d-inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-{{ $currency->is_active ? 'warning' : 'success' }}" title="{{ $currency->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="bi bi-{{ $currency->is_active ? 'pause-circle' : 'check2-circle' }}"></i>
                                                </button>
                                            </form>

                                            @if ($currency->is_active)
                                                <form action="{{ route('currencies.destroy', $currency) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Deactivate this currency?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        {{ $currencies->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
