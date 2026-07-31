@extends('layouts.guest')

@section('content')
    @php
        $isDeleted = $status === 'deleted';
    @endphp

    <div class="text-center">
        <div class="mb-3 text-warning">
            <i class="bi bi-exclamation-triangle-fill fs-1" aria-hidden="true"></i>
        </div>

        <h1 class="h4 mb-3">
            {{ $isDeleted ? 'Your account has been deleted' : 'Your account has been deactivated' }}
        </h1>

        <p class="mb-3">
            {{ $isDeleted
                ? 'This account has been soft deleted and is no longer available for login.'
                : 'This account has been deactivated and is no longer available for login.' }}
        </p>

        <p class="mb-3">
            The workspaces associated with this account have also been deactivated.
        </p>

        <p class="text-muted mb-4">
            Please contact the support team if you believe this was done in error or need assistance recovering access.
        </p>

        <a href="{{ route('login') }}" class="btn btn-primary">Return to login</a>
    </div>
@endsection
