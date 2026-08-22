@extends('layouts.app')

@section('title', 'SMS Reminders')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">SMS reminders</h1>
                <p class="text-muted mb-0">Send invoice reminders using the shared platform connection or your workspace connection.</p>
            </div>
            <span class="badge {{ $effectiveConnection?->isReady() ? 'bg-success' : 'bg-secondary' }}">
                {{ $effectiveConnection?->isReady() ? 'Ready to send' : 'Connection required' }}
            </span>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <h2 class="h5 mb-1">Reminder delivery settings</h2>
                        <p class="small text-muted mb-0">These settings control automatic SMS reminders for this workspace.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('sms.settings.update', $workspace, false) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="sms_auto_reminders_enabled" value="0">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="sms_auto_reminders_enabled"
                                    name="sms_auto_reminders_enabled" value="1" @checked(old('sms_auto_reminders_enabled', $workspace->sms_auto_reminders_enabled))>
                                <label class="form-check-label" for="sms_auto_reminders_enabled">Enable automatic SMS reminders</label>
                            </div>

                            <label for="sms_architecture" class="form-label">SMS connection architecture</label>
                            <select class="form-select" id="sms_architecture" name="sms_architecture" required>
                                <option value="shared" @selected(old('sms_architecture', $workspace->sms_architecture) === 'shared')>
                                    {{ $platformSettings['settings']->product_name }} shared SMS account
                                </option>
                                <option value="workspace" @selected(old('sms_architecture', $workspace->sms_architecture) === 'workspace')>
                                    Workspace-owned SMS account
                                </option>
                            </select>
                            <div class="form-text">Changing this selection changes which verified connection handles automatic reminders.</div>

                            <button class="btn btn-primary mt-4" type="submit">Save reminder settings</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-1">Effective SMS connection</h2>
                            <p class="small text-muted mb-0">The connection currently selected for this workspace.</p>
                        </div>
                        <span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $effectiveConnection?->status ?? 'not connected')) }}</span>
                    </div>
                    <div class="card-body">
                        @if ($effectiveConnection?->isReady())
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Provider</dt><dd class="col-sm-8">{{ strtoupper($effectiveConnection->provider) }}</dd>
                                <dt class="col-sm-4">Account</dt><dd class="col-sm-8">{{ $effectiveConnection->provider_account_id }}</dd>
                                <dt class="col-sm-4">Sender</dt><dd class="col-sm-8">{{ $effectiveConnection->messaging_service_id ?: $effectiveConnection->sender }}</dd>
                            </dl>
                        @else
                            <div class="alert alert-warning mb-0">The selected architecture does not have a verified SMS connection yet.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($workspace->sms_architecture === 'workspace')
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-1">Workspace-owned SMS connection</h2>
                    <p class="small text-muted mb-0">Credentials are encrypted at rest. Leave the auth token blank when updating an existing connection.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('sms.connect', $workspace, false) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="provider">Provider</label>
                                <select class="form-select" id="provider" name="provider">
                                    <option value="twilio">Twilio</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="provider_account_id">Account SID</label>
                                <input class="form-control" id="provider_account_id" name="provider_account_id" value="{{ old('provider_account_id', $connection?->provider_account_id) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sender">Sender phone number</label>
                                <input class="form-control" id="sender" name="sender" value="{{ old('sender', $connection?->sender) }}" placeholder="+15551234567">
                                <div class="form-text">Use this or a messaging service SID.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="messaging_service_id">Messaging Service SID</label>
                                <input class="form-control" id="messaging_service_id" name="messaging_service_id" value="{{ old('messaging_service_id', $connection?->messaging_service_id) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="auth_token">Auth token</label>
                                <input class="form-control" type="password" id="auth_token" name="auth_token" autocomplete="new-password">
                            </div>
                        </div>
                        <button class="btn btn-primary mt-4" type="submit">Verify and save connection</button>
                    </form>

                    @if ($connection?->exists)
                        <form method="POST" action="{{ route('sms.disconnect', $workspace, false) }}" class="mt-3" data-lifecycle-confirm data-lifecycle-title="Disconnect workspace SMS?" data-lifecycle-text="Automatic SMS delivery will stop until this connection is restored." data-lifecycle-confirm-text="Disconnect">
                            @csrf
                            <button class="btn btn-outline-danger" type="submit">Disconnect workspace account</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-transparent">
                <h2 class="h5 mb-1">Workspace SMS reminder templates</h2>
                <p class="small text-muted mb-0">Templates use the same placeholders and reminder content as email. SMS is limited to 1,600 characters.</p>
            </div>
            <div class="card-body">
                @if ($workspace->sms_architecture === 'shared')
                    <div class="alert alert-info mb-0">Shared SMS templates are managed by the platform administrator.</div>
                @elseif (! $connection)
                    <div class="alert alert-warning mb-0">Verify the workspace connection before configuring SMS templates.</div>
                @else
                    <div class="row g-4">
                        @foreach ($templateTypes as $type)
                            @php($template = $templates->firstWhere('type', $type))
                            <div class="col-12 col-lg-4">
                                <form method="POST" action="{{ route('sms.templates.update', $workspace, false) }}" class="border rounded p-3 h-100">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="type" value="{{ $type }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">{{ str($type)->replace('_', ' ')->title() }}</h3>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="sms-template-{{ $type }}" @checked($template?->is_active ?? true)>
                                            <label class="form-check-label small" for="sms-template-{{ $type }}">Active</label>
                                        </div>
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
            <div class="card-header bg-transparent"><h2 class="h5 mb-0">Recent SMS activity</h2></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Invoice</th><th>Recipient</th><th>Status</th><th>Sent</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($messageLogs as $log)
                            <tr>
                                <td>{{ $log->invoice?->invoice_number ?? 'Invoice removed' }}</td>
                                <td>{{ $log->recipient_phone }}</td>
                                <td><span class="badge bg-{{ $log->status === 'failed' ? 'danger' : ($log->status === 'delivered' ? 'success' : 'secondary') }}">{{ ucfirst($log->status) }}</span></td>
                                <td>{{ $log->sent_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($log->status === 'failed')
                                        <form method="POST" action="{{ route('sms.messages.retry', [$workspace, $log], false) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Retry</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No SMS activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
