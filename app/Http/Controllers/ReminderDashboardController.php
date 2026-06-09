<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReminderDashboardController extends Controller
{
    /**
     * Authorize that the current user is an owner or admin of the workspace
     *
     * @throws HttpResponseException
     */
    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        // deny access if the user is not the workspace owner or admin
        if (! $workspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {

            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    /**
     * Get the current workspace from the request
     */
    private function getCurrentWorkspace(): Workspace
    {
        return app('currentWorkspace');
    }

    public function index()
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);
        $today = now()->startOfDay();
        // return $currentWorkspace->id;
        // Dashboard statistics
        $statistics = [
            'total_sent' => ReminderLog::where('workspace_id', $currentWorkspace->id)->where('status', ReminderLog::STATUS_SENT)->count(),
            'total_failed' => ReminderLog::where('workspace_id', $currentWorkspace->id)->where('status', ReminderLog::STATUS_FAILED)->count(),
            'upcoming_reminders' => Invoice::query()
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->where('workspace_id', $currentWorkspace->id)
                ->whereDate('due_date', '>=', $today)
                ->count(),
            'today_activity' => ReminderLog::query()
                ->where('workspace_id', $currentWorkspace->id)
                ->whereDate('created_at', $today)
                ->count(),
        ];

        // Recent activity (last 10)
        $recentActivity = ReminderLog::query()
            ->with(['invoice', 'invoice.client', 'reminderSchedule'])
            ->where('workspace_id', $currentWorkspace->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('reminders.dashboard.index', [
            'statistics' => $statistics,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function activity(): View
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);

        $query = ReminderLog::query()
            ->with(['invoice', 'invoice.client', 'reminderSchedule'])
            ->where('workspace_id', $currentWorkspace->id);

        // Search filters
        if ($search = request('search')) {
            $query->whereHas('invoice', function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%");
            })->orWhereHas('invoice.client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = request('status')) {
            if (in_array($status, ReminderLog::STATUSES)) {
                $query->where('status', $status);
            }
        }

        // Sort
        $query->orderByDesc('created_at');

        $reminders = $query->paginate(20);

        return view('reminders.dashboard.activity', [
            'reminders' => $reminders,
            'statuses' => ReminderLog::STATUSES,
        ]);
    }

    public function upcoming(): View
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);

        $today = now()->startOfDay();

        $query = Invoice::query()
            ->with(['client', 'payments', 'currency', 'workspace'])
            ->where('workspace_id', $currentWorkspace->id)
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->whereDate('due_date', '>=', $today);

        // Search filters
        if ($search = request('search')) {
            $query->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('client', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        }

        // Sort by nearest due date
        $query->orderBy('due_date');

        $invoices = $query->paginate(20);

        return view('reminders.dashboard.upcoming', [
            'invoices' => $invoices,
        ]);
    }

    public function sent(): View
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);

        $query = ReminderLog::query()
            ->with(['invoice', 'invoice.client', 'reminderSchedule'])
            ->where('workspace_id', $currentWorkspace->id)
            ->where('status', ReminderLog::STATUS_SENT);

        // Search filters
        if ($search = request('search')) {
            $query->whereHas('invoice', function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%");
            })->orWhereHas('invoice.client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Date range filters
        if ($startDate = request('start_date')) {
            $query->whereDate('sent_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate = request('end_date')) {
            $query->whereDate('sent_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Sort
        $query->orderByDesc('sent_at');

        $reminders = $query->paginate(20);

        return view('reminders.dashboard.sent', [
            'reminders' => $reminders,
        ]);
    }

    public function failed(): View
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);

        $query = ReminderLog::query()
            ->with(['invoice', 'invoice.client', 'reminderSchedule'])
            ->where('workspace_id', $currentWorkspace->id)
            ->where('status', ReminderLog::STATUS_FAILED);

        // Search filters
        if ($search = request('search')) {
            $query->whereHas('invoice', function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%");
            })->orWhereHas('invoice.client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Date range filters
        if ($startDate = request('start_date')) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate = request('end_date')) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Sort
        $query->orderByDesc('created_at');

        $reminders = $query->paginate(20);

        return view('reminders.dashboard.failed', [
            'reminders' => $reminders,
        ]);
    }

    public function retry(ReminderLog $log): RedirectResponse
    {
        $currentWorkspace = $this->getCurrentWorkspace();
        $this->authorizeWorkspaceUser($currentWorkspace);

        // Placeholder for retry logic
        // Will be implemented to dispatch a new SendReminderEmailJob

        return back()->with('info', 'Retry functionality coming soon.');
    }
}
