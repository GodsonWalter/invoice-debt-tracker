@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-lg-5 text-center">
                <i class="bi bi-building display-5 text-primary"></i>
                <h1 class="h3 fw-bold mt-3">Select a workspace</h1>
                <p class="text-muted mb-4">
                    Choose an active workspace from the workspace switcher to view its dashboard and business data.
                </p>
                <a href="{{ route('workspace.index', [], false) }}" class="btn btn-primary">
                    <i class="bi bi-building me-1"></i> View workspaces
                </a>
            </div>
        </div>
    </div>
@endsection
