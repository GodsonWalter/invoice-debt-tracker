<?php

namespace App\Report;

use App\Data\ReportFilters;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\Workspace;
use App\ReportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportService
{
    private const DEBT_STATUSES = [
        Invoice::STATUS_SENT,
        Invoice::STATUS_PARTIAL,
        Invoice::STATUS_OVERDUE,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Workspace $workspace, ReportFilters $filters, int $rowLimit = 100): array
    {
        $workspace->loadMissing(['currency', 'businessProfile']);
        $query = $this->rowQuery($workspace, $filters);
        $rowsQuery = $rowLimit > 0 ? $query->limit($rowLimit) : $query;
        $rows = $rowsQuery->get()->map(fn ($row): array => $this->rowValues($filters->type, $row));

        return [
            'type' => $filters->type,
            'title' => $filters->type->label(),
            'period' => $filters->period,
            'filters' => $filters,
            'columns' => $this->columns($filters->type),
            'rows' => $rows,
            'summary' => $this->summary($workspace, $filters),
            'breakdown' => $this->breakdown($workspace, $filters),
            'currency' => [
                'code' => $workspace->currency?->code,
                'symbol' => $workspace->currency?->symbol,
            ],
            'business' => $workspace->businessProfile,
        ];
    }

    /**
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(Workspace $workspace, ReportFilters $filters): LazyCollection
    {
        return $this->rowQuery($workspace, $filters)
            ->cursor()
            ->map(fn ($row): array => $this->rowValues($filters->type, $row));
    }

    public function count(Workspace $workspace, ReportFilters $filters): int
    {
        return (int) $this->rowQuery($workspace, $filters)->reorder()->count();
    }

    /**
     * @return array<string, string>
     */
    public function columns(ReportType $type): array
    {
        return match ($type) {
            ReportType::REVENUE, ReportType::PAYMENTS => [
                'payment_date' => 'Payment date',
                'invoice_number' => 'Invoice',
                'customer' => 'Customer',
                'amount' => 'Amount',
                'payment_method' => 'Payment method',
            ],
            ReportType::OUTSTANDING, ReportType::OVERDUE, ReportType::INVOICES => [
                'invoice_number' => 'Invoice',
                'customer' => 'Customer',
                'issue_date' => 'Issue date',
                'due_date' => 'Due date',
                'total_amount' => 'Invoice total',
                'amount_paid' => 'Paid',
                'outstanding_balance' => 'Outstanding',
                'status' => 'Status',
                'days_overdue' => 'Days overdue',
                'reminder_status' => 'Reminder status',
                'last_reminder_sent' => 'Last reminder sent',
            ],
            ReportType::CUSTOMERS => [
                'customer' => 'Customer',
                'total_invoiced' => 'Total invoiced',
                'total_paid' => 'Total paid',
                'outstanding_balance' => 'Outstanding',
                'overdue_balance' => 'Overdue',
                'invoice_count' => 'Invoices',
                'average_payment_days' => 'Average payment days',
            ],
            ReportType::REMINDERS => [
                'created_at' => 'Attempted at',
                'sent_at' => 'Sent at',
                'invoice_number' => 'Invoice',
                'customer' => 'Customer',
                'recipient_email' => 'Recipient',
                'status' => 'Status',
                'error_message' => 'Error',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Workspace $workspace, ReportFilters $filters): array
    {
        return match ($filters->type) {
            ReportType::REVENUE, ReportType::PAYMENTS => $this->paymentSummary($workspace, $filters),
            ReportType::OUTSTANDING => $this->invoiceBalanceSummary($workspace, $filters, false),
            ReportType::OVERDUE => $this->invoiceBalanceSummary($workspace, $filters, true),
            ReportType::CUSTOMERS => $this->customerSummary($workspace, $filters),
            ReportType::REMINDERS => $this->reminderSummary($workspace, $filters),
            ReportType::INVOICES => $this->invoiceSummary($workspace, $filters),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdown(Workspace $workspace, ReportFilters $filters): array
    {
        return match ($filters->type) {
            ReportType::REVENUE => [
                'trend' => $this->groupedPayments($workspace, $filters),
                'methods' => $this->paymentMethods($workspace, $filters),
            ],
            ReportType::PAYMENTS => [
                'methods' => $this->paymentMethods($workspace, $filters),
            ],
            ReportType::OUTSTANDING => ['aging' => $this->agingBreakdown($workspace, $filters)],
            ReportType::OVERDUE => [],
            ReportType::CUSTOMERS => [],
            ReportType::REMINDERS => ['trend' => $this->groupedReminders($workspace, $filters)],
            ReportType::INVOICES => ['statuses' => $this->invoiceStatuses($workspace, $filters)],
        };
    }

    private function rowQuery(Workspace $workspace, ReportFilters $filters): Builder
    {
        return match ($filters->type) {
            ReportType::REVENUE, ReportType::PAYMENTS => $this->paymentRowsQuery($workspace, $filters),
            ReportType::OUTSTANDING => $this->invoiceRowsQuery($workspace, $filters, false, true),
            ReportType::OVERDUE => $this->invoiceRowsQuery($workspace, $filters, true, true),
            ReportType::CUSTOMERS => $this->customerRowsQuery($workspace, $filters),
            ReportType::REMINDERS => $this->reminderRowsQuery($workspace, $filters),
            ReportType::INVOICES => $this->invoiceRowsQuery($workspace, $filters, false, false, true),
        };
    }

    private function paymentRowsQuery(Workspace $workspace, ReportFilters $filters): Builder
    {
        return $this->paymentBaseQuery($workspace, $filters, true)
            ->select([
                'payments.payment_date',
                'invoices.invoice_number',
                'clients.name as customer',
                'payments.amount',
                'payments.payment_method',
            ])
            ->orderBy('payments.payment_date', $filters->direction)
            ->orderBy('payments.id', $filters->direction);
    }

    private function invoiceRowsQuery(Workspace $workspace, ReportFilters $filters, bool $overdueOnly, bool $debtOnly, ?bool $periodFiltered = null): Builder
    {
        $query = $this->invoiceLedgerQuery($workspace, $filters, $periodFiltered ?? (! $overdueOnly && ! $debtOnly));

        if ($debtOnly) {
            $query->whereIn('invoices.status', self::DEBT_STATUSES);
        }

        if ($overdueOnly) {
            $query->whereDate('invoices.due_date', '<', now()->toDateString());
        }

        $sortColumn = match ($filters->sort) {
            'amount' => 'invoices.total_amount',
            'customer' => 'clients.name',
            'days_overdue' => 'invoices.due_date',
            'outstanding' => 'outstanding_balance',
            default => 'invoices.issue_date',
        };

        return $query
            ->orderBy($sortColumn, $filters->direction)
            ->orderBy('invoices.id', $filters->direction);
    }

    private function customerRowsQuery(Workspace $workspace, ReportFilters $filters): Builder
    {
        $paidTotals = $this->paymentTotals($workspace);
        $outstanding = 'invoices.total_amount - COALESCE(report_payment_totals.paid_amount, 0)';
        $overdue = "CASE WHEN invoices.due_date < ? AND {$outstanding} > 0 THEN {$outstanding} ELSE 0 END";
        $averagePaymentDays = DB::connection()->getDriverName() === 'sqlite'
            ? 'AVG(julianday(customer_payments.payment_date) - julianday(customer_invoices.issue_date))'
            : 'AVG(DATEDIFF(customer_payments.payment_date, customer_invoices.issue_date))';

        $query = Client::query()
            ->from('clients')
            ->leftJoin('invoices', function ($join) use ($workspace): void {
                $join->on('invoices.client_id', '=', 'clients.id')
                    ->where('invoices.workspace_id', $workspace->id);
            })
            ->leftJoinSub($paidTotals, 'report_payment_totals', function ($join): void {
                $join->on('report_payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->where('clients.workspace_id', $workspace->id)
            ->select([
                'clients.id',
                'clients.name as customer',
            ])
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(COALESCE(report_payment_totals.paid_amount, 0)), 0) as total_paid')
            ->selectRaw("COALESCE(SUM(CASE WHEN {$outstanding} > 0 THEN {$outstanding} ELSE 0 END), 0) as outstanding_balance")
            ->selectRaw("COALESCE(SUM({$overdue}), 0) as overdue_balance", [now()->toDateString()])
            ->selectRaw('COUNT(DISTINCT invoices.id) as invoice_count')
            ->selectRaw("COALESCE((SELECT {$averagePaymentDays} FROM invoices customer_invoices JOIN payments customer_payments ON customer_payments.invoice_id = customer_invoices.id WHERE customer_invoices.client_id = clients.id AND customer_invoices.workspace_id = ? AND customer_payments.workspace_id = ?), 0) as average_payment_days", [$workspace->id, $workspace->id])
            ->groupBy('clients.id', 'clients.name');

        if ($filters->customerId) {
            $query->where('clients.id', $filters->customerId);
        }

        if ($filters->search) {
            $query->where('clients.name', 'like', '%'.$filters->search.'%');
        }

        if ($filters->invoiceStatus) {
            $query->where('invoices.status', $filters->invoiceStatus);
        }

        if ($filters->amountMin !== null) {
            $query->where('invoices.total_amount', '>=', $filters->amountMin);
        }

        if ($filters->amountMax !== null) {
            $query->where('invoices.total_amount', '<=', $filters->amountMax);
        }

        $sortColumn = $filters->sort === 'customer' ? 'clients.name' : 'outstanding_balance';

        return $query->orderBy($sortColumn, $filters->direction)->orderBy('clients.id');
    }

    private function reminderRowsQuery(Workspace $workspace, ReportFilters $filters): Builder
    {
        $query = ReminderLog::query()
            ->join('invoices', function ($join) use ($workspace): void {
                $join->on('invoices.id', '=', 'reminder_logs.invoice_id')
                    ->where('invoices.workspace_id', $workspace->id);
            })
            ->join('clients', function ($join) use ($workspace): void {
                $join->on('clients.id', '=', 'invoices.client_id')
                    ->where('clients.workspace_id', $workspace->id);
            })
            ->where('reminder_logs.workspace_id', $workspace->id)
            ->where(function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('reminder_logs.status', ReminderLog::STATUS_SENT)
                        ->whereBetween('reminder_logs.sent_at', [$filters->period->start, $filters->period->end]);
                })->orWhere(function ($query) use ($filters): void {
                    $query->where('reminder_logs.status', '!=', ReminderLog::STATUS_SENT)
                        ->whereBetween('reminder_logs.created_at', [$filters->period->start, $filters->period->end]);
                });
            })
            ->select([
                'reminder_logs.created_at',
                'reminder_logs.sent_at',
                'invoices.invoice_number',
                'clients.name as customer',
                'reminder_logs.recipient_email',
                'reminder_logs.status',
                'reminder_logs.error_message',
            ]);

        if ($filters->customerId) {
            $query->where('clients.id', $filters->customerId);
        }

        if ($filters->reminderStatus) {
            $query->where('reminder_logs.status', $filters->reminderStatus);
        }

        if ($filters->search) {
            $query->where(function ($query) use ($filters): void {
                $query->where('invoices.invoice_number', 'like', '%'.$filters->search.'%')
                    ->orWhere('clients.name', 'like', '%'.$filters->search.'%')
                    ->orWhere('reminder_logs.recipient_email', 'like', '%'.$filters->search.'%');
            });
        }

        return $query->orderByDesc('reminder_logs.created_at')->orderByDesc('reminder_logs.id');
    }

    private function paymentBaseQuery(Workspace $workspace, ReportFilters $filters, bool $periodFiltered): Builder
    {
        $query = Payment::query()
            ->from('payments')
            ->join('invoices', function ($join) use ($workspace): void {
                $join->on('invoices.id', '=', 'payments.invoice_id')
                    ->where('invoices.workspace_id', $workspace->id);
            })
            ->join('clients', function ($join) use ($workspace): void {
                $join->on('clients.id', '=', 'invoices.client_id')
                    ->where('clients.workspace_id', $workspace->id);
            })
            ->where('payments.workspace_id', $workspace->id);

        if ($periodFiltered) {
            $query->whereBetween('payments.payment_date', [$filters->period->start->toDateTimeString(), $filters->period->end->toDateTimeString()]);
        }

        if ($filters->customerId) {
            $query->where('clients.id', $filters->customerId);
        }

        if ($filters->paymentMethod) {
            $query->where('payments.payment_method', $filters->paymentMethod);
        }

        if ($filters->search) {
            $query->where(function ($query) use ($filters): void {
                $query->where('invoices.invoice_number', 'like', '%'.$filters->search.'%')
                    ->orWhere('clients.name', 'like', '%'.$filters->search.'%');
            });
        }

        if ($filters->invoiceStatus) {
            $query->where('invoices.status', $filters->invoiceStatus);
        }

        if ($filters->amountMin !== null) {
            $query->where('payments.amount', '>=', $filters->amountMin);
        }

        if ($filters->amountMax !== null) {
            $query->where('payments.amount', '<=', $filters->amountMax);
        }

        return $query;
    }

    private function invoiceLedgerQuery(Workspace $workspace, ReportFilters $filters, bool $periodFiltered): Builder
    {
        $paidTotals = $this->paymentTotals($workspace);
        $outstanding = 'invoices.total_amount - COALESCE(report_payment_totals.paid_amount, 0)';
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

        $query = Invoice::query()
            ->from('invoices')
            ->join('clients', function ($join) use ($workspace): void {
                $join->on('clients.id', '=', 'invoices.client_id')
                    ->where('clients.workspace_id', $workspace->id);
            })
            ->leftJoinSub($paidTotals, 'report_payment_totals', function ($join): void {
                $join->on('report_payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.workspace_id', $workspace->id)
            ->select([
                'invoices.id',
                'invoices.invoice_number',
                'clients.name as customer',
                'invoices.issue_date',
                'invoices.due_date',
                'invoices.total_amount',
                'invoices.status',
            ])
            ->selectRaw('COALESCE(report_payment_totals.paid_amount, 0) as amount_paid')
            ->selectRaw("CASE WHEN {$outstanding} > 0 THEN {$outstanding} ELSE 0 END as outstanding_balance")
            ->selectSub($lastReminder, 'last_reminder_sent')
            ->selectSub($lastReminderStatus, 'reminder_status');

        $query->selectRaw(
            DB::connection()->getDriverName() === 'sqlite'
                ? "CASE WHEN invoices.due_date < ? AND {$outstanding} > 0 THEN CAST(julianday(?) - julianday(invoices.due_date) AS INTEGER) ELSE 0 END as days_overdue"
                : "CASE WHEN invoices.due_date < ? AND {$outstanding} > 0 THEN DATEDIFF(?, invoices.due_date) ELSE 0 END as days_overdue",
            [now()->toDateString(), now()->toDateString()],
        );

        $this->applyInvoiceFilters($query, $filters, $periodFiltered);

        return $query;
    }

    private function applyInvoiceFilters(Builder $query, ReportFilters $filters, bool $periodFiltered): void
    {
        if ($periodFiltered) {
            $query->whereBetween('invoices.issue_date', [$filters->period->start->toDateTimeString(), $filters->period->end->toDateTimeString()]);
        }

        if ($filters->customerId) {
            $query->where('clients.id', $filters->customerId);
        }

        if ($filters->invoiceStatus) {
            $query->where('invoices.status', $filters->invoiceStatus);
        }

        if ($filters->search) {
            $query->where(function ($query) use ($filters): void {
                $query->where('invoices.invoice_number', 'like', '%'.$filters->search.'%')
                    ->orWhere('clients.name', 'like', '%'.$filters->search.'%');
            });
        }

        if ($filters->amountMin !== null) {
            $query->where('invoices.total_amount', '>=', $filters->amountMin);
        }

        if ($filters->amountMax !== null) {
            $query->where('invoices.total_amount', '<=', $filters->amountMax);
        }
    }

    private function paymentTotals(Workspace $workspace): Builder
    {
        return Payment::query()
            ->select('invoice_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_amount')
            ->where('workspace_id', $workspace->id)
            ->groupBy('invoice_id');
    }

    /**
     * @return array<string, float|int|null>
     */
    private function paymentSummary(Workspace $workspace, ReportFilters $filters): array
    {
        $stats = $this->paymentBaseQuery($workspace, $filters, true)
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as total')
            ->selectRaw('COUNT(payments.id) as count')
            ->selectRaw('COALESCE(AVG(payments.amount), 0) as average_payment')
            ->first();
        $invoiceAverageQuery = Invoice::query()
            ->join('clients', function ($join) use ($workspace): void {
                $join->on('clients.id', '=', 'invoices.client_id')
                    ->where('clients.workspace_id', $workspace->id);
            })
            ->where('invoices.workspace_id', $workspace->id);
        $this->applyInvoiceFilters($invoiceAverageQuery, $filters, true);
        $invoiceAverage = $invoiceAverageQuery->avg('invoices.total_amount');

        return [
            'total' => (float) ($stats->total ?? 0),
            'count' => (int) ($stats->count ?? 0),
            'average_payment' => (float) ($stats->average_payment ?? 0),
            'average_invoice' => (float) ($invoiceAverage ?? 0),
        ];
    }

    /**
     * @return array<int, array{payment_method: string, total: float, count: int}>
     */
    private function paymentMethods(Workspace $workspace, ReportFilters $filters): array
    {
        return $this->paymentBaseQuery($workspace, $filters, true)
            ->selectRaw("COALESCE(NULLIF(payments.payment_method, ''), 'Unspecified') as payment_method")
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as total')
            ->selectRaw('COUNT(payments.id) as count')
            ->groupBy('payments.payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'payment_method' => $row->payment_method,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * @return array<string, float|int>
     */
    private function invoiceBalanceSummary(Workspace $workspace, ReportFilters $filters, bool $overdueOnly): array
    {
        $query = $this->invoiceRowsQuery($workspace, $filters, $overdueOnly, true)->reorder();
        $aggregate = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) as total')
            ->selectRaw('COUNT(*) as count')
            ->first();

        return [
            'total' => (float) ($aggregate->total ?? 0),
            'count' => (int) ($aggregate->count ?? 0),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function customerSummary(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->customerRowsQuery($workspace, $filters)->reorder();
        $aggregate = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) as outstanding')
            ->selectRaw('COUNT(*) as customers')
            ->first();

        return [
            'outstanding' => (float) ($aggregate->outstanding ?? 0),
            'customers' => (int) ($aggregate->customers ?? 0),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function reminderSummary(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->reminderRowsQuery($workspace, $filters)->reorder();
        $stats = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->first();
        $sent = (int) ($stats->sent ?? 0);
        $failed = (int) ($stats->failed ?? 0);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'pending' => (int) ($stats->pending ?? 0),
            'success_rate' => $sent + $failed > 0 ? round(($sent / ($sent + $failed)) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function invoiceSummary(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->invoiceRowsQuery($workspace, $filters, false, false, true)->reorder();
        $aggregate = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as paid')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) as outstanding')
            ->first();

        return [
            'count' => (int) ($aggregate->count ?? 0),
            'total' => (float) ($aggregate->total ?? 0),
            'paid' => (float) ($aggregate->paid ?? 0),
            'outstanding' => (float) ($aggregate->outstanding ?? 0),
        ];
    }

    /**
     * @return array<int, array{label: string, total: float}>
     */
    private function groupedPayments(Workspace $workspace, ReportFilters $filters): array
    {
        [$year, $month] = $this->dateParts('payments.payment_date');
        $rows = $this->paymentBaseQuery($workspace, $filters, true)
            ->selectRaw("{$year} as report_year, {$month} as report_month")
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as total')
            ->groupByRaw("{$year}, {$month}")
            ->get()
            ->keyBy(fn ($row): string => sprintf('%04d-%02d', $row->report_year, $row->report_month));
        $buckets = [];
        $start = $filters->period->start->startOfMonth();
        $end = $filters->period->end->startOfMonth();

        for ($monthCursor = $start; $monthCursor->lessThanOrEqualTo($end); $monthCursor = $monthCursor->addMonth()) {
            $key = $monthCursor->format('Y-m');
            $buckets[] = [
                'label' => $monthCursor->format('M Y'),
                'total' => (float) ($rows->get($key)?->total ?? 0),
            ];
        }

        return $buckets;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function groupedReminders(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->reminderRowsQuery($workspace, $filters);
        $activityTimestamp = "CASE WHEN status = '".ReminderLog::STATUS_SENT."' THEN COALESCE(sent_at, created_at) ELSE created_at END";
        [$year, $month] = $this->dateParts($activityTimestamp);
        $rows = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->selectRaw("{$year} as report_year, {$month} as report_month")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw("{$year}, {$month}")
            ->get()
            ->keyBy(fn ($row): string => sprintf('%04d-%02d', $row->report_year, $row->report_month));
        $buckets = [];
        $start = $filters->period->start->startOfMonth();
        $end = $filters->period->end->startOfMonth();

        for ($monthCursor = $start; $monthCursor->lessThanOrEqualTo($end); $monthCursor = $monthCursor->addMonth()) {
            $key = $monthCursor->format('Y-m');
            $buckets[] = [
                'label' => $monthCursor->format('M Y'),
                'count' => (int) ($rows->get($key)?->total ?? 0),
            ];
        }

        return $buckets;
    }

    /**
     * @return array<int, array{status: string, count: int, total: float}>
     */
    private function invoiceStatuses(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->invoiceRowsQuery($workspace, $filters, false, false, true)->reorder();

        return DB::query()->fromSub($query->toBase(), 'report_rows')
            ->select(['status'])
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ])->all();
    }

    /**
     * @return array<int, array{bucket: string, total: float, count: int}>
     */
    private function agingBreakdown(Workspace $workspace, ReportFilters $filters): array
    {
        $query = $this->invoiceRowsQuery($workspace, $filters, false, true)->reorder();
        $today = now()->toDateString();
        $bucket = "CASE WHEN due_date >= ? THEN 'Current' WHEN due_date >= ? THEN '1-30 Days' WHEN due_date >= ? THEN '31-60 Days' WHEN due_date >= ? THEN '61-90 Days' WHEN due_date >= ? THEN '91-180 Days' ELSE '180+ Days' END";
        $bindings = [$today, now()->subDays(30)->toDateString(), now()->subDays(60)->toDateString(), now()->subDays(90)->toDateString(), now()->subDays(180)->toDateString()];

        $bucketed = DB::query()->fromSub($query->toBase(), 'report_rows')
            ->select('outstanding_balance')
            ->selectRaw($bucket.' as bucket', $bindings);

        return DB::query()->fromSub($bucketed, 'report_aging')
            ->select('bucket')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) as total')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('bucket')
            ->get()
            ->map(fn ($row): array => [
                'bucket' => $row->bucket,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function rowValues(ReportType $type, object $row): array
    {
        $values = [];
        foreach (array_keys($this->columns($type)) as $key) {
            $value = $row->{$key} ?? null;
            $values[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) ($value ?? '');
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateParts(string $column): array
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? [
                "CAST(strftime('%Y', {$column}) AS INTEGER)",
                "CAST(strftime('%m', {$column}) AS INTEGER)",
            ]
            : ["YEAR({$column})", "MONTH({$column})"];
    }
}
