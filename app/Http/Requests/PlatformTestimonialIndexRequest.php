<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PlatformTestimonialIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'workspace_id' => ['nullable', 'integer', Rule::exists('workspaces', 'id')],
            'status' => ['nullable', Rule::in(Testimonial::STATUSES)],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'featured' => ['nullable', 'boolean'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];
    }
}
