<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateEmailTemplateRequest extends FormRequest
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
        $emailTemplate = $this->route('email_template');

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                Rule::in(EmailTemplate::TYPES),
                Rule::unique('email_templates', 'type')
                    ->where('workspace_id', $workspace?->id)
                    ->ignore($emailTemplate?->id),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
