@extends('layouts.app')

@section('page_title', 'Workspace Lifecycle Audit')

@section('content')
    <div class="container-fluid py-2">
        <h2 class="fs-4 fw-bold mb-4">Workspace Lifecycle Audit</h2>
        <div class="table-responsive card border-0 shadow-sm"><table class="table mb-0"><thead><tr><th>Workspace</th><th>Event</th><th>Actor</th><th>Role</th><th>Reason</th><th>When</th></tr></thead><tbody>
            @forelse ($audits as $audit)
                <tr><td>{{ $audit->workspace_name }}</td><td>{{ $audit->event }}</td><td>{{ $audit->actor_user_id ?: 'System' }}</td><td>{{ $audit->actor_global_role ?: '—' }}</td><td>{{ $audit->reason ?: '—' }}</td><td>{{ $audit->created_at->format('M j, Y H:i') }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">No lifecycle audit events found.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-3">{{ $audits->links() }}</div>
    </div>
@endsection
