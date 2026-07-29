@extends('layouts.app')

@section('page_title', 'Recovery Details')

@section('content')
    <div class="container-fluid py-2">
        <a href="{{ route('workspace.recovery.index') }}" class="btn btn-outline-secondary btn-sm mb-3">Back to Recovery Center</a>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="fs-4">{{ $workspace->name }}</h2>
                <p>Current status: <strong>{{ str($lifecycle['state'])->replace('_', ' ')->title() }}</strong></p>
                <dl class="row">
                    <dt class="col-sm-4">Deleted</dt><dd class="col-sm-8">{{ $lifecycle['deleted_at']->format('M j, Y H:i') }}</dd>
                    <dt class="col-sm-4">Owner restore deadline</dt><dd class="col-sm-8">{{ $lifecycle['restore_deadline']->format('M j, Y H:i') }}</dd>
                    <dt class="col-sm-4">Permanent deletion date</dt><dd class="col-sm-8">{{ $lifecycle['permanent_deletion_deadline']->format('M j, Y H:i') }}</dd>
                </dl>
                @if ($lifecycle['owner_can_restore'])
                    <form method="POST" action="{{ route('workspace.recovery.restore', $workspace->id) }}" data-lifecycle-confirm
                        data-lifecycle-title="Restore workspace?"
                        data-lifecycle-text="{{ $workspace->name }} and its data will become accessible again."
                        data-lifecycle-confirm-text="Restore workspace">
                        @csrf
                        <button class="btn btn-success" type="submit">Restore workspace</button>
                    </form>
                @else
                    <div class="alert alert-warning">Self-service recovery has expired. Contact platform support before permanent deletion.</div>
                @endif
                <a href="{{ route('workspace.recovery.audit', $workspace->id) }}" class="btn btn-link px-0">View lifecycle audit history</a>
            </div>
        </div>
    </div>
@endsection
