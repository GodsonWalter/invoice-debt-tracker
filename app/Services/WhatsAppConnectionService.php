<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConnectionAudit;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WhatsAppConnectionService
{
    public function shared(): ?WhatsAppConnection
    {
        $connection = WhatsAppConnection::query()
            ->whereNull('workspace_id')
            ->where('connection_type', WhatsAppConnection::TYPE_SHARED)
            ->latest('id')
            ->first();

        if ($connection) {
            return $connection;
        }

        $configuration = config('services.whatsapp');

        if (! filled($configuration['shared_phone_number_id'] ?? null)
            || ! filled($configuration['shared_access_token'] ?? null)) {
            return null;
        }

        $connection = new WhatsAppConnection([
            'connection_type' => WhatsAppConnection::TYPE_SHARED,
            'business_portfolio_id' => $configuration['business_portfolio_id'] ?? null,
            'waba_id' => $configuration['shared_waba_id'] ?? null,
            'phone_number_id' => $configuration['shared_phone_number_id'],
            'display_phone_number' => $configuration['shared_display_phone_number'] ?? null,
            'verified_name' => $configuration['shared_verified_name'] ?? null,
            'status' => WhatsAppConnection::STATUS_CONNECTED,
            'is_enabled' => true,
        ]);
        $connection->access_token = $configuration['shared_access_token'];

        return $connection;
    }

    public function forWorkspace(Workspace $workspace): ?WhatsAppConnection
    {
        return $workspace->whatsappConnections()
            ->where('connection_type', WhatsAppConnection::TYPE_TENANT)
            ->latest('id')
            ->first();
    }

    public function effectiveFor(Workspace $workspace): ?WhatsAppConnection
    {
        return $workspace->whatsapp_architecture === WhatsAppConnection::TYPE_SHARED
            ? $this->shared()
            : $this->forWorkspace($workspace);
    }

    public function updateWorkspaceSettings(Workspace $workspace, bool $enabled, string $architecture, User $actor): Workspace
    {
        return DB::transaction(function () use ($workspace, $enabled, $architecture, $actor): Workspace {
            $before = [
                'whatsapp_auto_reminders_enabled' => $workspace->whatsapp_auto_reminders_enabled,
                'whatsapp_architecture' => $workspace->whatsapp_architecture,
            ];

            $workspace->forceFill([
                'whatsapp_auto_reminders_enabled' => $enabled,
                'whatsapp_architecture' => $architecture,
            ])->save();

            $this->audit(
                connection: $this->forWorkspace($workspace),
                workspace: $workspace,
                actor: $actor,
                event: 'workspace_whatsapp_settings.updated',
                changes: $this->changes($before, [
                    'whatsapp_auto_reminders_enabled' => $workspace->whatsapp_auto_reminders_enabled,
                    'whatsapp_architecture' => $workspace->whatsapp_architecture,
                ]),
            );

            return $workspace->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(User $actor, ?Workspace $workspace, array $attributes): WhatsAppConnection
    {
        $type = $workspace ? WhatsAppConnection::TYPE_TENANT : WhatsAppConnection::TYPE_SHARED;

        return DB::transaction(function () use ($actor, $workspace, $attributes, $type): WhatsAppConnection {
            $query = WhatsAppConnection::query()
                ->where('connection_type', $type)
                ->when($workspace, fn ($builder) => $builder->where('workspace_id', $workspace->id), fn ($builder) => $builder->whereNull('workspace_id'));

            $connection = $query->lockForUpdate()->first() ?? new WhatsAppConnection([
                'workspace_id' => $workspace?->id,
                'connection_type' => $type,
            ]);
            $before = $this->auditValues($connection);

            $connection->forceFill([
                'workspace_id' => $workspace?->id,
                'connection_type' => $type,
                'business_portfolio_id' => $attributes['business_portfolio_id'] ?? $connection->business_portfolio_id,
                'waba_id' => $attributes['waba_id'] ?? $connection->waba_id,
                'phone_number_id' => $attributes['phone_number_id'] ?? $connection->phone_number_id,
                'display_phone_number' => $attributes['display_phone_number'] ?? $connection->display_phone_number,
                'verified_name' => $attributes['verified_name'] ?? $connection->verified_name,
                'token_expires_at' => $attributes['token_expires_at'] ?? $connection->token_expires_at,
                'status' => $attributes['status'] ?? WhatsAppConnection::STATUS_PENDING,
                'is_enabled' => array_key_exists('is_enabled', $attributes)
                    ? (bool) $attributes['is_enabled']
                    : ($connection->is_enabled ?? false),
                'last_error' => $attributes['last_error'] ?? null,
                'updated_by' => $actor->id,
            ]);

            if (array_key_exists('access_token', $attributes) && filled($attributes['access_token'])) {
                $connection->access_token = $attributes['access_token'];
            }

            if (! $connection->phone_number_id || ! $connection->access_token) {
                $connection->status = WhatsAppConnection::STATUS_PENDING;
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
                event: $type === WhatsAppConnection::TYPE_SHARED
                    ? 'platform_whatsapp_connection.updated'
                    : 'workspace_whatsapp_connection.updated',
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
                'access_token' => null,
                'status' => WhatsAppConnection::STATUS_DISCONNECTED,
                'is_enabled' => false,
                'last_error' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit(
                connection: $connection,
                workspace: $workspace,
                actor: $actor,
                event: $workspace ? 'workspace_whatsapp_connection.disconnected' : 'platform_whatsapp_connection.disconnected',
                changes: ['status' => ['old' => $previousStatus, 'new' => WhatsAppConnection::STATUS_DISCONNECTED]],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(WhatsAppConnection $connection): array
    {
        return [
            'workspace_id' => $connection->workspace_id,
            'connection_type' => $connection->connection_type,
            'business_portfolio_id' => $connection->business_portfolio_id,
            'waba_id' => $connection->waba_id,
            'phone_number_id' => $connection->phone_number_id,
            'display_phone_number' => $connection->display_phone_number,
            'verified_name' => $connection->verified_name,
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
        ?WhatsAppConnection $connection,
        ?Workspace $workspace,
        User $actor,
        string $event,
        array $changes,
    ): void {
        if ($changes === [] && $event !== 'workspace_whatsapp_connection.disconnected' && $event !== 'platform_whatsapp_connection.disconnected') {
            return;
        }

        WhatsAppConnectionAudit::query()->create([
            'whatsapp_connection_id' => $connection?->id,
            'workspace_id' => $workspace?->id,
            'actor_user_id' => $actor->id,
            'event' => $event,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
