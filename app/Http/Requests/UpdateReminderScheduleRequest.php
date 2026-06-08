<?php

namespace App\Http\Requests;

use App\Models\ReminderSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateReminderScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace?->users()
            ->where('user_id', Auth::id())
            ->where('workspace_user.is_active', true)
            ->whereIn('workspace_user.role', ['owner', 'admin'])
            ->exists() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'direction' => ['required', Rule::in(ReminderSchedule::DIRECTIONS)],
            'days_offset' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
