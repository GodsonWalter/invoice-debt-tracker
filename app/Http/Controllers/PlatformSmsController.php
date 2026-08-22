<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlatformSmsConnectionRequest;
use App\Http\Requests\SaveSmsTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\SmsConnection;
use App\Models\SmsConnectionAudit;
use App\Models\Workspace;
use App\Services\PlatformConfigurationService;
use App\Services\SmsConnectionService;
use App\Services\SmsProviderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class PlatformSmsController extends Controller
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
        private readonly PlatformConfigurationService $platformConfigurationService,
    ) {}

    public function index(): View
    {
        $connection = $this->connectionService->shared();

        return view('platform.sms.index', [
            'connection' => $connection,
            'templates' => $connection?->templates()->orderBy('type')->get() ?? collect(),
            'templateTypes' => EmailTemplate::TYPES,
            'audits' => SmsConnectionAudit::query()
                ->whereNull('workspace_id')
                ->with('actor')
                ->latest()
                ->limit(15)
                ->get(),
            'workspaceConnections' => SmsConnection::query()
                ->whereNotNull('workspace_id')
                ->with('workspace')
                ->latest()
                ->paginate(15, ['*'], 'connections_page')
                ->withQueryString(),
        ]);
    }

    public function update(
        SavePlatformSmsConnectionRequest $request,
        SmsProviderService $provider,
    ): RedirectResponse {
        $connection = null;

        try {
            $attributes = $request->validated();
            $attributes['auth_token'] = $attributes['auth_token'] ?? config('services.sms.shared_auth_token');
            $connection = $this->connectionService->save($request->user(), null, [
                ...$attributes,
                'status' => SmsConnection::STATUS_PENDING,
                'is_enabled' => true,
            ]);
            $details = $provider->verify($connection);
            $connection->forceFill([
                'status' => SmsConnection::STATUS_CONNECTED,
                'is_enabled' => true,
                'last_synced_at' => now(),
                'last_error' => null,
                'metadata' => $details,
            ])->save();
        } catch (Throwable $exception) {
            if ($connection?->exists) {
                $connection->forceFill([
                    'status' => SmsConnection::STATUS_PENDING,
                    'is_enabled' => false,
                    'last_error' => (string) str($exception->getMessage())->limit(1000),
                ])->save();
            }

            return back()->withInput()->with('error', 'Shared SMS connection could not be verified: '.$exception->getMessage());
        }

        return back()->with('success', 'Shared '.$this->platformConfigurationService->settings()->product_name.' SMS connection verified successfully.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->connectionService->disconnect(request()->user(), null);

        return back()->with('success', 'Shared SMS connection disconnected.');
    }

    public function disconnectWorkspace(Workspace $workspace): RedirectResponse
    {
        $this->connectionService->disconnect(request()->user(), $workspace);

        return back()->with('success', 'Workspace SMS connection disconnected by platform administration.');
    }

    public function saveTemplate(SaveSmsTemplateRequest $request): RedirectResponse
    {
        $connection = $this->connectionService->shared();

        if (! $connection?->exists) {
            return back()->with('error', 'Configure the shared SMS connection before adding templates.');
        }

        $validated = $request->validated();
        $connection->templates()->updateOrCreate(
            ['type' => $validated['type']],
            [
                'workspace_id' => null,
                'body' => $validated['body'],
                'is_active' => $request->boolean('is_active'),
            ],
        );

        return back()->with('success', 'Shared SMS reminder template saved successfully.');
    }
}
