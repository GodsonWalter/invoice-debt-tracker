<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSmsConnectionRequest;
use App\Http\Requests\SaveSmsTemplateRequest;
use App\Http\Requests\UpdateSmsSettingsRequest;
use App\Models\EmailTemplate;
use App\Models\SmsConnection;
use App\Models\SmsMessageLog;
use App\Models\Workspace;
use App\Services\SmsConnectionService;
use App\Services\SmsMessageService;
use App\Services\SmsProviderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class SmsController extends Controller
{
    public function __construct(private readonly SmsConnectionService $connectionService) {}

    public function index(Workspace $workspace): View
    {
        $this->authorizeWorkspace($workspace);
        $connection = $this->connectionService->forWorkspace($workspace);

        return view('sms.index', [
            'workspace' => $workspace,
            'connection' => $connection,
            'effectiveConnection' => $this->connectionService->effectiveFor($workspace),
            'templates' => $connection?->templates()->orderBy('type')->get() ?? collect(),
            'messageLogs' => $workspace->smsMessageLogs()
                ->with('invoice.client')
                ->latest()
                ->limit(10)
                ->get(),
            'templateTypes' => EmailTemplate::TYPES,
        ]);
    }

    public function updateSettings(UpdateSmsSettingsRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->connectionService->updateWorkspaceSettings(
            workspace: $workspace,
            enabled: $request->boolean('sms_auto_reminders_enabled'),
            architecture: $request->string('sms_architecture')->toString(),
            actor: $request->user(),
        );

        return back()->with('success', 'SMS reminder settings updated successfully.');
    }

    public function connect(
        SaveSmsConnectionRequest $request,
        Workspace $workspace,
        SmsProviderService $provider,
    ): RedirectResponse {
        $attributes = $request->validated();
        $connection = null;

        try {
            $connection = $this->connectionService->save($request->user(), $workspace, [
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

            return back()->withInput()->with('error', 'Workspace SMS connection could not be verified: '.$exception->getMessage());
        }

        return back()->with('success', 'Workspace SMS connection verified successfully.');
    }

    public function disconnect(Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);
        $this->connectionService->disconnect(request()->user(), $workspace);

        return back()->with('success', 'Workspace SMS connection disconnected.');
    }

    public function retry(Workspace $workspace, SmsMessageLog $messageLog): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);
        $messageLog = $workspace->smsMessageLogs()->whereKey($messageLog->id)->firstOrFail();

        abort_if($messageLog->status !== SmsMessageLog::STATUS_FAILED, 422, 'Only failed SMS messages can be retried.');

        app(SmsMessageService::class)->retry($messageLog);

        return back()->with('success', 'SMS reminder queued for retry.');
    }

    public function saveTemplate(SaveSmsTemplateRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspace($workspace);

        if ($workspace->sms_architecture === SmsConnection::TYPE_SHARED) {
            return back()->with('error', 'Shared SMS templates are managed by the platform administrator.');
        }

        $connection = $this->connectionService->forWorkspace($workspace);

        if (! $connection) {
            return back()->with('error', 'Connect the workspace SMS account before configuring templates.');
        }

        $validated = $request->validated();
        $connection->templates()->updateOrCreate(
            ['type' => $validated['type']],
            [
                'workspace_id' => $workspace->id,
                'body' => $validated['body'],
                'is_active' => $request->boolean('is_active'),
            ],
        );

        return back()->with('success', 'SMS reminder template saved successfully.');
    }

    private function authorizeWorkspace(Workspace $workspace): void
    {
        abort_unless($workspace->canBeManagedBy(request()->user()), 403);
    }
}
