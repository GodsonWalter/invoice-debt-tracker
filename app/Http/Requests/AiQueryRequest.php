<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Query is required.',
            'query.string' => 'Query must be a string.',
            'query.max' => 'Query must not exceed 500 characters.',
        ];
    }
}
