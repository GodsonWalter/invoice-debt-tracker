<?php

namespace App\Http\Requests;

use App\Models\WhatsAppConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('workspace')?->canBeManagedBy($this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'whatsapp_auto_reminders_enabled' => ['required', 'boolean'],
            'whatsapp_architecture' => ['required', Rule::in(WhatsAppConnection::TYPES)],
        ];
    }
}
