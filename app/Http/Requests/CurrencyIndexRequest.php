<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CurrencyIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $search = trim((string) $this->input('search', ''));

        $this->merge([
            'search' => $search !== '' ? $search : null,
            'status' => $this->input('status', 'all'),
            'sort' => $this->input('sort', 'code'),
            'direction' => $this->input('direction', 'asc'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:all,active,inactive,deleted'],
            'sort' => ['required', 'in:code,symbol,name,is_active,created_at'],
            'direction' => ['required', 'in:asc,desc'],
        ];
    }
}
