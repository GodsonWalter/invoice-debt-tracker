<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceDashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceDashboardService
{
    private const DEBT_STATUSES = [
        Invoice::STATUS_SENT,
        Invoice::STATUS_PARTIAL,
        Invoice::STATUS_OVERDUE,
    ];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Workspace $workspace, User $user, WorkspaceDashboardPeriod $period): array
    {
        if (! $workspace->canBeManagedBy($user)) {
            throw new AuthorizationException('You are not authorized to view this workspace dashboard.');
        }

        $workspace->loadMissing(['currency', 'businessProfile']);
        $reminders = $this->reminderSummary($workspace, $period);

        return [
            'period' => $period,
            'kpis' => $this->kpis($workspace, $period, $reminders),
            'chart' => $this->collectionChart($workspace, $period),
            'invoiceStatuses' => $this->invoiceStatuses($workspace, $period),
            'topDebtors' => $this->topDebtors($workspace),
            'recentInvoices' => $this->recentInvoices($workspace),
            'recentPayments' => $this->recentPayments($workspace),
            'overdueInvoices' => $this->overdueInvoices($workspace),
            'reminders' => $reminders,
            'activities' => $this->recentActivities($workspace),
            'alerts' => $this->alerts($workspace),
            'quickActions' => $this->quickActions($workspace),
            'currency' => [
                'code' => $workspace->currency?->code,
                'symbol' => $workspace->currency?->symbol,
            ],
        ];
    }

    /**
     * @param  array{sent: int, failed: int, pending: int, upcoming: int, without_schedule: int, success_rate: float, last_activity_at: ?CarbonImmutable}  $reminders
     * @return array<string, mixed>
     */
    private function kpis(Workspace $workspace, WorkspaceDashboardPeriod $period, array $reminders): array
    {
        $now = CarbonImmutable::now(config('app.timezone', 'UTC'));

        $paymentStats = Payment::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('COALESCE(SUM(amount), 0) as all_time_revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as period_revenue', [
                $period->start->toDateTimeString(),
                $period->end->toDateTimeString(),
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as comparison_revenue', [
                $period->comparisonStart->toDateTimeString(),
                $period->comparisonEnd->toDateTimeString(),
            ])
            ->selectRaw('SUM(CASE WHEN payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as period_payment_count', [
                $period->start->toDateTimeString(),
                $period->end->toDateTimeString(),
            ])
            ->first();

        $balanceQuery = $this->balanceQuery($workspace);
        $remainingBalance = $this->remainingBalanceExpression();
        $debt = (clone $balanceQuery)
            ->selectRaw("COALESCE(SUM(CASE WHEN {$remainingBalance} > 0 THEN {$remainingBalance} ELSE 0 END), 0) as total_debt")
            ->selectRaw("SUM(CASE WHEN {$remainingBalance} > 0 THEN 1 ELSE 0 END) as unpaid_count")
            ->first();

        $customersOwing = (clone $balanceQuery)
            ->whereRaw("{$remainingBalance} > 0")
            ->distinct()
            ->count('invoices.client_id');

        $overdueQuery = (clone $balanceQuery)
            ->whereDate('invoices.due_date', '<', $now->toDateString())
            ->whereRaw("{$remainingBalance} > 0");
        $overdue = (clone $overdueQuery)
            ->selectRaw("COALESCE(SUM({$remainingBalance}), 0) as amount")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MIN(invoices.due_date) as oldest_due_date')
            ->first();

        $pending = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [Invoice::STATUS_DRAFT, ...self::DEBT_STATUSES])
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as value')
            ->first();

        return [
            'revenue_all_time' => (float) ($paymentStats->all_time_revenue ?? 0),
            'revenue_period' => (float) ($paymentStats->period_revenue ?? 0),
            'revenue_change_percent' => $this->percentageChange(
                (float) ($paymentStats->comparison_revenue ?? 0),
                (float) ($paymentStats->period_revenue ?? 0),
            ),
            'payments_period' => (float) ($paymentStats->period_revenue ?? 0),
            'payment_transactions_period' => (int) ($paymentStats->period_payment_count ?? 0),
            'outstanding_debt' => (float) ($debt->total_debt ?? 0),
            'unpaid_count' => (int) ($debt->unpaid_count ?? 0),
            'customers_owing' => (int) $customersOwing,
            'overdue_amount' => (float) ($overdue->amount ?? 0),
            'overdue_count' => (int) ($overdue->count ?? 0),
            'oldest_overdue_age' => $overdue->oldest_due_date
                ? CarbonImmutable::parse($overdue->oldest_due_date)->startOfDay()->diffInDays($now->startOfDay())
                : null,
            'pending_invoice_count' => (int) ($pending->count ?? 0),
            'pending_invoice_value' => (float) ($pending->value ?? 0),
            'reminders_sent_period' => $reminders['sent'],
            'reminders_failed_period' => $reminders['failed'],
            'reminder_success_rate' => $reminders['success_rate'],
        ];
    }

    /**
     * @return array{labels: array<int, string>, invoiced: array<int, float>, payments: array<int, float>, outstanding: array<int, float>}
     */
    private function collectionChart(Workspace $workspace, WorkspaceDashboardPeriod $period): array
    {
        $isDaily = $period->granularity === 'day';
        $bucketStart = $isDaily ? $period->start->startOfDay() : $period->start->startOfMonth();
        $bucketEnd = $isDaily ? $period->end->startOfDay() : $period->end->startOfMonth();
        $buckets = [];

        for ($bucket = $bucketStart; $bucket->lessThanOrEqualTo($bucketEnd); $bucket = $isDaily ? $bucket->addDay() : $bucket->addMonth()) {
            $key = $this->bucketKey($bucket, $isDaily);
            $buckets[$key] = [
                'label' => $isDaily ? $bucket->format('d M') : $bucket->format('M Y'),
                'invoiced' => 0.0,
                'payments' => 0.0,
            ];
        }

        $invoiceRows = $this->groupedAmountRows(
            Invoice::query()
                ->where('workspace_id', $workspace->id)
                ->whereBetween('issue_date', [$period->start->toDateTimeString(), $period->end->toDateTimeString()]),
            'issue_date',
            'total_amount',
            $isDaily,
        );
        $paymentRows = $this->groupedAmountRows(
            Payment::query()
                ->where('workspace_id', $workspace->id)
                ->whereBetween('payment_date', [$period->start->toDateTimeString(), $period->end->toDateTimeString()]),
            'payment_date',
            'amount',
            $isDaily,
        );

        foreach ($invoiceRows as $row) {
            $key = $this->rowBucketKey($row, $isDaily);
            if (isset($buckets[$key])) {
                $buckets[$key]['invoiced'] = (float) $row->total;
            }
        }

        foreach ($paymentRows as $row) {
            $key = $this->rowBucketKey($row, $isDaily);
            if (isset($buckets[$key])) {
                $buckets[$key]['payments'] = (float) $row->total;
            }
        }

        $runningOutstanding = max(
            (float) Invoice::query()
                ->where('workspace_id', $workspace->id)
                ->where('issue_date', '<', $bucketStart->toDateTimeString())
                ->sum('total_amount')
            - (float) Payment::query()
                ->where('workspace_id', $workspace->id)
                ->where('payment_date', '<', $bucketStart->toDateTimeString())
                ->sum('amount'),
            0.0,
        );

        foreach ($buckets as &$bucket) {
            $runningOutstanding = max($runningOutstanding + $bucket['invoiced'] - $bucket['payments'], 0.0);
            $bucket['outstanding'] = $runningOutstanding;
        }
        unset($bucket);

        return [
            'labels' => array_column($buckets, 'label'),
            'invoiced' => array_column($buckets, 'invoiced'),
            'payments' => array_column($buckets, 'payments'),
            'outstanding' => array_column($buckets, 'outstanding'),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function invoiceStatuses(Workspace $workspace, WorkspaceDashboardPeriod $period): Collection
    {
        return Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('issue_date', [$period->start->toDateTimeString(), $period->end->toDateTimeString()])
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as value')
            ->groupBy('status')
            ->orderBy('status')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function topDebtors(Workspace $workspace): Collection
    {
        $remainingBalance = $this->remainingBalanceExpression();

        return Client::query()
            ->select(['clients.id', 'clients.name'])
            ->join('invoices', 'clients.id', '=', 'invoices.client_id')
            ->leftJoinSub($this->paymentTotals($workspace), 'dashboard_payment_totals', function ($join): void {
                $join->on('dashboard_payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->where('clients.workspace_id', $workspace->id)
            ->where('invoices.workspace_id', $workspace->id)
            ->whereIn('invoices.status', self::DEBT_STATUSES)
            ->whereRaw("{$remainingBalance} > 0")
            ->selectRaw("COALESCE(SUM({$remainingBalance}), 0) as outstanding_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN invoices.due_date < ? THEN {$remainingBalance} ELSE 0 END), 0) as overdue_amount", [now()->toDateString()])
            ->selectRaw('COUNT(DISTINCT invoices.id) as unpaid_invoice_count')
            ->selectRaw('MIN(invoices.issue_date) as oldest_unpaid_date')
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('outstanding_amount')
            ->limit(5)
            ->get();
    }

    private function recentInvoices(Workspace $workspace): Collection
    {
        return $workspace->invoices()
            ->with(['client', 'payments'])
            ->select(['id', 'workspace_id', 'client_id', 'invoice_number', 'issue_date', 'due_date', 'status', 'total_amount'])
            ->latest('issue_date')
            ->limit(5)
            ->get();
    }

    private function recentPayments(Workspace $workspace): Collection
    {
        return $workspace->payments()
            ->with(['invoice.client'])
            ->select(['id', 'workspace_id', 'invoice_id', 'amount', 'payment_date', 'payment_method', 'reference'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function overdueInvoices(Workspace $workspace): Collection
    {
        $lastReminder = ReminderLog::query()
            ->selectRaw('MAX(sent_at)')
            ->whereColumn('reminder_logs.invoice_id', 'invoices.id')
            ->where('reminder_logs.workspace_id', $workspace->id)
            ->where('reminder_logs.status', ReminderLog::STATUS_SENT);
        $lastReminderStatus = ReminderLog::query()
            ->select('status')
            ->whereColumn('reminder_logs.invoice_id', 'invoices.id')
            ->where('reminder_logs.workspace_id', $workspace->id)
            ->latest('created_at')
            ->limit(1);

        return $this->balanceQuery($workspace)
            ->select('invoices.*')
            ->selectSub($lastReminder, 'last_reminder_sent_at')
            ->selectSub($lastReminderStatus, 'last_reminder_status')
            ->with(['client', 'payments'])
            ->whereDate('invoices.due_date', '<', now()->toDateString())
            ->whereRaw($this->remainingBalanceExpression().' > 0')
            ->orderBy('invoices.due_date')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{sent: int, failed: int, pending: int, upcoming: int, without_schedule: int, success_rate: float, last_activity_at: ?CarbonImmutable}
     */
    private function reminderSummary(Workspace $workspace, WorkspaceDashboardPeriod $period): array
    {
        $stats = ReminderLog::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('SUM(CASE WHEN status = ? AND sent_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as sent', [
                ReminderLog::STATUS_SENT,
                $period->start->toDateTimeString(),
                $period->end->toDateTimeString(),
            ])
            ->selectRaw('SUM(CASE WHEN status = ? AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as failed', [
                ReminderLog::STATUS_FAILED,
                $period->start->toDateTimeString(),
                $period->end->toDateTimeString(),
            ])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [ReminderLog::STATUS_PENDING])
            ->selectRaw('MAX(updated_at) as last_activity_at')
            ->first();

        $withoutSchedule = $this->balanceQuery($workspace)
            ->whereRaw($this->remainingBalanceExpression().' > 0')
            ->whereNotExists(function ($query) use ($workspace): void {
                $query->selectRaw('1')
                    ->from('reminder_schedules')
                    ->whereColumn('reminder_schedules.workspace_id', 'invoices.workspace_id')
                    ->where('reminder_schedules.workspace_id', $workspace->id)
                    ->where('reminder_schedules.is_active', true);
            })
            ->count();

        $sent = (int) ($stats->sent ?? 0);
        $failed = (int) ($stats->failed ?? 0);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'pending' => (int) ($stats->pending ?? 0),
            'upcoming' => (int) ($stats->pending ?? 0),
            'without_schedule' => $withoutSchedule,
            'success_rate' => $sent + $failed > 0 ? round(($sent / ($sent + $failed)) * 100, 1) : 0.0,
            'last_activity_at' => $stats->last_activity_at ? CarbonImmutable::parse($stats->last_activity_at) : null,
        ];
    }

    private function recentActivities(Workspace $workspace): Collection
    {
        $activityLogs = ActivityLog::query()
            ->where('workspace_id', $workspace->id)
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $activity): array => [
                'type' => $activity->type,
                'description' => $activity->description,
                'actor' => $activity->user?->name,
                'date' => $activity->created_at,
                'url' => null,
            ]);

        $invoices = $workspace->invoices()
            ->with('client:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'type' => 'invoice_created',
                'description' => 'Invoice '.$invoice->invoice_number.' was created for '.$invoice->client?->name,
                'actor' => null,
                'date' => $invoice->created_at,
                'url' => route('invoices.show', [$workspace, $invoice], false),
            ]);

        $payments = $workspace->payments()
            ->with('invoice:id,invoice_number')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'type' => 'payment_recorded',
                'description' => 'Payment recorded for invoice '.$payment->invoice?->invoice_number,
                'actor' => null,
                'date' => $payment->created_at,
                'url' => $payment->invoice ? route('invoices.show', [$workspace, $payment->invoice], false) : null,
            ]);

        $reminders = ReminderLog::query()
            ->where('workspace_id', $workspace->id)
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (ReminderLog $log): array => [
                'type' => $log->status === ReminderLog::STATUS_FAILED ? 'reminder_failed' : 'reminder_sent',
                'description' => 'Reminder '.($log->status === ReminderLog::STATUS_FAILED ? 'failed' : 'processed').' for '.$log->recipient_email,
                'actor' => null,
                'date' => $log->sent_at ?? $log->updated_at,
                'url' => $log->invoice_id ? route('invoices.show', [$workspace, $log->invoice_id], false) : null,
            ]);

        return $activityLogs
            ->concat($invoices)
            ->concat($payments)
            ->concat($reminders)
            ->sortByDesc('date')
            ->take(10)
            ->values();
    }

    private function alerts(Workspace $workspace): array
    {
        $alerts = [];
        $remainingBalance = $this->remainingBalanceExpression();

        $overdueWithoutReminder = $this->balanceQuery($workspace)
            ->whereDate('invoices.due_date', '<', now()->toDateString())
            ->whereRaw("{$remainingBalance} > 0")
            ->whereDoesntHave('reminderLogs', function ($query): void {
                $query->where('status', ReminderLog::STATUS_SENT)
                    ->where('sent_at', '>=', now()->subDays(7));
            })
            ->count();
        if ($overdueWithoutReminder > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Overdue invoices need attention',
                'message' => $overdueWithoutReminder.' overdue '.str('invoice')->plural($overdueWithoutReminder).' have no reminder in the last 7 days.',
                'url' => route('invoices.index', $workspace, false),
            ];
        }

        $failedReminders = ReminderLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ReminderLog::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        if ($failedReminders > 0) {
            $alerts[] = [
                'severity' => 'danger',
                'title' => 'Reminder delivery failures',
                'message' => $failedReminders.' reminder '.str('attempt')->plural($failedReminders).' failed in the last 7 days.',
                'url' => route('reminders.failed', [], false),
            ];
        }

        $dueSoon = $this->balanceQuery($workspace)
            ->whereBetween('invoices.due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->whereRaw("{$remainingBalance} > 0")
            ->count();
        if ($dueSoon > 0) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'Invoices due soon',
                'message' => $dueSoon.' unpaid '.str('invoice')->plural($dueSoon).' are due within 7 days.',
                'url' => route('invoices.index', $workspace, false),
            ];
        }

        if (! $workspace->businessProfile || blank($workspace->businessProfile->business_name)) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'Complete your business profile',
                'message' => 'Add your business details so invoices and emails look professional.',
                'url' => route('business-profile.index', [], false),
            ];
        }

        $pendingInvitations = DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('is_active', false)
            ->whereNotNull('activation_token')
            ->count();
        if ($pendingInvitations > 0) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'Pending workspace invitations',
                'message' => $pendingInvitations.' invitation '.str('is')->plural($pendingInvitations).' awaiting acceptance.',
                'url' => route('workspace.users.index', $workspace, false),
            ];
        }

        return array_slice($alerts, 0, 5);
    }

    private function quickActions(Workspace $workspace): array
    {
        return [
            ['label' => 'Create Invoice', 'icon' => 'bi-receipt', 'url' => route('invoices.create', $workspace, false)],
            ['label' => 'Add Customer', 'icon' => 'bi-person-plus', 'url' => route('clients.create', $workspace, false)],
            ['label' => 'Record Payment', 'icon' => 'bi-cash-coin', 'url' => route('invoices.index', $workspace, false)],
            ['label' => 'Overdue Invoices', 'icon' => 'bi-exclamation-triangle', 'url' => route('invoices.index', $workspace, false)],
            ['label' => 'Reminder Schedules', 'icon' => 'bi-bell', 'url' => route('reminder-schedules.index', $workspace, false)],
            ['label' => 'Email Templates', 'icon' => 'bi-envelope', 'url' => route('email-templates.index', $workspace, false)],
            ['label' => 'Business Profile', 'icon' => 'bi-briefcase', 'url' => route('business-profile.index', [], false)],
            ['label' => 'Invite Workspace User', 'icon' => 'bi-person-add', 'url' => route('workspace.users.create', $workspace, false)],
        ];
    }

    private function balanceQuery(Workspace $workspace): Builder
    {
        return Invoice::query()
            ->from('invoices')
            ->leftJoinSub($this->paymentTotals($workspace), 'dashboard_payment_totals', function ($join): void {
                $join->on('dashboard_payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.workspace_id', $workspace->id)
            ->whereIn('invoices.status', self::DEBT_STATUSES);
    }

    private function paymentTotals(Workspace $workspace): Builder
    {
        return Payment::query()
            ->select('invoice_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_amount')
            ->where('workspace_id', $workspace->id)
            ->groupBy('invoice_id');
    }

    private function groupedAmountRows(Builder $query, string $dateColumn, string $amountColumn, bool $daily): Collection
    {
        [$yearExpression, $monthExpression, $dayExpression] = $this->dateParts($dateColumn);
        $select = $query
            ->selectRaw("{$yearExpression} as dashboard_year, {$monthExpression} as dashboard_month")
            ->selectRaw('COALESCE(SUM('.$amountColumn.'), 0) as total');

        if ($daily) {
            $select
                ->selectRaw("{$dayExpression} as dashboard_day")
                ->groupByRaw("{$yearExpression}, {$monthExpression}, {$dayExpression}");
        } else {
            $select->groupByRaw("{$yearExpression}, {$monthExpression}");
        }

        return $select->get();
    }

    private function remainingBalanceExpression(): string
    {
        return 'invoices.total_amount - COALESCE(dashboard_payment_totals.paid_amount, 0)';
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function dateParts(string $column): array
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? [
                "CAST(strftime('%Y', {$column}) AS INTEGER)",
                "CAST(strftime('%m', {$column}) AS INTEGER)",
                "CAST(strftime('%d', {$column}) AS INTEGER)",
            ]
            : ["YEAR({$column})", "MONTH({$column})", "DAY({$column})"];
    }

    private function bucketKey(CarbonImmutable $bucket, bool $daily): string
    {
        return $daily ? $bucket->format('Y-m-d') : $bucket->format('Y-m');
    }

    private function rowBucketKey(object $row, bool $daily): string
    {
        return $daily
            ? sprintf('%04d-%02d-%02d', $row->dashboard_year, $row->dashboard_month, $row->dashboard_day)
            : sprintf('%04d-%02d', $row->dashboard_year, $row->dashboard_month);
    }

    private function percentageChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
