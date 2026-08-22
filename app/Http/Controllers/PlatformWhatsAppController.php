<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlatformWhatsAppConnectionRequest;
use App\Http\Requests\SaveWhatsAppTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConnectionAudit;
use App\Models\Workspace;
use App\Services\PlatformConfigurationService;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class PlatformWhatsAppController extends Controller
{
    public function __construct(
        private readonly WhatsAppConnectionService $connectionService,
        private readonly PlatformConfigurationService $platformConfigurationService,
    ) {}

    public function index(): View
    {
        $connection = $this->connectionService->shared();

        return view('platform.whatsapp.index', [
            'connection' => $connection,
            'templates' => $connection?->templates()->orderBy('type')->get() ?? collect(),
            'templateTypes' => EmailTemplate::TYPES,
            'audits' => WhatsAppConnectionAudit::query()
                ->whereNull('workspace_id')
                ->with('actor')
                ->latest()
                ->limit(15)
                ->get(),
            'tenantConnections' => WhatsAppConnection::query()
                ->whereNotNull('workspace_id')
                ->with('workspace')
                ->latest()
                ->paginate(15, ['*'], 'connections_page')
                ->withQueryString(),
        ]);
    }

    public function update(SavePlatformWhatsAppConnectionRequest $request, WhatsAppCloudApiService $cloudApi): RedirectResponse
    {
        try {
            $attributes = $request->validated();

            if (! filled($attributes['access_token'] ?? null)) {
                $attributes['access_token'] = config('services.whatsapp.shared_access_token');
            }

            $connection = $this->connectionService->save($request->user(), null, [
                ...$attributes,
                'is_enabled' => true,
                'status' => WhatsAppConnection::STATUS_PENDING,
            ]);
            $cloudApi->subscribe($connection);
            $details = $cloudApi->verify($connection);
            $connection->forceFill([
                'display_phone_number' => $details['display_phone_number'] ?? $connection->display_phone_number,
                'verified_name' => $details['verified_name'] ?? $connection->verified_name,
                'status' => WhatsAppConnection::STATUS_CONNECTED,
                'is_enabled' => true,
                'last_synced_at' => now(),
                'last_error' => null,
                'metadata' => $details,
            ])->save();
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', 'Shared WhatsApp connection could not be verified: '.$exception->getMessage());
        }

        return back()->with('success', 'Shared '.$this->platformConfigurationService->settings()->product_name.' WhatsApp Cloud API connection verified successfully.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->connectionService->disconnect(request()->user(), null);

        return back()->with('success', 'Shared '.$this->platformConfigurationService->settings()->product_name.' WhatsApp connection disconnected.');
    }

    public function disconnectTenant(Workspace $workspace): RedirectResponse
    {
        $this->connectionService->disconnect(request()->user(), $workspace);

        return back()->with('success', 'Tenant WhatsApp connection disconnected by platform administration.');
    }

    public function saveTemplate(SaveWhatsAppTemplateRequest $request): RedirectResponse
    {
        $connection = $this->connectionService->shared();

        if (! $connection?->exists) {
            return back()->with('error', 'Configure the shared WhatsApp connection before adding templates.');
        }

        $validated = $request->validated();
        $connection->templates()->updateOrCreate(
            ['type' => $validated['type']],
            [
                'workspace_id' => null,
                'template_name' => $validated['template_name'],
                'language_code' => $validated['language_code'],
                'body' => $validated['body'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
        );

        return back()->with('success', 'Shared WhatsApp reminder template saved successfully.');
    }
}
