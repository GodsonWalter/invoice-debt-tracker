<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PreviewEmailTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace?->users()
            ->where('user_id', Auth::id())
            ->where('workspace_user.is_active', true)
            ->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(EmailTemplate::TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'invoice_id' => [
                'required',
                Rule::exists('invoices', 'id')->where('workspace_id', $workspace?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
