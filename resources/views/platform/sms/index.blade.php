@extends('layouts.app')

@section('title', 'SMS Configuration')

@section('content')
    <div class="container-fluid py-4">
        <div class="mb-4">
            <h1 class="h3 mb-1">SMS configuration</h1>
            <p class="text-muted mb-0">Configure the shared SMS connection and templates used by workspaces that select shared delivery.</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-transparent">
                <h2 class="h5 mb-1">Shared {{ $platformSettings['settings']->product_name }} SMS connection</h2>
                <p class="small text-muted mb-0">Credentials are encrypted at rest. Leave the auth token blank to keep the saved token.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('platform.sms.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="platform-provider">Provider</label>
                            <select class="form-select" id="platform-provider" name="provider"><option value="twilio">Twilio</option></select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="platform-account">Account SID</label>
                            <input class="form-control" id="platform-account" name="provider_account_id" value="{{ old('provider_account_id', $connection?->provider_account_id) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="platform-sender">Sender phone number</label>
                            <input class="form-control" id="platform-sender" name="sender" value="{{ old('sender', $connection?->sender) }}" placeholder="+15551234567">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="platform-service">Messaging Service SID</label>
                            <input class="form-control" id="platform-service" name="messaging_service_id" value="{{ old('messaging_service_id', $connection?->messaging_service_id) }}">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="platform-token">Auth token</label>
                            <input class="form-control" type="password" id="platform-token" name="auth_token" autocomplete="new-password">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-4" type="submit">Verify and save shared connection</button>
                </form>
                @if ($connection?->exists)
                    <form method="POST" action="{{ route('platform.sms.disconnect') }}" class="mt-3" data-lifecycle-confirm data-lifecycle-title="Disconnect shared SMS?" data-lifecycle-text="All workspaces using shared delivery will stop sending SMS reminders." data-lifecycle-confirm-text="Disconnect">
                        @csrf
                        <button class="btn btn-outline-danger" type="submit">Disconnect shared account</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-transparent">
                <h2 class="h5 mb-1">Shared reminder templates</h2>
                <p class="small text-muted mb-0">These templates apply to every workspace that selects the shared SMS architecture.</p>
            </div>
            <div class="card-body">
                @if (! $connection?->exists)
                    <div class="alert alert-warning mb-0">Verify the shared connection before configuring templates.</div>
                @else
                    <div class="row g-4">
                        @foreach ($templateTypes as $type)
                            @php($template = $templates->firstWhere('type', $type))
                            <div class="col-12 col-lg-4">
                                <form method="POST" action="{{ route('platform.sms.templates.update') }}" class="border rounded p-3 h-100">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="type" value="{{ $type }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">{{ str($type)->replace('_', ' ')->title() }}</h3>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="platform-sms-{{ $type }}" @checked($template?->is_active ?? true)><label class="form-check-label small" for="platform-sms-{{ $type }}">Active</label></div>
                                    </div>
                                    <textarea class="form-control" name="body" rows="8" maxlength="1600" required>{{ old('body', $template?->body ?? '') }}</textarea>
                                    <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Save template</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-transparent"><h2 class="h5 mb-0">Workspace-owned connections</h2></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Workspace</th><th>Provider</th><th>Account</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($workspaceConnections as $workspaceConnection)
                            <tr>
                                <td>{{ $workspaceConnection->workspace?->name ?? 'Workspace removed' }}</td>
                                <td>{{ strtoupper($workspaceConnection->provider) }}</td>
                                <td>{{ $workspaceConnection->provider_account_id }}</td>
                                <td><span class="badge bg-{{ $workspaceConnection->isReady() ? 'success' : 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $workspaceConnection->status)) }}</span></td>
                                <td>{{ $workspaceConnection->updated_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-end">
                                    @if ($workspaceConnection->workspace)
                                        <form method="POST" action="{{ route('platform.sms.workspace.disconnect', $workspaceConnection->workspace) }}" data-lifecycle-confirm data-lifecycle-title="Disconnect workspace SMS?" data-lifecycle-text="This workspace will stop sending through its connected SMS account." data-lifecycle-confirm-text="Disconnect">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Disconnect</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No workspace-owned SMS connections have been configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($workspaceConnections->hasPages())
                <div class="card-footer bg-transparent">{{ $workspaceConnections->links() }}</div>
            @endif
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-transparent"><h2 class="h5 mb-0">Shared connection audit</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Event</th><th>Actor</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr><td>{{ str($audit->event)->replace('_', ' ')->title() }}</td><td>{{ $audit->actor?->name ?? 'System' }}</td><td>{{ $audit->created_at?->format('M j, Y g:i A') }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No audit events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
