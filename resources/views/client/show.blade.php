@extends('layouts.app')

@section('page_title', 'Client Details')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Client Details</h2>
            <p class="text-muted small mb-0">Review the client record for <strong>{{ $client->name }}</strong>.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('clients.index', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Clients
            </a>
            <a href="{{ route('clients.edit', [$workspace, $client]) }}" class="btn btn-warning btn-sm">
                <i class="bi bi-pencil-square"></i> Edit Client
            </a>
        </div>
    </div>

    <div class="card border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Name</h6>
                    <p class="mb-0">{{ $client->name }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Email</h6>
                    <p class="mb-0">{{ $client->email ?: '—' }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Phone</h6>
                    <p class="mb-0">{{ $client->phone ?: '—' }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Address</h6>
                    <p class="mb-0">{{ $client->address ?: '—' }}</p>
                </div>

                <div class="col-12">
                    <h6 class="mb-2">Notes</h6>
                    <p class="mb-0">{!! nl2br(e($client->notes ?: '—')) !!}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

