<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePlatformWhatsAppConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-platform-whatsapp');
    }

    public function rules(): array
    {
        return [
            'business_portfolio_id' => ['nullable', 'string', 'max:100'],
            'waba_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['required', 'string', 'max:100'],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'verified_name' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
