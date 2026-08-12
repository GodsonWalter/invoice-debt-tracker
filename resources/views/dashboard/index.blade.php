@extends('layouts.app')

@section('page_title', 'Your Workspaces')

@push('styles')
    <style>
        .workspace-selector-card {
            border: 1px solid #e7edf4;
            min-height: 12rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .workspace-selector-card:hover {
            border-color: #b8c9e6;
            box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.1) !important;
            transform: translateY(-2px);
        }

        .workspace-selector-card:focus-visible {
            border-color: #0d6efd;
            outline: 3px solid rgba(13, 110, 253, 0.25);
            outline-offset: 3px;
        }

        .workspace-selector-logo {
            align-items: center;
            background: #eef4ff;
            border-radius: 0.85rem;
            color: #0d6efd;
            display: flex;
            flex: 0 0 auto;
            font-size: 1.1rem;
            font-weight: 700;
            height: 3rem;
            justify-content: center;
            overflow: hidden;
            width: 3rem;
        }

        .workspace-selector-logo img {
            height: 100%;
            object-fit: contain;
            padding: 0.25rem;
            width: 100%;
        }

        .workspace-selector-create {
            background: #fbfdff;
            border-style: dashed;
        }

        @media (prefers-reduced-motion: reduce) {
            .workspace-selector-card {
                transition: none;
            }

            .workspace-selector-card:hover {
                transform: none;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="text-primary text-uppercase small fw-semibold mb-1">Workspace home</p>
                <h1 class="fs-3 fw-bold mb-1">Your workspaces</h1>
                <p class="text-muted mb-0">Select a workspace to continue.</p>
            </div>
            <a href="{{ route('workspace.index', [], false) }}" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-1" aria-hidden="true"></i> Manage workspaces
            </a>
        </div>

        @if ($workspaces->isEmpty())
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="workspace-selector-logo mx-auto mb-3" aria-hidden="true">
                        <i class="bi bi-building"></i>
                    </div>
                    <h2 class="h5 fw-bold mb-2">No workspaces yet</h2>
                    <p class="text-muted mb-3">
                        Create your first business workspace to start managing invoices and collections.
                    </p>
                    <a href="{{ route('workspace.create', [], false) }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Create Workspace
                    </a>
                </div>
            </div>
        @endif

        <div class="row g-3" aria-label="Available workspaces">
            @foreach ($workspaces as $workspace)
                @php
                    $businessProfile = $workspace->businessProfile;
                    $logoPath = $businessProfile?->logo;
                    $logoUrl = filled($logoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath)
                        : null;
                    $nameParts = preg_split('/\s+/', trim($workspace->name), -1, PREG_SPLIT_NO_EMPTY);
                    $initials = collect($nameParts ?: [])
                        ->take(2)
                        ->map(fn (string $part): string => (string) str($part)->substr(0, 1)->upper())
                        ->implode('');
                    $switchUrl = route('workspace.switch', ['workspace' => $workspace->subdomain]);
                @endphp

                <div class="col-12 col-md-6 col-lg-4">
                    <a href="{{ $switchUrl }}"
                        class="workspace-selector-card card border-0 shadow-sm rounded-4 h-100 text-decoration-none text-reset"
                        aria-label="Switch to {{ $workspace->name }} workspace">
                        <div class="card-body p-3 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                @if ($logoUrl)
                                    <div class="workspace-selector-logo">
                                        <img src="{{ $logoUrl }}" alt="{{ $businessProfile?->business_name ?: $workspace->name }} logo">
                                    </div>
                                @else
                                    <div class="workspace-selector-logo" role="img"
                                        aria-label="{{ $workspace->name }} logo placeholder">
                                        @if ($initials)
                                            <span aria-hidden="true">{{ $initials }}</span>
                                        @else
                                            <i class="bi bi-building" aria-hidden="true"></i>
                                        @endif
                                    </div>
                                @endif
                                <i class="bi bi-arrow-up-right text-primary" aria-hidden="true"></i>
                            </div>

                            <div class="flex-grow-1">
                                <h2 class="h6 fw-bold mb-2">{{ $workspace->name }}</h2>
                                <span class="badge text-bg-light border text-capitalize">
                                    {{ $workspace->pivot->role ?? 'member' }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 text-primary fw-semibold small mt-3">
                                <span>Switch Workspace</span>
                                <span aria-hidden="true">→</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach

            <div class="col-12 col-md-6 col-lg-4">
                <a href="{{ route('workspace.create', [], false) }}"
                    class="workspace-selector-card workspace-selector-create card border-0 shadow-sm rounded-4 h-100 text-decoration-none text-reset"
                    aria-label="Create Workspace">
                    <div class="card-body p-3 d-flex flex-column justify-content-center">
                        <div class="workspace-selector-logo mb-3" aria-hidden="true">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <h2 class="h6 fw-bold mb-2">Create Workspace</h2>
                        <p class="text-muted small mb-3">Create another business workspace.</p>
                        <span class="text-primary fw-semibold small">
                            Get started <span aria-hidden="true">→</span>
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </div>
@endsection
