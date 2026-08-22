<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('workspace')) {
            return $this->route('workspace')->canBeManagedBy($this->user());
        }

        return $this->user()?->canManagePlatformUsers() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EmailTemplate::TYPES)],
            'template_name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'language_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'body' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
