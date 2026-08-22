<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePlatformSmsConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-platform-sms');
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['twilio'])],
            'provider_account_id' => ['required', 'string', 'max:100'],
            'sender' => ['nullable', 'string', 'max:80', 'required_without:messaging_service_id'],
            'messaging_service_id' => ['nullable', 'string', 'max:100', 'required_without:sender'],
            'auth_token' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
