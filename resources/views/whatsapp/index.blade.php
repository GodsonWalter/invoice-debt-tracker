@extends('layouts.app')

@section('page_title', 'WhatsApp Reminders')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <p class="text-muted mb-1">{{ $workspace->name }} · Automation</p>
                <h1 class="h3 fw-bold mb-1">WhatsApp reminders</h1>
                <p class="text-muted mb-0">Send the same personalized invoice reminder content through WhatsApp Cloud API.</p>
            </div>
            <span class="badge {{ $workspace->whatsapp_auto_reminders_enabled ? 'text-bg-success' : 'text-bg-secondary' }} align-self-center">
                {{ $workspace->whatsapp_auto_reminders_enabled ? 'Automatic sending enabled' : 'Automatic sending disabled' }}
            </span>
        </div>

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $class)
            @if (session($key))<div class="alert alert-{{ $class }}" role="alert">{{ session($key) }}</div>@endif
        @endforeach
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-1">Reminder delivery settings</h2>
                        <p class="text-muted small mb-4">Only workspace owners and admins can change these settings.</p>
                        <form method="POST" action="{{ route('whatsapp.settings.update', $workspace, false) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="whatsapp_auto_reminders_enabled" value="0">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="whatsapp-enabled" name="whatsapp_auto_reminders_enabled" value="1" @checked(old('whatsapp_auto_reminders_enabled', $workspace->whatsapp_auto_reminders_enabled))>
                                <label class="form-check-label fw-semibold" for="whatsapp-enabled">Send automatic invoice reminders through WhatsApp</label>
                                <div class="form-text">Email reminders remain independent and continue using the existing schedules.</div>
                            </div>
                            <label for="whatsapp-architecture" class="form-label">WhatsApp architecture</label>
                            <select id="whatsapp-architecture" name="whatsapp_architecture" class="form-select" required>
                                <option value="tenant" @selected(old('whatsapp_architecture', $workspace->whatsapp_architecture) === 'tenant')>Workspace-owned WhatsApp Cloud API</option>
                                <option value="shared" @selected(old('whatsapp_architecture', $workspace->whatsapp_architecture) === 'shared')>{{ $platformSettings['settings']->product_name }}-owned shared WhatsApp account</option>
                            </select>
                            <div class="form-text mb-3">Workspace-owned connections send from this workspace’s number. Shared connections use the platform-managed {{ $platformSettings['settings']->product_name }} number.</div>
                            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> Save reminder settings</button>
                        </form>
                    </div>
                </div>

                @if ($workspace->whatsapp_architecture === 'tenant')
                    <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <div><h2 class="h5 fw-bold mb-1">Workspace-owned connection</h2><p class="text-muted small mb-0">Connect this workspace’s WABA and phone number.</p></div>
                            <span class="badge text-bg-{{ $connection?->status === 'connected' ? 'success' : 'secondary' }} align-self-start">{{ str($connection?->status ?? 'not_connected')->replace('_', ' ')->title() }}</span>
                        </div>

                        @if ($embeddedSignupReady)
                            <div class="alert alert-info small"><strong>Embedded Signup is configured.</strong> Use the Meta onboarding button after your Meta app has App Review and the required permissions.</div>
                            <form method="POST" action="{{ route('whatsapp.connect', $workspace, false) }}" id="embedded-signup-form" class="d-none">@csrf<input type="hidden" name="code" id="embedded-signup-code"></form>
                            <button type="button" class="btn btn-outline-primary mb-3" id="embedded-signup-button"><i class="fa-brands fa-meta me-1"></i> Connect with Meta Embedded Signup</button>
                        @endif

                        <form method="POST" action="{{ route('whatsapp.connect', $workspace, false) }}">
                            @csrf
                            <p class="small text-muted">For controlled deployments, the connection can also be entered server-side using the values from WhatsApp Manager. Existing access tokens are never displayed.</p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="business-portfolio-id">Business portfolio ID</label><input id="business-portfolio-id" name="business_portfolio_id" value="{{ old('business_portfolio_id', $connection?->business_portfolio_id) }}" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label" for="waba-id">WABA ID</label><input id="waba-id" name="waba_id" value="{{ old('waba_id', $connection?->waba_id) }}" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label" for="phone-number-id">Phone number ID</label><input id="phone-number-id" name="phone_number_id" value="{{ old('phone_number_id', $connection?->phone_number_id) }}" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label" for="display-phone-number">Display phone number</label><input id="display-phone-number" name="display_phone_number" value="{{ old('display_phone_number', $connection?->display_phone_number) }}" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label" for="verified-name">Verified business name</label><input id="verified-name" name="verified_name" value="{{ old('verified_name', $connection?->verified_name) }}" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label" for="access-token">Access token</label><input id="access-token" type="password" name="access_token" class="form-control" autocomplete="new-password" placeholder="{{ $connection?->exists ? 'Leave blank to keep current token' : 'Paste encrypted at rest after saving' }}"></div>
                            </div>
                            <button class="btn btn-primary mt-3" type="submit"><i class="fa-solid fa-link me-1"></i> Save and verify workspace connection</button>
                        </form>

                        @if ($connection?->exists)
                            <form method="POST" action="{{ route('whatsapp.disconnect', $workspace, false) }}" class="mt-3" data-lifecycle-confirm data-lifecycle-title="Disconnect WhatsApp?" data-lifecycle-text="Automatic WhatsApp reminders will stop until this connection is restored." data-lifecycle-confirm-text="Disconnect">@csrf<button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-unlink me-1"></i> Disconnect workspace connection</button></form>
                        @endif
                    </div>
                    </div>
                @endif
            </div>

            <div class="col-xl-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3">Effective connection</h2>
                        @if ($effectiveConnection?->isReady())
                            <div class="alert alert-success small mb-3"><i class="fa-solid fa-circle-check me-1"></i> Ready to send using the {{ $workspace->whatsapp_architecture === 'shared' ? $platformSettings['settings']->product_name.' shared' : 'workspace-owned' }} connection.</div>
                            <dl class="row small mb-0"><dt class="col-5 text-muted">Business name</dt><dd class="col-7">{{ $effectiveConnection->verified_name ?: 'Not supplied' }}</dd><dt class="col-5 text-muted">Phone</dt><dd class="col-7">{{ $effectiveConnection->display_phone_number ?: $effectiveConnection->phone_number_id }}</dd><dt class="col-5 text-muted">WABA</dt><dd class="col-7">{{ $effectiveConnection->waba_id ?: 'Not supplied' }}</dd></dl>
                        @else
                            <div class="alert alert-warning small mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> The selected connection is not ready. Configure and verify it before enabling automatic sending.</div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-1">Reminder templates</h2>
                        <p class="text-muted small mb-3">Map each reminder type to an approved Meta template. The body field documents the variable order and mirrors the email reminder content.</p>
                        @if ($workspace->whatsapp_architecture === 'shared')
                            <div class="alert alert-secondary small mb-0">Shared templates are managed by the platform administrator.</div>
                        @else
                            @foreach ($templateTypes as $type)
                                @php($template = $templates->firstWhere('type', $type))
                                <form method="POST" action="{{ route('whatsapp.templates.update', $workspace, false) }}" class="border rounded p-3 mb-3">
                                    @csrf @method('PUT')<input type="hidden" name="type" value="{{ $type }}">
                                    <div class="fw-semibold mb-2">{{ str($type)->replace('_', ' ')->title() }}</div>
                                    <input name="template_name" value="{{ old('template_name', $template?->template_name) }}" class="form-control form-control-sm mb-2" placeholder="approved_template_name" required>
                                    <div class="row g-2"><div class="col-5"><input name="language_code" value="{{ old('language_code', $template?->language_code ?? 'en_US') }}" class="form-control form-control-sm" required></div><div class="col-7"><input name="body" value="{{ old('body', $template?->body) }}" class="form-control form-control-sm" placeholder="Optional variable map"></div></div>
                                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="template-{{ $type }}" @checked($template?->is_active ?? true)><label class="form-check-label small" for="template-{{ $type }}">Use this approved template</label></div>
                                    <button class="btn btn-sm btn-outline-primary mt-2" type="submit">Save mapping</button>
                                </form>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3">Recent WhatsApp activity</h2>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Invoice</th><th>Recipient</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
                            @forelse ($messageLogs as $messageLog)
                                <tr><td>{{ $messageLog->invoice?->invoice_number ?? '—' }}</td><td>{{ $messageLog->recipient_phone }}</td><td><span class="badge text-bg-{{ in_array($messageLog->status, ['sent', 'delivered', 'read'], true) ? 'success' : ($messageLog->status === 'failed' ? 'danger' : 'secondary') }}">{{ str($messageLog->status)->title() }}</span></td><td class="small text-muted">{{ $messageLog->updated_at?->diffForHumans() }}</td><td>@if ($messageLog->status === 'failed')<form method="POST" action="{{ route('whatsapp.messages.retry', [$workspace, $messageLog], false) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Retry</button></form>@endif</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-muted small">No WhatsApp reminders have been queued yet.</td></tr>
                            @endforelse
                        </tbody></table></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@if ($embeddedSignupReady)
    @push('scripts')
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
        <script>
            window.fbAsyncInit = function () {
                FB.init({ appId: @json(config('services.whatsapp.app_id')), cookie: true, xfbml: true, version: @json(config('services.whatsapp.api_version')) });
            };
            document.getElementById('embedded-signup-button')?.addEventListener('click', function () {
                if (!window.FB) { alert('Meta onboarding is not available yet.'); return; }
                FB.login(function (response) {
                    if (response.authResponse?.code) {
                        document.getElementById('embedded-signup-code').value = response.authResponse.code;
                        document.getElementById('embedded-signup-form').submit();
                    }
                }, { config_id: @json(config('services.whatsapp.embedded_signup_config_id')), response_type: 'code', override_default_response_type: true, extras: { setup: {} } });
            });
        </script>
    @endpush
@endif
