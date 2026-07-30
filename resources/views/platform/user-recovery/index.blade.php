@extends('layouts.app')

@section('page_title', 'Deleted User Accounts')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fs-4 fw-bold">Deleted User Accounts</h2>
                <p class="text-muted mb-0">Platform-only recovery for soft-deleted accounts.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-6">
                <label class="visually-hidden" for="deleted-user-search">Search deleted accounts</label>
                <input id="deleted-user-search" class="form-control" name="search" value="{{ request('search') }}"
                    maxlength="100" placeholder="Search by name or email">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-responsive card border-0 shadow-sm">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Deleted</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name ?: 'Unnamed account' }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->deleted_at?->format('M j, Y H:i') }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('platform.user-recovery.show', $user->id) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No deleted accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $users->links() }}</div>
    </div>
@endsection
