@extends('layouts.app')

@section('page_title', 'Email Templates')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Reminder Email Templates</h2>
                <p class="text-muted small mb-0">Manage workspace-specific reminder email copy and placeholders.</p>
            </div>

            <a href="{{ route('email-templates.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Template
            </a>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <h5 class="fw-bold text-dark mb-0 fs-6">Templates</h5>
            </div>

            <div class="card-body p-3 p-md-4">
                @if ($emailTemplates->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <p class="mb-1">No reminder email templates found.</p>
                        <a href="{{ route('email-templates.create', $workspace) }}" class="btn btn-sm btn-primary">Create template</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Default</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($emailTemplates as $emailTemplate)
                                    <tr>
                                        <td class="fw-semibold">{{ $emailTemplate->name }}</td>
                                        <td>{{ $emailTemplate->typeLabel() }}</td>
                                        <td class="text-muted">{{ $emailTemplate->subject }}</td>
                                        <td>
                                            <span class="badge bg-{{ $emailTemplate->is_active ? 'success' : 'secondary' }}">
                                                {{ $emailTemplate->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $emailTemplate->is_default ? 'primary' : 'light text-dark' }}">
                                                {{ $emailTemplate->is_default ? 'Default' : 'Custom' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('email-templates.edit', [$workspace, $emailTemplate]) }}" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <form action="{{ route('email-templates.destroy', [$workspace, $emailTemplate]) }}" method="POST" class="d-inline-block" data-delete-confirm>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        {{ $emailTemplates->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
