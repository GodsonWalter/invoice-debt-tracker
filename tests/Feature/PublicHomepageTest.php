<?php

use App\Models\PlatformSetting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('guests can view the public homepage and its authentication links', function (): void {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('Create Invoices. Track Payments. Recover Debts Faster.')
        ->assertSee('Everything you need to manage invoices and debts')
        ->assertSee('Get started in four simple steps')
        ->assertSee('Customer stories')
        ->assertSee('Customer stories will appear here')
        ->assertSee('Frequently asked questions')
        ->assertSee('Revenue')
        ->assertSee('Outstanding debt')
        ->assertSee('Recent customer payments')
        ->assertSee('data-theme-toggle', false)
        ->assertSee('idt.theme', false)
        ->assertSee(route('login', [], false), false)
        ->assertSee(route('register', [], false), false);
});

test('mobile homepage navigation closes after selecting an in-page menu item', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="publicNavigation"', false)
        ->assertSee('window.innerWidth >= 992', false)
        ->assertSee('navigationCollapse.hide()', false);
});

test('authenticated users can access the public homepage without workspace data', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Open Dashboard')
        ->assertSee(route('dashboard', [], false), false)
        ->assertDontSee('Sign In')
        ->assertDontSee('Get Started Free');
});

test('the configured product name is used throughout the homepage sections', function (): void {
    PlatformSetting::factory()->create([
        'product_name' => 'LedgerPro',
        'product_title' => 'LedgerPro Invoice Suite',
        'tagline' => 'A clearer way to manage cash flow.',
    ]);

    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('LedgerPro workspace')
        ->assertSee('Trusted by teams using LedgerPro')
        ->assertSee('LedgerPro gives your team a shared place')
        ->assertSee('LedgerPro is for teams that invoice customers')
        ->assertSee('explore LedgerPro')
        ->assertSee('how LedgerPro fits into your invoicing')
        ->assertSee('What can I manage in LedgerPro?')
        ->assertSee('LedgerPro supports reminder schedules')
        ->assertSee('LedgerPro roadmap')
        ->assertDontSee('using IDT')
        ->assertDontSee('IDT gives your team')
        ->assertDontSee('IDT is for teams')
        ->assertDontSee('explore IDT')
        ->assertDontSee('how IDT fits')
        ->assertDontSee('manage in IDT');
});

test('homepage preview uses the visitor currency returned for their public ip', function (): void {
    Http::fake([
        'https://ipwho.is/8.8.8.8' => Http::response([
            'success' => true,
            'currency' => [
                'code' => 'EUR',
                'symbol' => '€',
            ],
        ]),
    ]);
    Cache::forget('homepage.currency.'.hash('sha256', '8.8.8.8'));

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('€1.28m')
        ->assertSee('€486k')
        ->assertSee('€120k')
        ->assertDontSee('₦1.28m');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://ipwho.is/8.8.8.8');
});

test('homepage preview falls back to usd for local private or failed lookups', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('$1.28m')
        ->assertDontSee('₦1.28m');

    Http::fake([
        'https://ipwho.is/8.8.4.4' => Http::response([], 503),
    ]);
    Cache::forget('homepage.currency.'.hash('sha256', '8.8.4.4'));

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('$1.28m')
        ->assertDontSee('₦1.28m');
});

test('published testimonials use the identity-first homepage card hierarchy', function (): void {
    $testimonial = Testimonial::factory()->published()->create([
        'display_name' => 'Ada Customer',
        'job_title' => 'Operations Lead',
        'business_name' => 'Ada Trading Co.',
        'content' => 'IDT gives our team a clear view of every invoice and payment.',
        'rating' => 5,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([
            'testimonial-author',
            'testimonial-avatar',
            $testimonial->display_name,
            $testimonial->job_title,
            $testimonial->business_name,
            '5 out of 5 stars',
            'testimonial-divider',
            $testimonial->content,
        ]);
});
