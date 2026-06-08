<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReminderScheduleRequest;
use App\Http\Requests\UpdateReminderScheduleRequest;
use App\Models\ReminderSchedule;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ReminderScheduleController extends Controller
{
    public function index(Workspace $workspace): View
    {
        $this->authorizeWorkspaceAdmin($workspace);

        return view('reminder-schedules.index', [
            'workspace' => $workspace,
            'reminderSchedules' => $workspace->reminderSchedules()
                ->orderBy('direction')
                ->orderBy('days_offset')
                ->paginate(12),
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorizeWorkspaceAdmin($workspace);

        return view('reminder-schedules.create', [
            'workspace' => $workspace,
            'reminderSchedule' => new ReminderSchedule([
                'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
                'days_offset' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreReminderScheduleRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspaceAdmin($workspace);

        $workspace->reminderSchedules()->create($request->validated());

        return redirect()
            ->route('reminder-schedules.index', $workspace)
            ->with('success', 'Reminder schedule created successfully.');
    }

    public function edit(Workspace $workspace, ReminderSchedule $reminderSchedule): View
    {
        $this->authorizeWorkspaceAdmin($workspace);
        $reminderSchedule = $this->workspaceSchedule($workspace, $reminderSchedule);

        return view('reminder-schedules.edit', [
            'workspace' => $workspace,
            'reminderSchedule' => $reminderSchedule,
        ]);
    }

    public function update(UpdateReminderScheduleRequest $request, Workspace $workspace, ReminderSchedule $reminderSchedule): RedirectResponse
    {
        $this->authorizeWorkspaceAdmin($workspace);
        $reminderSchedule = $this->workspaceSchedule($workspace, $reminderSchedule);

        $reminderSchedule->update($request->validated());

        return redirect()
            ->route('reminder-schedules.index', $workspace)
            ->with('success', 'Reminder schedule updated successfully.');
    }

    public function destroy(Workspace $workspace, ReminderSchedule $reminderSchedule): RedirectResponse
    {
        $this->authorizeWorkspaceAdmin($workspace);
        $reminderSchedule = $this->workspaceSchedule($workspace, $reminderSchedule);

        $reminderSchedule->delete();

        return redirect()
            ->route('reminder-schedules.index', $workspace)
            ->with('success', 'Reminder schedule deleted successfully.');
    }

    public function toggle(Workspace $workspace, ReminderSchedule $reminderSchedule): RedirectResponse
    {
        $this->authorizeWorkspaceAdmin($workspace);
        $reminderSchedule = $this->workspaceSchedule($workspace, $reminderSchedule);

        $reminderSchedule->update(['is_active' => !$reminderSchedule->is_active]);

        return redirect()
            ->route('reminder-schedules.index', $workspace)
            ->with('success', 'Reminder schedule status updated successfully.');
    }

    private function authorizeWorkspaceAdmin(Workspace $workspace): void
    {
        if (!$workspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {
            abort(403, 'You are not authorized to manage reminder schedules for this workspace.');
        }
    }

    private function workspaceSchedule(Workspace $workspace, ReminderSchedule $reminderSchedule): ReminderSchedule
    {
        return $workspace->reminderSchedules()->where('id', $reminderSchedule->id)->firstOrFail();
    }
}
