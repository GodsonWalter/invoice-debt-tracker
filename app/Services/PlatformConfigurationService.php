<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\PlatformSettingAudit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlatformConfigurationService
{
    public const CACHE_KEY = 'platform.settings';

    /**
     * @var array<string, string>
     */
    private const IMAGE_FIELDS = [
        'logo' => 'logo_path',
        'dark_logo' => 'dark_logo_path',
        'favicon' => 'favicon_path',
        'og_image' => 'og_image_path',
        'twitter_image' => 'twitter_image_path',
    ];

    /**
     * @var array<int, string>
     */
    private const CONTENT_FIELDS = [
        'product_name',
        'product_title',
        'tagline',
        'login_title',
        'login_description',
        'default_page_title_suffix',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description',
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

    public function settings(): PlatformSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            $settings = new PlatformSetting;
            $settings->setRawAttributes($cached, true);
            $settings->exists = true;

            return $settings;
        }

        // Remove stale serialized model instances, including __PHP_Incomplete_Class values.
        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $settings = $this->loadOrCreate();
        Cache::forever(self::CACHE_KEY, $settings->getAttributes());

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $settings = $this->settings();

        return [
            'settings' => $settings,
            'logoUrl' => $this->assetUrl($settings->logo_path),
            'darkLogoUrl' => $this->assetUrl($settings->dark_logo_path),
            'faviconUrl' => $this->assetUrl($settings->favicon_path),
            'ogImageUrl' => $this->assetUrl($settings->og_image_path),
            'twitterImageUrl' => $this->assetUrl($settings->twitter_image_path),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, UploadedFile|null>  $files
     */
    public function update(User $actor, array $attributes, array $files = []): PlatformSetting
    {
        $newPaths = [];
        $oldPaths = [];

        try {
            foreach (self::IMAGE_FIELDS as $input => $column) {
                if (($files[$input] ?? null) instanceof UploadedFile) {
                    $newPaths[$column] = $files[$input]->store('platform-settings', 'public');
                }
            }

            $settings = DB::transaction(function () use ($actor, $attributes, $newPaths, &$oldPaths): PlatformSetting {
                $settings = PlatformSetting::query()->lockForUpdate()->first() ?? $this->newDefaultSettings();
                $before = $this->auditValues($settings);
                $updates = [];

                foreach (self::CONTENT_FIELDS as $field) {
                    if (array_key_exists($field, $attributes)) {
                        $updates[$field] = $attributes[$field];
                    }
                }

                foreach (self::IMAGE_FIELDS as $input => $column) {
                    if (array_key_exists($column, $newPaths)) {
                        $oldPaths[$column] = $settings->{$column};
                        $updates[$column] = $newPaths[$column];
                    } elseif ((bool) ($attributes['remove_'.$input] ?? false)) {
                        $oldPaths[$column] = $settings->{$column};
                        $updates[$column] = null;
                    }
                }

                $settings->forceFill($updates)->save();
                $after = $this->auditValues($settings);
                $changes = $this->diff($before, $after);

                if ($changes !== []) {
                    PlatformSettingAudit::query()->create([
                        'actor_user_id' => $actor->id,
                        'event' => 'platform_settings.updated',
                        'changes' => $changes,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }

                return $settings->refresh();
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) {
                $this->deletePath($path);
            }

            throw $exception;
        }

        foreach ($oldPaths as $path) {
            if ($path && ! in_array($path, $newPaths, true)) {
                $this->deletePath($path);
            }
        }

        $this->forgetCache();

        return $settings;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function assetUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function loadOrCreate(): PlatformSetting
    {
        return PlatformSetting::query()->first() ?? DB::transaction(function (): PlatformSetting {
            return PlatformSetting::query()->first() ?? $this->newDefaultSettings();
        });
    }

    private function newDefaultSettings(): PlatformSetting
    {
        $settings = new PlatformSetting;
        $settings->forceFill([
            'singleton_key' => 'global',
            'product_name' => config('app.name', 'IDT'),
            'product_title' => 'Invoice and Debt Tracker',
            'tagline' => 'Invoice clarity for growing businesses.',
            'login_title' => 'Welcome back',
            'login_description' => 'Sign in to your account',
            'default_page_title_suffix' => config('app.name', 'IDT'),
            'seo_title' => config('app.name', 'IDT').' | Invoice and debt management',
            'seo_description' => 'Create professional invoices, track payments, automate reminders, and manage customer debts from one secure workspace.',
            'og_title' => 'Create Invoices. Track Payments. Recover Debts Faster.',
            'og_description' => 'Create professional invoices, monitor payments, automate reminders, and manage customer debts from one secure workspace.',
            'twitter_title' => 'Create Invoices. Track Payments. Recover Debts Faster.',
            'twitter_description' => 'Create professional invoices, monitor payments, automate reminders, and manage customer debts from one secure workspace.',
            'footer_copyright' => 'Copyright '.now()->year.' '.config('app.name', 'IDT').'. All rights reserved.',
            'maintenance_banner_enabled' => false,
        ])->save();

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(PlatformSetting $settings): array
    {
        return collect([...self::CONTENT_FIELDS, ...array_values(self::IMAGE_FIELDS)])
            ->mapWithKeys(fn (string $field): array => [$field => $settings->{$field}])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = ['old' => $before[$field] ?? null, 'new' => $value];
            }
        }

        return $changes;
    }

    private function deletePath(?string $path): void
    {
        if ($path && str_starts_with($path, 'platform-settings/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
