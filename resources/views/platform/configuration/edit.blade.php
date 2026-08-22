@extends('layouts.app')

@php($settings = $platformSettings['settings'])

@section('page_title', 'Platform Configuration')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <p class="text-muted mb-1">Platform administration</p>
                <h1 class="h3 fw-bold mb-1">Platform Configuration</h1>
                <p class="text-muted mb-0">Manage the product identity, public metadata, support links, and shared branding used across {{ $settings->product_name }}.</p>
            </div>
            <span class="badge text-bg-primary align-self-center"><i class="fa-solid fa-shield-halved me-1"></i> Owner/Admin only</span>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('platform.configuration.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-1">Product identity</h2>
                            <p class="text-muted small mb-4">These values are used in the navigation, authentication screens, browser titles, and system messages.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="product-name" class="form-label">Product name</label>
                                    <input id="product-name" name="product_name" value="{{ old('product_name', $settings->product_name) }}" class="form-control @error('product_name') is-invalid @enderror" required maxlength="120">
                                    @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="product-title" class="form-label">Product title</label>
                                    <input id="product-title" name="product_title" value="{{ old('product_title', $settings->product_title) }}" class="form-control @error('product_title') is-invalid @enderror" required maxlength="160">
                                    @error('product_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label for="tagline" class="form-label">Tagline</label>
                                    <input id="tagline" name="tagline" value="{{ old('tagline', $settings->tagline) }}" class="form-control @error('tagline') is-invalid @enderror" maxlength="255">
                                    @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="login-title" class="form-label">Login title</label>
                                    <input id="login-title" name="login_title" value="{{ old('login_title', $settings->login_title) }}" class="form-control @error('login_title') is-invalid @enderror" maxlength="160">
                                    @error('login_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="title-suffix" class="form-label">Default title suffix</label>
                                    <input id="title-suffix" name="default_page_title_suffix" value="{{ old('default_page_title_suffix', $settings->default_page_title_suffix) }}" class="form-control @error('default_page_title_suffix') is-invalid @enderror" maxlength="100">
                                    @error('default_page_title_suffix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label for="login-description" class="form-label">Login description</label>
                                    <textarea id="login-description" name="login_description" rows="2" class="form-control @error('login_description') is-invalid @enderror" maxlength="500">{{ old('login_description', $settings->login_description) }}</textarea>
                                    @error('login_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-1">Brand assets</h2>
                            <p class="text-muted small mb-4">Upload safe raster assets. SVG is intentionally not accepted to prevent active content from being rendered.</p>
                            <div class="row g-3">
                                @foreach ([
                                    'logo' => ['Logo', 'logo_path', 'logo', 'Upload the primary product logo.'],
                                    'dark_logo' => ['Dark logo', 'dark_logo_path', 'dark_logo', 'Optional logo for dark backgrounds.'],
                                    'favicon' => ['Favicon', 'favicon_path', 'favicon', 'PNG, ICO, or WebP; keep this square.'],
                                ] as $field => [$label, $column, $input, $help])
                                    <div class="col-md-4">
                                        <label for="{{ $input }}" class="form-label">{{ $label }}</label>
                                        @if ($platformSettings[$field === 'logo' ? 'logoUrl' : ($field === 'dark_logo' ? 'darkLogoUrl' : 'faviconUrl')])
                                            <div class="bg-light border rounded p-2 mb-2 text-center"><img src="{{ $platformSettings[$field === 'logo' ? 'logoUrl' : ($field === 'dark_logo' ? 'darkLogoUrl' : 'faviconUrl')] }}" alt="Current {{ strtolower($label) }}" style="max-width:100%;height:54px;object-fit:contain"></div>
                                        @endif
                                        <input id="{{ $input }}" type="file" name="{{ $input }}" class="form-control @error($input) is-invalid @enderror" accept="{{ $input === 'favicon' ? '.png,.ico,.webp' : 'image/*' }}">
                                        <div class="form-text">{{ $help }}</div>
                                        @error($input)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if ($settings->{$column})
                                            <div class="form-check mt-2"><input id="remove-{{ $input }}" type="checkbox" name="remove_{{ $input }}" value="1" class="form-check-input"><label for="remove-{{ $input }}" class="form-check-label small">Remove current asset</label></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-1">SEO and social sharing</h2>
                            <p class="text-muted small mb-4">Page-level sections can override these defaults when needed.</p>
                            <div class="row g-3">
                                @foreach ([
                                    'seo_title' => 'SEO title', 'og_title' => 'Open Graph title', 'twitter_title' => 'Twitter title',
                                ] as $field => $label)
                                    <div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings->{$field}) }}" class="form-control @error($field) is-invalid @enderror" maxlength="160">@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                @endforeach
                                @foreach ([
                                    'seo_description' => 'SEO description', 'og_description' => 'Open Graph description', 'twitter_description' => 'Twitter description',
                                ] as $field => $label)
                                    <div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="3" class="form-control @error($field) is-invalid @enderror" maxlength="320">{{ old($field, $settings->{$field}) }}</textarea>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                @endforeach
                                <div class="col-md-4"><label for="seo-keywords" class="form-label">SEO keywords</label><textarea id="seo-keywords" name="seo_keywords" rows="3" class="form-control @error('seo_keywords') is-invalid @enderror" maxlength="500">{{ old('seo_keywords', $settings->seo_keywords) }}</textarea>@error('seo_keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                @foreach ([
                                    'og_image' => ['Open Graph image', 'ogImageUrl'], 'twitter_image' => ['Twitter image', 'twitterImageUrl'],
                                ] as $input => [$label, $urlKey])
                                    <div class="col-md-4">
                                        <label for="{{ $input }}" class="form-label">{{ $label }}</label>
                                        @if ($platformSettings[$urlKey])
                                            <div class="bg-light border rounded p-2 mb-2 text-center"><img src="{{ $platformSettings[$urlKey] }}" alt="Current {{ strtolower($label) }}" style="max-width:100%;height:54px;object-fit:contain"></div>
                                        @endif
                                        <input id="{{ $input }}" type="file" name="{{ $input }}" class="form-control @error($input) is-invalid @enderror" accept="image/*">
                                        <div class="form-text">JPG, PNG, WebP, or GIF up to 2 MB.</div>
                                        @error($input)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if ($settings->{str_replace('og_image', 'og_image_path', str_replace('twitter_image', 'twitter_image_path', $input))})
                                            <div class="form-check mt-2"><input id="remove-{{ $input }}" type="checkbox" name="remove_{{ $input }}" value="1" class="form-check-input"><label for="remove-{{ $input }}" class="form-check-label small">Remove current asset</label></div>
                                        @endif
                                    </div>
                                @endforeach
                                <div class="col-md-6"><label for="canonical-url" class="form-label">Canonical URL</label><input id="canonical-url" type="url" name="canonical_url" value="{{ old('canonical_url', $settings->canonical_url) }}" class="form-control @error('canonical_url') is-invalid @enderror" maxlength="2048">@error('canonical_url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-1">Support and legal links</h2>
                            <p class="text-muted small mb-4">These links are available to shared layouts and system communications.</p>
                            <div class="row g-3">
                                <div class="col-md-4"><label for="support-email" class="form-label">Support email</label><input id="support-email" type="email" name="support_email" value="{{ old('support_email', $settings->support_email) }}" class="form-control @error('support_email') is-invalid @enderror">@error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="support-phone" class="form-label">Support phone</label><input id="support-phone" name="support_phone" value="{{ old('support_phone', $settings->support_phone) }}" class="form-control @error('support_phone') is-invalid @enderror">@error('support_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="support-url" class="form-label">Support URL</label><input id="support-url" type="url" name="support_url" value="{{ old('support_url', $settings->support_url) }}" class="form-control @error('support_url') is-invalid @enderror">@error('support_url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label for="privacy-url" class="form-label">Privacy policy URL</label><input id="privacy-url" type="url" name="privacy_url" value="{{ old('privacy_url', $settings->privacy_url) }}" class="form-control @error('privacy_url') is-invalid @enderror">@error('privacy_url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label for="terms-url" class="form-label">Terms URL</label><input id="terms-url" type="url" name="terms_url" value="{{ old('terms_url', $settings->terms_url) }}" class="form-control @error('terms_url') is-invalid @enderror">@error('terms_url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="footer-copyright" class="form-label">Footer copyright</label><input id="footer-copyright" name="footer_copyright" value="{{ old('footer_copyright', $settings->footer_copyright) }}" class="form-control @error('footer_copyright') is-invalid @enderror" maxlength="255">@error('footer_copyright')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12">
                                    <label class="form-label">Social links</label>
                                    <div class="row g-2">
                                        @foreach (['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X'] as $socialKey => $socialLabel)
                                            <div class="col-md-6"><label for="social-{{ $socialKey }}" class="visually-hidden">{{ $socialLabel }} URL</label><input id="social-{{ $socialKey }}" type="url" name="social_links[{{ $socialKey }}]" value="{{ old('social_links.'.$socialKey, $settings->social_links[$socialKey] ?? '') }}" class="form-control @error('social_links.'.$socialKey) is-invalid @enderror" placeholder="{{ $socialLabel }} URL">@error('social_links.'.$socialKey)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-1">Maintenance notice</h2>
                            <p class="text-muted small mb-4">Display a non-blocking notice in shared application layouts.</p>
                            <div class="form-check mb-3"><input id="maintenance-enabled" type="hidden" name="maintenance_banner_enabled" value="0"><input type="checkbox" id="maintenance-enabled-check" name="maintenance_banner_enabled" value="1" class="form-check-input" @checked(old('maintenance_banner_enabled', $settings->maintenance_banner_enabled))><label for="maintenance-enabled-check" class="form-check-label">Show maintenance notice</label></div>
                            <label for="maintenance-text" class="form-label">Notice text</label>
                            <textarea id="maintenance-text" name="maintenance_banner_text" rows="2" class="form-control @error('maintenance_banner_text') is-invalid @enderror" maxlength="500">{{ old('maintenance_banner_text', $settings->maintenance_banner_text) }}</textarea>
                            @error('maintenance_banner_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save configuration</button>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-3">Current branding</h2>
                            <div class="border rounded-3 p-4 text-center bg-light">
                                @if ($platformSettings['logoUrl'])<img src="{{ $platformSettings['logoUrl'] }}" alt="{{ $settings->product_name }} logo" style="max-width:100%;height:72px;object-fit:contain">@else<span class="h2 fw-bold text-primary">{{ $settings->product_name }}</span>@endif
                                <div class="fw-semibold mt-3">{{ $settings->product_title }}</div>
                                <div class="small text-muted">{{ $settings->tagline }}</div>
                            </div>
                            <p class="small text-muted mt-3 mb-0">Workspace business logos remain separate and continue to be used on workspace invoices and customer-facing documents.</p>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-bold mb-3">Recent configuration audit</h2>
                            @forelse ($audits as $audit)
                                <div class="border-bottom pb-3 mb-3"><div class="small fw-semibold">{{ str($audit->event)->replace('_', ' ')->title() }}</div><div class="small text-muted">{{ $audit->actor?->name ?? 'System' }} · {{ $audit->created_at->format('M j, Y H:i') }}</div><div class="small text-muted">{{ count($audit->changes ?? []) }} field(s) changed</div></div>
                            @empty
                                <p class="small text-muted mb-0">No configuration changes have been audited yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
