@extends('layouts.app')

@section('page_title', 'Recovery Center')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h2 class="fs-4 fw-bold text-dark">Recovery Center</h2>
            <p class="text-muted">Restore deleted workspaces during the self-service recovery period.</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @forelse ($workspaces as $item)
                    @php($workspace = $item['workspace'])
                    @php($lifecycle = $item['lifecycle'])
                    <div class="p-4 border-bottom d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">{{ $workspace->name }}</h5>
                            <p class="text-muted small mb-1">Deleted {{ $lifecycle['deleted_at']->format('M j, Y H:i') }}</p>
                            <p class="small mb-1">Owner restore deadline: {{ $lifecycle['restore_deadline']->format('M j, Y') }}</p>
                            <p class="small mb-2">Permanent deletion date: {{ $lifecycle['permanent_deletion_deadline']->format('M j, Y') }}</p>
                            <span class="badge {{ $lifecycle['state'] === 'recoverable' ? 'bg-success' : ($lifecycle['state'] === 'pending_permanent_deletion' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                {{ str($lifecycle['state'])->replace('_', ' ')->title() }}
                            </span>
                            @if ($lifecycle['owner_can_restore'])
                                <span class="text-muted small ms-2">{{ $lifecycle['restore_days_remaining'] }} day(s) remaining</span>
                            @else
                                <span class="text-muted small ms-2">Contact platform support for recovery.</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <a href="{{ route('workspace.recovery.show', $workspace->id) }}" class="btn btn-outline-secondary btn-sm">Details</a>
                            @if ($lifecycle['owner_can_restore'])
                                <form method="POST" action="{{ route('workspace.recovery.restore', $workspace->id) }}" data-lifecycle-confirm
                                    data-lifecycle-title="Restore workspace?"
                                    data-lifecycle-text="{{ $workspace->name }} and its data will become accessible again."
                                    data-lifecycle-confirm-text="Restore workspace">
                                    @csrf
                                    <button class="btn btn-success btn-sm" type="submit">Restore</button>
                                </form>
                            @else
                                <button class="btn btn-secondary btn-sm" type="button" disabled>Restore expired</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted">No deleted workspaces are available for recovery.</div>
                @endforelse
            </div>
            <div class="card-footer">{{ $workspaces->links() }}</div>
        </div>
    </div>
@endsection
