<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveSmsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('workspace')) {
            return $this->route('workspace')->canBeManagedBy($this->user());
        }

        return Gate::allows('manage-platform-sms');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EmailTemplate::TYPES)],
            'body' => ['required', 'string', 'max:1600'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
