<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformSettingsRequest;
use App\Models\PlatformSettingAudit;
use App\Services\PlatformConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformConfigurationController extends Controller
{
    public function __construct(private readonly PlatformConfigurationService $configurationService) {}

    public function edit(): View
    {
        return view('platform.configuration.edit', [
            ...$this->configurationService->viewData(),
            'audits' => PlatformSettingAudit::query()
                ->with('actor')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(PlatformSettingsRequest $request): RedirectResponse
    {
        $files = [
            'logo' => $request->file('logo'),
            'dark_logo' => $request->file('dark_logo'),
            'favicon' => $request->file('favicon'),
            'og_image' => $request->file('og_image'),
            'twitter_image' => $request->file('twitter_image'),
        ];

        $this->configurationService->update($request->user(), $request->validated(), $files);

        return back()->with('success', 'Platform configuration updated successfully.');
    }
}
