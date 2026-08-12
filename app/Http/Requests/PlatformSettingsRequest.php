<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-platform-settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:120'],
            'product_title' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'dark_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon,image/webp', 'max:1024'],
            'login_title' => ['nullable', 'string', 'max:160'],
            'login_description' => ['nullable', 'string', 'max:500'],
            'default_page_title_suffix' => ['nullable', 'string', 'max:100'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:160'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'twitter_title' => ['nullable', 'string', 'max:160'],
            'twitter_description' => ['nullable', 'string', 'max:320'],
            'twitter_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'support_url' => ['nullable', 'url', 'max:2048'],
            'privacy_url' => ['nullable', 'url', 'max:2048'],
            'terms_url' => ['nullable', 'url', 'max:2048'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'maintenance_banner_text' => ['nullable', 'string', 'max:500'],
            'maintenance_banner_enabled' => ['required', 'boolean'],
            'social_links' => ['nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url', 'max:2048'],
            'social_links.facebook' => ['nullable', 'url', 'max:2048'],
            'social_links.instagram' => ['nullable', 'url', 'max:2048'],
            'social_links.youtube' => ['nullable', 'url', 'max:2048'],
            'social_links.x' => ['nullable', 'url', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_dark_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
            'remove_twitter_image' => ['nullable', 'boolean'],
        ];
    }
}
