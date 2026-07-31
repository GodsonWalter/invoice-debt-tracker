<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\User;
use App\Models\UserAccountAudit;
use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Models\WorkspaceLifecycleNotification;
use App\WorkspaceDashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user, WorkspaceDashboardPeriod $period): array
    {
        if (! $user->canManagePlatformUsers()) {
            throw new AuthorizationException('You are not authorized to view the platform dashboard.');
        }

        $workspacesMissingProfile = $this->activeWorkspacesWithoutBusinessProfile();
        $overview = $this->overview($period, $workspacesMissingProfile);
        $system = $this->systemHealth();
        $deletedWorkspaces = $this->deletedWorkspaceSummary();

        return [
            'period' => $period,
            'overview' => $overview,
            'trends' => $this->trends($period),
            'currencies' => $this->currencySummary($period),
            'attention' => $this->attention($overview, $deletedWorkspaces, $system, $workspacesMissingProfile),
            'deleted_workspaces' => $deletedWorkspaces,
            'workspace_health' => $this->workspaceHealth(),
            'activity' => $this->recentActivity(),
            'system' => $system,
            'quick_actions' => $this->quickActions($user),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function overview(WorkspaceDashboardPeriod $period, Collection $workspacesMissingProfile): array
    {
        $activeUsers = User::query()->where('is_active', true);
        $activeWorkspaces = Workspace::query()->where('is_active', true);
        $activeInvoices = $this->activeInvoiceQuery();
        $activePayments = $this->activePaymentQuery();
        $activeReminders = $this->activeReminderQuery();
        $now = CarbonImmutable::now(config('app.timezone', 'UTC'));

        return [
            'active_users' => (clone $activeUsers)->count(),
            'new_users' => (clone $activeUsers)->whereBetween('created_at', $this->dateRange($period))->count(),
            'active_workspaces' => (clone $activeWorkspaces)->count(),
            'new_workspaces' => (clone $activeWorkspaces)->whereBetween('created_at', $this->dateRange($period))->count(),
            'active_memberships' => DB::table('workspace_user')
                ->join('workspaces', 'workspaces.id', '=', 'workspace_user.workspace_id')
                ->join('users', 'users.id', '=', 'workspace_user.user_id')
                ->where('workspaces.is_active', true)
                ->whereNull('workspaces.deleted_at')
                ->where('users.is_active', true)
                ->whereNull('users.deleted_at')
                ->where('workspace_user.is_active', true)
                ->count(),
            'invoices_period' => (clone $activeInvoices)
                ->whereBetween('invoices.issue_date', $this->dateRange($period))
                ->count('invoices.id'),
            'payments_period' => (clone $activePayments)
                ->whereBetween('payments.payment_date', $this->dateRange($period))
                ->count('payments.id'),
            'overdue_invoices' => (clone $this->outstandingInvoiceQuery())
                ->whereDate('invoices.due_date', '<', $now->toDateString())
                ->count('invoices.id'),
            'failed_reminders_period' => (clone $activeReminders)
                ->where('reminder_logs.status', ReminderLog::STATUS_FAILED)
                ->whereBetween('reminder_logs.created_at', $this->dateRange($period))
                ->count('reminder_logs.id'),
            'pending_reminders' => (clone $activeReminders)
                ->where('reminder_logs.status', ReminderLog::STATUS_PENDING)
                ->count('reminder_logs.id'),
            'workspaces_missing_currency' => (clone $activeWorkspaces)->whereDoesntHave('currency')->count(),
            'workspaces_missing_profile' => $workspacesMissingProfile->count(),
        ];
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function activeWorkspacesWithoutBusinessProfile(): Collection
    {
        return Workspace::query()
            ->where('is_active', true)
            ->whereDoesntHave('businessProfile')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'subdomain']);
    }

    /**
     * @return array{labels: array<int, string>, users: array<int, int>, workspaces: array<int, int>, invoices: array<int, int>, payments: array<int, int>}
     */
    private function trends(WorkspaceDashboardPeriod $period): array
    {
        $buckets = $this->trendBuckets($period);

        $this->fillTrend($buckets, 'users', User::query(), 'users.created_at', $period);
        $this->fillTrend($buckets, 'workspaces', Workspace::query()->where('is_active', true), 'workspaces.created_at', $period);
        $this->fillTrend($buckets, 'invoices', $this->activeInvoiceQuery(), 'invoices.issue_date', $period);
        $this->fillTrend($buckets, 'payments', $this->activePaymentQuery(), 'payments.payment_date', $period);

        return [
            'labels' => array_column($buckets, 'label'),
            'users' => array_column($buckets, 'users'),
            'workspaces' => array_column($buckets, 'workspaces'),
            'invoices' => array_column($buckets, 'invoices'),
            'payments' => array_column($buckets, 'payments'),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function currencySummary(WorkspaceDashboardPeriod $period): Collection
    {
        $dateRange = $this->dateRange($period);
        $invoices = (clone $this->activeInvoiceQuery())
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->whereBetween('invoices.issue_date', $dateRange)
            ->selectRaw("COALESCE(currencies.code, 'Unspecified') as code")
            ->selectRaw("COALESCE(currencies.symbol, '') as symbol")
            ->selectRaw('COUNT(invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as invoiced_amount')
            ->groupBy('invoices.currency_id', 'currencies.code', 'currencies.symbol')
            ->get();

        $payments = (clone $this->activePaymentQuery())
            ->join('invoices', function (JoinClause $join): void {
                $join->on('invoices.id', '=', 'payments.invoice_id')
                    ->on('invoices.workspace_id', '=', 'payments.workspace_id');
            })
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->whereBetween('payments.payment_date', $dateRange)
            ->selectRaw("COALESCE(currencies.code, 'Unspecified') as code")
            ->selectRaw("COALESCE(currencies.symbol, '') as symbol")
            ->selectRaw('COUNT(payments.id) as payment_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as collected_amount')
            ->groupBy('invoices.currency_id', 'currencies.code', 'currencies.symbol')
            ->get();

        $outstanding = (clone $this->outstandingInvoiceQuery())
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->selectRaw("COALESCE(currencies.code, 'Unspecified') as code")
            ->selectRaw("COALESCE(currencies.symbol, '') as symbol")
            ->selectRaw('COUNT(invoices.id) as outstanding_invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount - COALESCE(platform_payment_totals.paid_amount, 0)), 0) as outstanding_amount')
            ->groupBy('invoices.currency_id', 'currencies.code', 'currencies.symbol')
            ->get();

        $summary = [];

        foreach ($invoices as $row) {
            $summary[$row->code] = $this->emptyCurrencySummary($row->code, $row->symbol);
            $summary[$row->code]['invoice_count'] = (int) $row->invoice_count;
            $summary[$row->code]['invoiced_amount'] = (float) $row->invoiced_amount;
        }

        foreach ($payments as $row) {
            $summary[$row->code] ??= $this->emptyCurrencySummary($row->code, $row->symbol);
            $summary[$row->code]['payment_count'] = (int) $row->payment_count;
            $summary[$row->code]['collected_amount'] = (float) $row->collected_amount;
        }

        foreach ($outstanding as $row) {
            $summary[$row->code] ??= $this->emptyCurrencySummary($row->code, $row->symbol);
            $summary[$row->code]['outstanding_invoice_count'] = (int) $row->outstanding_invoice_count;
            $summary[$row->code]['outstanding_amount'] = (float) $row->outstanding_amount;
        }

        return collect($summary)->sortKeys()->values();
    }

    /**
     * @param  array<string, int>  $overview
     * @param  array<string, int>  $deletedWorkspaces
     * @param  array<string, int|string|null>  $system
     * @param  Collection<int, Workspace>  $workspacesMissingProfile
     * @return array<int, array{label: string, count: int, tone: string, url: ?string, workspaces?: array<int, array{id: int, name: string, identifier: ?string}>}>
     */
    private function attention(array $overview, array $deletedWorkspaces, array $system, Collection $workspacesMissingProfile): array
    {
        return collect([
            [
                'label' => 'Failed queue jobs',
                'count' => (int) ($system['failed_jobs'] ?? 0),
                'tone' => 'danger',
                'url' => null,
            ],
            [
                'label' => 'Failed lifecycle notifications',
                'count' => (int) ($system['failed_lifecycle_notifications'] ?? 0),
                'tone' => 'danger',
                'url' => route('platform.recovery.audits'),
            ],
            [
                'label' => 'Workspaces due for permanent deletion',
                'count' => $deletedWorkspaces['permanent_deletion_due'],
                'tone' => 'danger',
                'url' => route('platform.recovery.index', ['state' => 'permanent_deletion_due']),
            ],
            [
                'label' => 'Deleted user accounts',
                'count' => (int) ($system['deleted_users'] ?? 0),
                'tone' => 'warning',
                'url' => route('platform.user-recovery.index'),
            ],
            [
                'label' => 'Failed reminders in selected period',
                'count' => $overview['failed_reminders_period'],
                'tone' => 'warning',
                'url' => null,
            ],
            [
                'label' => 'Active workspaces without a currency',
                'count' => $overview['workspaces_missing_currency'],
                'tone' => 'warning',
                'url' => null,
            ],
            [
                'label' => 'Active workspaces without a business profile',
                'count' => $overview['workspaces_missing_profile'],
                'tone' => 'warning',
                'url' => null,
                'workspaces' => $workspacesMissingProfile
                    ->map(fn (Workspace $workspace): array => [
                        'id' => (int) $workspace->getKey(),
                        'name' => $workspace->name,
                        'identifier' => $workspace->subdomain ?: $workspace->slug,
                    ])
                    ->values()
                    ->all(),
            ],
        ])->filter(fn (array $item): bool => $item['count'] > 0)->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function deletedWorkspaceSummary(): array
    {
        $now = CarbonImmutable::now(config('app.timezone', 'UTC'));
        $restoreCutoff = $now->subDays((int) config('workspace-lifecycle.self_service_restore_days'));
        $permanentCutoff = $now->subDays((int) config('workspace-lifecycle.permanent_deletion_days'));
        $warningCutoff = $permanentCutoff->addDays(max(config('workspace-lifecycle.permanent_deletion_warning_days', [30])));
        $query = Workspace::onlyTrashed();

        return [
            'total' => (clone $query)->count(),
            'recoverable' => (clone $query)->where('deleted_at', '>=', $restoreCutoff)->count(),
            'restore_expired' => (clone $query)->where('deleted_at', '<', $restoreCutoff)->where('deleted_at', '>', $warningCutoff)->count(),
            'pending_permanent_deletion' => (clone $query)->where('deleted_at', '<=', $warningCutoff)->where('deleted_at', '>', $permanentCutoff)->count(),
            'permanent_deletion_due' => (clone $query)->where('deleted_at', '<=', $permanentCutoff)->count(),
        ];
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function workspaceHealth(): Collection
    {
        $lastActivity = ActivityLog::query()
            ->selectRaw('MAX(activity_logs.created_at)')
            ->whereColumn('activity_logs.workspace_id', 'workspaces.id');

        return Workspace::query()
            ->with(['owner:id,name,email', 'currency:id,code,symbol'])
            ->withCount([
                'users as active_members_count' => function (Builder $query): void {
                    $query->where('workspace_user.is_active', true);
                },
                'clients',
                'invoices',
            ])
            ->select('workspaces.*')
            ->selectSub($lastActivity, 'last_activity_at')
            ->where('is_active', true)
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, array{type: string, event: string, target: string, actor: string, date: mixed, reason: ?string}>
     */
    private function recentActivity(): Collection
    {
        $workspaceAudits = WorkspaceLifecycleAudit::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (WorkspaceLifecycleAudit $audit): array => [
                'type' => 'workspace',
                'event' => $audit->event,
                'target' => $audit->workspace_name,
                'actor_id' => $audit->actor_user_id,
                'actor' => $audit->actor_type,
                'date' => $audit->created_at,
                'reason' => $audit->reason,
            ]);

        $userAudits = UserAccountAudit::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (UserAccountAudit $audit): array => [
                'type' => 'user',
                'event' => $audit->event,
                'target' => $audit->target_email,
                'actor_id' => $audit->actor_user_id,
                'actor' => $audit->actor_type,
                'date' => $audit->created_at,
                'reason' => $audit->reason,
            ]);

        $audits = $workspaceAudits->merge($userAudits);
        $actorIds = $audits->pluck('actor_id')->filter()->unique()->values();
        $actors = User::withTrashed()->whereKey($actorIds)->pluck('name', 'id');

        return $audits
            ->sortByDesc('date')
            ->take(8)
            ->map(function (array $audit) use ($actors): array {
                $audit['actor'] = $actors->get($audit['actor_id']) ?: $audit['actor'];
                unset($audit['actor_id']);

                return $audit;
            })
            ->values();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function systemHealth(): array
    {
        $queuedJobs = Schema::hasTable('jobs')
            ? DB::table('jobs')->whereNull('reserved_at')->count()
            : null;
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : null;

        return [
            'queue_connection' => (string) config('queue.default'),
            'queued_jobs' => $queuedJobs,
            'failed_jobs' => $failedJobs,
            'failed_lifecycle_notifications' => WorkspaceLifecycleNotification::query()
                ->where('status', WorkspaceLifecycleNotification::STATUS_FAILED)
                ->count(),
            'pending_lifecycle_notifications' => WorkspaceLifecycleNotification::query()
                ->where('status', WorkspaceLifecycleNotification::STATUS_PENDING)
                ->count(),
            'deleted_users' => User::onlyTrashed()->count(),
            'scheduler_status' => 'Not monitored',
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    private function quickActions(User $user): array
    {
        $actions = [
            ['label' => 'Platform users', 'url' => route('platform.users.index'), 'icon' => 'fa-user-gear'],
            ['label' => 'Platform workspaces', 'url' => route('platform.workspaces.index'), 'icon' => 'fa-building-shield'],
        ];

        if ($user->isPlatformOwner()) {
            $actions[] = ['label' => 'Deleted workspaces', 'url' => route('platform.recovery.index'), 'icon' => 'fa-recycle'];
            $actions[] = ['label' => 'Deleted users', 'url' => route('platform.user-recovery.index'), 'icon' => 'fa-user-shield'];
            $actions[] = ['label' => 'Lifecycle audit', 'url' => route('platform.recovery.audits'), 'icon' => 'fa-clipboard-list'];
        }

        return $actions;
    }

    private function activeInvoiceQuery(): Builder
    {
        return Invoice::query()
            ->join('workspaces', 'workspaces.id', '=', 'invoices.workspace_id')
            ->where('workspaces.is_active', true)
            ->whereNull('workspaces.deleted_at');
    }

    private function activePaymentQuery(): Builder
    {
        return Payment::query()
            ->join('workspaces', 'workspaces.id', '=', 'payments.workspace_id')
            ->where('workspaces.is_active', true)
            ->whereNull('workspaces.deleted_at')
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', 'payments.invoice_id')
                    ->whereColumn('invoices.workspace_id', 'payments.workspace_id');
            });
    }

    private function activeReminderQuery(): Builder
    {
        return ReminderLog::query()
            ->join('workspaces', 'workspaces.id', '=', 'reminder_logs.workspace_id')
            ->where('workspaces.is_active', true)
            ->whereNull('workspaces.deleted_at')
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', 'reminder_logs.invoice_id')
                    ->whereColumn('invoices.workspace_id', 'reminder_logs.workspace_id');
            });
    }

    private function outstandingInvoiceQuery(): Builder
    {
        $paymentTotals = $this->activePaymentQuery()
            ->select('payments.invoice_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_amount')
            ->groupBy('payments.invoice_id');

        return $this->activeInvoiceQuery()
            ->leftJoinSub($paymentTotals, 'platform_payment_totals', function (JoinClause $join): void {
                $join->on('platform_payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->whereIn('invoices.status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->whereRaw('invoices.total_amount - COALESCE(platform_payment_totals.paid_amount, 0) > 0');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateRange(WorkspaceDashboardPeriod $period): array
    {
        return [$period->start->toDateTimeString(), $period->end->toDateTimeString()];
    }

    /**
     * @return array<int, array{key: string, label: string, users: int, workspaces: int, invoices: int, payments: int}>
     */
    private function trendBuckets(WorkspaceDashboardPeriod $period): array
    {
        $isDaily = $period->granularity === 'day';
        $start = $isDaily ? $period->start->startOfDay() : $period->start->startOfMonth();
        $end = $isDaily ? $period->end->startOfDay() : $period->end->startOfMonth();
        $buckets = [];

        for ($bucket = $start; $bucket->lessThanOrEqualTo($end); $bucket = $isDaily ? $bucket->addDay() : $bucket->addMonth()) {
            $buckets[] = [
                'key' => $isDaily ? $bucket->format('Y-m-d') : $bucket->format('Y-m'),
                'label' => $isDaily ? $bucket->format('d M') : $bucket->format('M Y'),
                'users' => 0,
                'workspaces' => 0,
                'invoices' => 0,
                'payments' => 0,
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<int, array{key: string, label: string, users: int, workspaces: int, invoices: int, payments: int}>  $buckets
     */
    private function fillTrend(array &$buckets, string $metric, Builder $query, string $dateColumn, WorkspaceDashboardPeriod $period): void
    {
        $rows = $query
            ->whereBetween($dateColumn, $this->dateRange($period))
            ->selectRaw("DATE({$dateColumn}) as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->get();
        $bucketIndexes = collect($buckets)->mapWithKeys(fn (array $bucket, int $index): array => [$bucket['key'] => $index]);

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->bucket, config('app.timezone', 'UTC'));
            $key = $period->granularity === 'day' ? $date->format('Y-m-d') : $date->format('Y-m');
            $index = $bucketIndexes->get($key);

            if ($index !== null) {
                $buckets[$index][$metric] = (int) $row->total;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCurrencySummary(string $code, string $symbol): array
    {
        return [
            'code' => $code,
            'symbol' => $symbol,
            'invoice_count' => 0,
            'invoiced_amount' => 0.0,
            'payment_count' => 0,
            'collected_amount' => 0.0,
            'outstanding_invoice_count' => 0,
            'outstanding_amount' => 0.0,
        ];
    }
}
