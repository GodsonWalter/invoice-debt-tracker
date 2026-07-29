@extends('layouts.app')

@section('page_title', 'Platform Workspace Recovery')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h2 class="fs-4 fw-bold">Platform Workspace Recovery</h2><p class="text-muted mb-0">All deleted workspaces and retention deadlines.</p></div>
            <a href="{{ route('platform.recovery.audits') }}" class="btn btn-outline-secondary btn-sm">Lifecycle Audit</a>
        </div>
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-6"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search workspace name"></div>
            <div class="col-md-3"><select class="form-select" name="state"><option value="">All states</option>@foreach (['recoverable', 'restore_expired', 'pending_permanent_deletion', 'permanent_deletion_due'] as $state)<option value="{{ $state }}" @selected(request('state') === $state)>{{ str($state)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-primary">Search</button></div>
        </form>
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table align-middle mb-0">
                <thead><tr><th>Workspace</th><th>Owner</th><th>Deleted</th><th>Restore deadline</th><th>Permanent deletion</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($workspaces as $item)
                        @php($workspace = $item['workspace']) @php($lifecycle = $item['lifecycle'])
                        <tr>
                            <td>{{ $workspace->name }}</td><td>{{ $workspace->owner?->email ?? 'Unknown' }}</td>
                            <td>{{ $lifecycle['deleted_at']->format('M j, Y') }}</td><td>{{ $lifecycle['restore_deadline']->format('M j, Y') }}</td>
                            <td>{{ $lifecycle['permanent_deletion_deadline']->format('M j, Y') }}</td>
                            <td><span class="badge bg-{{ $lifecycle['state'] === 'permanent_deletion_due' ? 'danger' : ($lifecycle['state'] === 'recoverable' ? 'success' : 'warning') }} text-dark">{{ str($lifecycle['state'])->replace('_', ' ')->title() }}</span></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('platform.recovery.show', $workspace->id) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No deleted workspaces found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $workspaces->links() }}</div>
    </div>
@endsection
