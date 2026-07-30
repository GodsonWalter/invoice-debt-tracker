@extends('layouts.app')

@section('page_title', 'Deleted User Account')

@section('content')
    <div class="container-fluid py-2">
        <a href="{{ route('platform.user-recovery.index') }}" class="btn btn-outline-secondary btn-sm mb-3">Back to deleted accounts</a>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="fs-4">{{ $user->name ?: 'Unnamed account' }}</h2>
                <dl class="row mb-4">
                    <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $user->email }}</dd>
                    <dt class="col-sm-3">Deleted</dt><dd class="col-sm-9">{{ $user->deleted_at?->format('M j, Y H:i') }}</dd>
                </dl>

                <form method="POST" action="{{ route('platform.user-recovery.restore', $user->id) }}"
                    data-lifecycle-confirm
                    data-lifecycle-title="Restore user account?"
                    data-lifecycle-text="This account will become eligible to sign in again. Existing sessions, tokens, and workspace membership states will not be recreated."
                    data-lifecycle-confirm-text="Restore account"
                    data-lifecycle-reason="true">
                    @csrf
                    <input type="hidden" name="reason" data-lifecycle-reason-input>
                    <button class="btn btn-warning" type="submit">Restore account</button>
                </form>
            </div>
        </div>

        <h3 class="fs-5 mt-4">Account audit</h3>
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table mb-0">
                <thead><tr><th>Event</th><th>Actor</th><th>Reason</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($audits as $audit)
                        <tr>
                            <td>{{ $audit->event }}</td>
                            <td>{{ $audit->actor_type }} ({{ $audit->actor_global_role ?: 'unknown' }})</td>
                            <td>{{ $audit->reason ?: '—' }}</td>
                            <td>{{ $audit->created_at->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No account audit events found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
