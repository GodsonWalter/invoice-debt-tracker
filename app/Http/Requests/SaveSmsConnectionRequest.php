<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSmsConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('workspace')?->canBeManagedBy($this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['twilio'])],
            'provider_account_id' => ['required', 'string', 'max:100'],
            'sender' => ['nullable', 'string', 'max:80', 'required_without:messaging_service_id'],
            'messaging_service_id' => ['nullable', 'string', 'max:100', 'required_without:sender'],
            'auth_token' => [
                Rule::requiredIf(fn (): bool => ! $this->route('workspace')?->smsConnections()->exists()),
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }
}
