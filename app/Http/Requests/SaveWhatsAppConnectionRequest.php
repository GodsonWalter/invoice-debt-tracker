<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWhatsAppConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace?->canBeManagedBy($this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:5000'],
            'business_portfolio_id' => ['nullable', 'string', 'max:100'],
            'waba_id' => [Rule::requiredIf(fn (): bool => ! $this->filled('code')), 'nullable', 'string', 'max:100'],
            'phone_number_id' => [Rule::requiredIf(fn (): bool => ! $this->filled('code')), 'nullable', 'string', 'max:100'],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'verified_name' => ['nullable', 'string', 'max:255'],
            'access_token' => [Rule::requiredIf(fn (): bool => ! $this->filled('code') && ! $this->route('workspace')?->whatsappConnections()->exists()), 'nullable', 'string', 'max:10000'],
        ];
    }
}
