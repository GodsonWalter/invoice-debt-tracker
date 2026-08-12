<?php

namespace App\Models;

use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'product_name',
        'product_title',
        'tagline',
        'logo_path',
        'dark_logo_path',
        'favicon_path',
        'login_title',
        'login_description',
        'default_page_title_suffix',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_title',
        'og_description',
        'og_image_path',
        'twitter_title',
        'twitter_description',
        'twitter_image_path',
        'canonical_url',
        'support_email',
        'support_phone',
        'support_url',
        'privacy_url',
        'terms_url',
        'footer_copyright',
        'maintenance_banner_text',
        'maintenance_banner_enabled',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_banner_enabled' => 'boolean',
            'social_links' => 'array',
        ];
    }
}
