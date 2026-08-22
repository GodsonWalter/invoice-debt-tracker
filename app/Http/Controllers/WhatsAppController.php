<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWhatsAppConnectionRequest;
use App\Http\Requests\SaveWhatsAppTemplateRequest;
use App\Http\Requests\UpdateWhatsAppSettingsRequest;
use App\Models\EmailTemplate;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessageLog;
use App\Models\Workspace;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppConnectionService;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppConnectionService $connectionService) {}

    public function index(Workspace $workspace): View
    {
        $this->authorizeWorkspace($workspace);

        $connection = $this->connectionService->forWorkspace($workspace);

        return view('whatsapp.index', [
            'workspace' => $workspace,
            'connection' => $connection,
            'effectiveConnection' => $this->connectionService->effectiveFor($workspace),
            'templates' => $connection?->templates()->orderBy('type')->get() ?? collect(),
            'messageLogs' => $workspace->whatsappMessageLogs()
                ->with('invoice.client')
                ->latest()
                ->limit(10)
                ->get(),
            'templateTypes' => EmailTemplate::TYPES,
            'embeddedSignupReady' => filled(config('services.whatsapp.app_id'))
                && filled(config('services.whatsapp.embedded_signup_config_id')),
        ]);
    }

    public function updateSettings(UpdateWhatsAppSettingsRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->connectionService->updateWorkspaceSettings(
            workspace: $workspace,
            enabled: $request->boolean('whatsapp_auto_reminders_enabled'),
            architecture: $request->string('whatsapp_architecture')->toString(),
            actor: $request->user(),
        );

        return back()->with('success', 'WhatsApp reminder settings updated successfully.');
    }

    public function connect(SaveWhatsAppConnectionRequest $request, Workspace $workspace, WhatsAppCloudApiService $cloudApi): RedirectResponse
    {
        $attributes = $request->validated();

        try {
            if (filled($attributes['code'] ?? null)) {
                $attributes = [...$attributes, ...$cloudApi->completeEmbeddedSignup($attributes['code'])];
            }

            unset($attributes['code']);
            $attributes['status'] = WhatsAppConnection::STATUS_PENDING;
            $attributes['is_enabled'] = true;
            $connection = $this->connectionService->save($request->user(), $workspace, $attributes);
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
            if (isset($connection)) {
                $connection->forceFill([
                    'status' => WhatsAppConnection::STATUS_PENDING,
                    'is_enabled' => false,
                    'last_error' => (string) str($exception->getMessage())->limit(1000),
                ])->save();
            }

            return back()->withInput()->with('error', 'WhatsApp connection could not be verified: '.$exception->getMessage());
        }

        return back()->with('success', 'Tenant WhatsApp Cloud API connection verified successfully.');
    }

    public function disconnect(Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);
        $this->connectionService->disconnect(request()->user(), $workspace);

        return back()->with('success', 'Tenant WhatsApp connection disconnected.');
    }

    public function retry(Workspace $workspace, WhatsAppMessageLog $messageLog): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);
        $messageLog = $workspace->whatsappMessageLogs()->whereKey($messageLog->id)->firstOrFail();

        abort_if($messageLog->status !== WhatsAppMessageLog::STATUS_FAILED, 422, 'Only failed WhatsApp messages can be retried.');

        app(WhatsAppMessageService::class)->retry($messageLog);

        return back()->with('success', 'WhatsApp reminder queued for retry.');
    }

    public function saveTemplate(SaveWhatsAppTemplateRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);

        if ($workspace->whatsapp_architecture === WhatsAppConnection::TYPE_SHARED) {
            return back()->with('error', 'Shared WhatsApp templates are managed by the platform administrator.');
        }

        $connection = $this->connectionService->forWorkspace($workspace);

        if (! $connection) {
            return back()->with('error', 'Connect the tenant WhatsApp account before configuring templates.');
        }

        $validated = $request->validated();
        $connection->templates()->updateOrCreate(
            ['type' => $validated['type']],
            [
                'workspace_id' => $workspace->id,
                'template_name' => $validated['template_name'],
                'language_code' => $validated['language_code'],
                'body' => $validated['body'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
        );

        return back()->with('success', 'WhatsApp reminder template saved successfully.');
    }

    private function authorizeWorkspace(Workspace $workspace): void
    {
        abort_unless($workspace->canBeManagedBy(request()->user()), 403);
    }
}
