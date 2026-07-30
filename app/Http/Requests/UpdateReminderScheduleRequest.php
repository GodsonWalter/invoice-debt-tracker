<?php

namespace App\Http\Requests;

use App\Models\ReminderSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace?->canBeManagedBy($this->user()) ?? false;
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
