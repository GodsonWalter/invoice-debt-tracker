<?php

namespace App\Services;

use App\Models\SmsConnection;
use App\Models\SmsConnectionAudit;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SmsConnectionService
{
    public function shared(): ?SmsConnection
    {
        $connection = SmsConnection::query()
            ->whereNull('workspace_id')
            ->where('connection_type', SmsConnection::TYPE_SHARED)
            ->latest('id')
            ->first();

        if ($connection) {
            return $connection;
        }

        $configuration = config('services.sms');

        if (! filled($configuration['shared_account_sid'] ?? null)
            || ! filled($configuration['shared_auth_token'] ?? null)
            || (! filled($configuration['shared_from'] ?? null) && ! filled($configuration['shared_messaging_service_sid'] ?? null))) {
            return null;
        }

        $connection = new SmsConnection([
            'connection_type' => SmsConnection::TYPE_SHARED,
            'provider' => $configuration['provider'] ?? 'twilio',
            'provider_account_id' => $configuration['shared_account_sid'],
            'sender' => $configuration['shared_from'] ?? null,
            'messaging_service_id' => $configuration['shared_messaging_service_sid'] ?? null,
            'status' => SmsConnection::STATUS_CONNECTED,
            'is_enabled' => true,
        ]);
        $connection->auth_token = $configuration['shared_auth_token'];

        return $connection;
    }

    public function forWorkspace(Workspace $workspace): ?SmsConnection
    {
        return $workspace->smsConnections()
            ->where('connection_type', SmsConnection::TYPE_WORKSPACE)
            ->latest('id')
            ->first();
    }

    public function effectiveFor(Workspace $workspace): ?SmsConnection
    {
        return $workspace->sms_architecture === SmsConnection::TYPE_WORKSPACE
            ? $this->forWorkspace($workspace)
            : $this->shared();
    }

    public function updateWorkspaceSettings(Workspace $workspace, bool $enabled, string $architecture, User $actor): Workspace
    {
        return DB::transaction(function () use ($workspace, $enabled, $architecture, $actor): Workspace {
            $before = [
                'sms_auto_reminders_enabled' => $workspace->sms_auto_reminders_enabled,
                'sms_architecture' => $workspace->sms_architecture,
            ];

            $workspace->forceFill([
                'sms_auto_reminders_enabled' => $enabled,
                'sms_architecture' => $architecture,
            ])->save();

            $this->audit(
                connection: $this->forWorkspace($workspace),
                workspace: $workspace,
                actor: $actor,
                event: 'workspace_sms_settings.updated',
                changes: $this->changes($before, [
                    'sms_auto_reminders_enabled' => $workspace->sms_auto_reminders_enabled,
                    'sms_architecture' => $workspace->sms_architecture,
                ]),
            );

            return $workspace->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(User $actor, ?Workspace $workspace, array $attributes): SmsConnection
    {
        $type = $workspace ? SmsConnection::TYPE_WORKSPACE : SmsConnection::TYPE_SHARED;

        return DB::transaction(function () use ($actor, $workspace, $attributes, $type): SmsConnection {
            $query = SmsConnection::query()
                ->where('connection_type', $type)
                ->when(
                    $workspace,
                    fn ($builder) => $builder->where('workspace_id', $workspace->id),
                    fn ($builder) => $builder->whereNull('workspace_id'),
                );

            $connection = $query->lockForUpdate()->first() ?? new SmsConnection([
                'workspace_id' => $workspace?->id,
                'connection_type' => $type,
            ]);
            $before = $this->auditValues($connection);

            $connection->forceFill([
                'workspace_id' => $workspace?->id,
                'connection_type' => $type,
                'provider' => $attributes['provider'] ?? $connection->provider ?? 'twilio',
                'provider_account_id' => $attributes['provider_account_id'] ?? $connection->provider_account_id,
                'sender' => $attributes['sender'] ?? $connection->sender,
                'messaging_service_id' => $attributes['messaging_service_id'] ?? $connection->messaging_service_id,
                'status' => $attributes['status'] ?? SmsConnection::STATUS_PENDING,
                'is_enabled' => array_key_exists('is_enabled', $attributes)
                    ? (bool) $attributes['is_enabled']
                    : ($connection->is_enabled ?? false),
                'last_error' => $attributes['last_error'] ?? null,
                'updated_by' => $actor->id,
            ]);

            if (array_key_exists('auth_token', $attributes) && filled($attributes['auth_token'])) {
                $connection->auth_token = $attributes['auth_token'];
            }

            if (! $connection->provider_account_id
                || ! $connection->auth_token
                || (! $connection->sender && ! $connection->messaging_service_id)) {
                $connection->status = SmsConnection::STATUS_PENDING;
                $connection->is_enabled = false;
            }

            if (! $connection->exists) {
                $connection->created_by = $actor->id;
            }

            $connection->save();

            $this->audit(
                connection: $connection,
                workspace: $workspace,
                actor: $actor,
                event: $type === SmsConnection::TYPE_SHARED
                    ? 'platform_sms_connection.updated'
                    : 'workspace_sms_connection.updated',
                changes: $this->changes($before, $this->auditValues($connection)),
            );

            return $connection->refresh();
        });
    }

    public function disconnect(User $actor, ?Workspace $workspace): void
    {
        $connection = $workspace ? $this->forWorkspace($workspace) : $this->shared();

        if (! $connection?->exists) {
            return;
        }

        $previousStatus = $connection->status;

        DB::transaction(function () use ($actor, $workspace, $connection, $previousStatus): void {
            $connection->forceFill([
                'auth_token' => null,
                'status' => SmsConnection::STATUS_DISCONNECTED,
                'is_enabled' => false,
                'last_error' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit(
                connection: $connection,
                workspace: $workspace,
                actor: $actor,
                event: $workspace ? 'workspace_sms_connection.disconnected' : 'platform_sms_connection.disconnected',
                changes: ['status' => ['old' => $previousStatus, 'new' => SmsConnection::STATUS_DISCONNECTED]],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(SmsConnection $connection): array
    {
        return [
            'workspace_id' => $connection->workspace_id,
            'connection_type' => $connection->connection_type,
            'provider' => $connection->provider,
            'provider_account_id' => $connection->provider_account_id,
            'sender' => $connection->sender,
            'messaging_service_id' => $connection->messaging_service_id,
            'status' => $connection->status,
            'is_enabled' => $connection->is_enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old:mixed, new:mixed}>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changes[$key] = ['old' => $before[$key] ?? null, 'new' => $value];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, array{old:mixed, new:mixed}>  $changes
     */
    private function audit(
        ?SmsConnection $connection,
        ?Workspace $workspace,
        User $actor,
        string $event,
        array $changes,
    ): void {
        if ($changes === [] && ! str_ends_with($event, '.disconnected')) {
            return;
        }

        SmsConnectionAudit::query()->create([
            'sms_connection_id' => $connection?->id,
            'workspace_id' => $workspace?->id,
            'actor_user_id' => $actor->id,
            'event' => $event,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
