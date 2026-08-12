<?php

use App\Models\PlatformSetting;
use App\Models\PlatformSettingAudit;
use App\Models\User;
use App\Services\PlatformConfigurationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function platformConfigurationUrl(): string
{
    return 'http://'.trim((string) config('app.base_domain'), '"').route('platform.configuration.edit', [], false);
}

test('platform owners and admins can view configuration while other roles are denied', function (): void {
    foreach (['owner', 'admin'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(platformConfigurationUrl())
            ->assertOk()
            ->assertSee('Platform Configuration');
    }

    $this->actingAs(User::factory()->create(['role' => 'manager']))
        ->get(platformConfigurationUrl())
        ->assertForbidden();
});

test('platform configuration is not available on a workspace host', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('http://workspace.'.trim((string) config('app.base_domain'), '"').route('platform.configuration.edit', [], false))
        ->assertNotFound();
});

test('authorized platform users can update identity, seo, notice, and image settings', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(PlatformConfigurationService::class);
    $service->settings();

    $this->actingAs($admin)
        ->put(platformConfigurationUrl(), [
            'product_name' => 'LedgerPro',
            'product_title' => 'LedgerPro Invoice Suite',
            'tagline' => 'Clearer cash flow for every team.',
            'seo_title' => 'LedgerPro | Smart invoicing',
            'seo_description' => 'Manage invoices and payments with LedgerPro.',
            'og_title' => 'LedgerPro for growing teams',
            'og_description' => 'A simpler way to manage invoices.',
            'twitter_title' => 'LedgerPro for growing teams',
            'twitter_description' => 'A simpler way to manage invoices.',
            'maintenance_banner_enabled' => '1',
            'maintenance_banner_text' => 'Scheduled maintenance tonight.',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = PlatformSetting::query()->firstOrFail();

    expect($settings->product_name)->toBe('LedgerPro')
        ->and($settings->maintenance_banner_enabled)->toBeTrue()
        ->and($settings->logo_path)->toStartWith('platform-settings/');

    Storage::disk('public')->assertExists($settings->logo_path);
    expect(PlatformSettingAudit::query()->where('event', 'platform_settings.updated')->count())->toBe(1)
        ->and($service->settings()->product_name)->toBe('LedgerPro');
});

test('configuration updates reject unsafe images and cannot mass assign protected paths', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = PlatformSetting::factory()->create();

    $this->actingAs($admin)
        ->put(platformConfigurationUrl(), [
            'product_name' => 'Changed Name',
            'product_title' => $settings->product_title,
            'maintenance_banner_enabled' => '0',
            'logo' => UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo');

    $this->actingAs($admin)
        ->put(platformConfigurationUrl(), [
            'product_name' => 'Changed Name',
            'product_title' => $settings->product_title,
            'maintenance_banner_enabled' => '0',
            'logo_path' => 'platform-settings/attacker.png',
            'id' => 99999,
        ])
        ->assertRedirect();

    expect($settings->fresh()->product_name)->toBe('Changed Name')
        ->and($settings->fresh()->logo_path)->toBeNull()
        ->and(PlatformSetting::query()->count())->toBe(1);
});

test('replacing and removing a logo cleans up the old public asset', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $oldPath = 'platform-settings/old.png';
    Storage::disk('public')->put($oldPath, 'old');
    PlatformSetting::factory()->create(['logo_path' => $oldPath]);

    $this->actingAs($admin)->put(platformConfigurationUrl(), [
        'product_name' => 'IDT',
        'product_title' => 'Invoice and Debt Tracker',
        'maintenance_banner_enabled' => '0',
        'logo' => UploadedFile::fake()->image('new.png'),
    ])->assertRedirect();

    Storage::disk('public')->assertMissing($oldPath);
    $newPath = PlatformSetting::query()->firstOrFail()->logo_path;
    Storage::disk('public')->assertExists($newPath);

    $this->actingAs($admin)->put(platformConfigurationUrl(), [
        'product_name' => 'IDT',
        'product_title' => 'Invoice and Debt Tracker',
        'maintenance_banner_enabled' => '0',
        'remove_logo' => '1',
    ])->assertRedirect();

    Storage::disk('public')->assertMissing($newPath);
    expect(PlatformSetting::query()->firstOrFail()->logo_path)->toBeNull();
});

test('public metadata and branding use the persisted platform configuration', function (): void {
    Storage::fake('public');
    $logoPath = 'platform-settings/public-logo.png';
    Storage::disk('public')->put($logoPath, 'logo');
    PlatformSetting::factory()->create([
        'product_name' => 'LedgerPro',
        'product_title' => 'LedgerPro Invoice Suite',
        'seo_title' => 'LedgerPro SEO title',
        'seo_description' => 'LedgerPro SEO description',
        'logo_path' => $logoPath,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('LedgerPro SEO title')
        ->assertSee('LedgerPro SEO description')
        ->assertSee('LedgerPro Invoice Suite')
        ->assertSee(Storage::disk('public')->url($logoPath), false);
});

test('platform settings cache is invalidated after an update', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(PlatformConfigurationService::class);
    $service->settings();

    $this->actingAs($admin)->put(platformConfigurationUrl(), [
        'product_name' => 'Fresh Name',
        'product_title' => 'Fresh Title',
        'maintenance_banner_enabled' => '0',
    ])->assertRedirect();

    expect($service->settings()->product_name)->toBe('Fresh Name');
});

test('platform settings recover from a legacy serialized model cache value', function (): void {
    $settings = PlatformSetting::factory()->create(['product_name' => 'Cached Product']);
    $service = app(PlatformConfigurationService::class);

    Cache::forever(PlatformConfigurationService::CACHE_KEY, $settings);

    expect($service->settings()->product_name)->toBe('Cached Product')
        ->and(Cache::get(PlatformConfigurationService::CACHE_KEY))->toBeArray();
});
