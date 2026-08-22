<?php

namespace App\Http\Requests;

use App\Models\SmsConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('workspace')?->canBeManagedBy($this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'sms_auto_reminders_enabled' => ['required', 'boolean'],
            'sms_architecture' => ['required', Rule::in(SmsConnection::TYPES)],
        ];
    }
}
