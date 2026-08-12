<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PresentationTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-platform-testimonials');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'featured' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
