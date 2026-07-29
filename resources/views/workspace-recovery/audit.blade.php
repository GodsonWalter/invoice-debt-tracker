@extends('layouts.app')

@section('page_title', 'Workspace Recovery Audit')

@section('content')
    <div class="container-fluid py-2">
        <a href="{{ route('workspace.recovery.show', $workspace->id) }}" class="btn btn-outline-secondary btn-sm mb-3">Back to recovery details</a>
        <h2 class="fs-4">{{ $workspace->name }} audit history</h2>
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table mb-0">
                <thead><tr><th>Event</th><th>Actor</th><th>Reason</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($audits as $audit)
                        <tr><td>{{ $audit->event }}</td><td>{{ $audit->actor_type }}</td><td>{{ $audit->reason ?: '—' }}</td><td>{{ $audit->created_at->format('M j, Y H:i') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No lifecycle events recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $audits->links() }}</div>
    </div>
@endsection
