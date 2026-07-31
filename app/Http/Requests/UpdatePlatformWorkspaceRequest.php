<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdatePlatformWorkspaceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manage-platform-workspaces');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workspaces', 'slug')->ignore($this->workspaceId()),
            ],
            'subdomain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('workspaces', 'subdomain')->ignore($this->workspaceId()),
            ],
            'invoice_prefix' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'json'],
            'currency_id' => [
                'nullable',
                'integer',
                Rule::exists('currencies', 'id')->where(fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function workspaceId(): int|string|null
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? $workspace->getKey() : $workspace;
    }
}
