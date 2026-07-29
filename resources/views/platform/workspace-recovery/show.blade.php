@extends('layouts.app')

@section('page_title', 'Platform Workspace Review')

@section('content')
    <div class="container-fluid py-2">
        <a href="{{ route('platform.recovery.index') }}" class="btn btn-outline-secondary btn-sm mb-3">Back to platform recovery</a>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="fs-4">{{ $workspace->name }}</h2>
                <p>Owner: {{ $workspace->owner?->email ?? 'Unknown' }}</p>
                <p>Status: <strong>{{ str($lifecycle['state'])->replace('_', ' ')->title() }}</strong></p>
                <p>Deleted: {{ $lifecycle['deleted_at']->format('M j, Y H:i') }}</p>
                <p>Owner restore deadline: {{ $lifecycle['restore_deadline']->format('M j, Y H:i') }}</p>
                <p>Permanent deletion date: {{ $lifecycle['permanent_deletion_deadline']->format('M j, Y H:i') }}</p>
                @if ($lifecycle['state'] !== 'permanent_deletion_due')
                    <form method="POST" action="{{ route('platform.recovery.restore', $workspace->id) }}" data-lifecycle-confirm
                        data-lifecycle-title="Exceptional workspace recovery"
                        data-lifecycle-text="The owner's normal recovery period may have expired. Provide a reason for restoring {{ $workspace->name }}."
                        data-lifecycle-confirm-text="Restore workspace" data-lifecycle-reason="true">
                        @csrf
                        <input type="hidden" name="reason" data-lifecycle-reason-input>
                        <button class="btn btn-warning" type="submit">Restore through platform support</button>
                    </form>
                @else
                    <div class="alert alert-danger">Permanent deletion is due. This workspace cannot be restored.</div>
                @endif
            </div>
        </div>
        <h3 class="fs-5 mt-4">Lifecycle audit</h3>
        <div class="table-responsive card border-0 shadow-sm"><table class="table mb-0"><thead><tr><th>Event</th><th>Actor</th><th>Reason</th><th>When</th></tr></thead><tbody>
            @foreach ($audits as $audit)<tr><td>{{ $audit->event }}</td><td>{{ $audit->actor_type }} ({{ $audit->actor_global_role ?: 'system' }})</td><td>{{ $audit->reason ?: '—' }}</td><td>{{ $audit->created_at->format('M j, Y H:i') }}</td></tr>@endforeach
        </tbody></table></div>
    </div>
@endsection
