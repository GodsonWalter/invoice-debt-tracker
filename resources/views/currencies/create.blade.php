@extends('layouts.app')

@section('page_title', 'Create Currency')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Create Currency</h2>
                <p class="text-muted small mb-0">Add a database-backed currency for invoices and workspaces.</p>
            </div>

            <a href="{{ route('currencies.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Currencies
            </a>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-3 p-md-4">
                <form action="{{ route('currencies.store') }}" method="POST">
                    @include('currencies._form')
                </form>
            </div>
        </div>
    </div>
@endsection
