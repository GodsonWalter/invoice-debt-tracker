<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getMetrics(int $workspaceId): array
    {
        return [
            'revenue' => [],
            'debt' => [],
            'overdue' => [],
        ];
    }

    public function revenueSummary(int $workspaceId): array
    {
        return [
            'total_revenue' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'paid')
                ->sum('total_amount'),

            'monthly_revenue' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),

            'yearly_revenue' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'paid')
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),

            'paid_invoice_count' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'paid')
                ->count(),
        ];
    }


    public function outstandingDebt(int $workspaceId): array
    {
        return [

            'total_debt' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', ['sent', 'overdue', 'partial'])
                ->sum('total_amount'),

            'unpaid_count' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'pending')
                ->count(),

            'customers_owing' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', ['sent', 'overdue'])
                ->distinct()
                ->count('client_id'),
        ];
    }

    public function overdueSummary(int $workspaceId): array
    {
        $oldest = Invoice::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'overdue')
            ->oldest('due_date')
            ->first();

        return [

            'overdue_amount' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'overdue')
                ->sum('total_amount'),

            'overdue_count' => Invoice::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'overdue')
                ->count(),

            'oldest_overdue_invoice' => $oldest,
        ];
    }

    public function recentActivities(int $workspaceId)
    {
        return ActivityLog::query()
            ->where('workspace_id', $workspaceId)
            ->latest()
            ->limit(10)
            ->get();
    }

    public function topDebtors(int $workspaceId)
    {
        return Client::query()
            ->select([
                'clients.id',
                'clients.name',
                DB::raw('SUM(invoices.total_amount) as total_debt')
            ])
            ->join('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.workspace_id', $workspaceId)
            ->whereIn('invoices.status', ['sent', 'overdue'])
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total_debt')
            ->limit(5)
            ->get();
    }

    // public function reminderStats(int $workspaceId){
    //     $totalReminders = Invoice::query()
    //         ->where('workspace_id', $workspaceId)
    //         ->where('reminder_count', '>', 0)
    //         ->count();

    //     $remindersSent = Invoice::query()
    //         ->where('workspace_id', $workspaceId)
    //         ->where('reminder_count', '>', 0)
    //         ->sum('reminder_count');

    //     return [
    //         'total_reminders' => $totalReminders,
    //         'reminders_sent' => $remindersSent,
    //     ];
    // }

    public function outstandingInvoices(int $workspaceId)
    {
        return Invoice::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->latest()
            ->limit(5)
            ->get();
    }
}
