<?php

use App\Models\ActivityLog;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TestimonialStatusNotification;
use App\Services\TestimonialService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: User, 1: User, 2: Workspace}
 */
function testimonialWorkspaceFixture(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $slug = 'testimonial-workspace-'.fake()->unique()->numerify('####');
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Testimonial Workspace',
        'slug' => $slug,
        'subdomain' => $slug,
        'invoice_prefix' => 'TES',
        'is_active' => true,
    ]);
    $workspace->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
    $workspace->users()->attach($admin->id, ['role' => 'admin', 'is_active' => true]);

    return [$admin, $owner, $workspace];
}

function testimonialWorkspaceUrl(string $routeName, Workspace $workspace, ?Testimonial $testimonial = null): string
{
    $parameters = $testimonial ? [$workspace, $testimonial] : $workspace;

    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($routeName, $parameters, false);
}

function makeTestimonial(Workspace $workspace, User $creator, string $status = Testimonial::STATUS_DRAFT): Testimonial
{
    $testimonial = new Testimonial;
    $testimonial->forceFill([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
        'display_name' => 'Ada Customer',
        'job_title' => 'Operations Lead',
        'business_name' => 'Ada Trading Co.',
        'content' => 'IDT makes our invoicing and payment follow-up much easier to manage.',
        'rating' => 5,
        'status' => $status,
        'consent_confirmed' => true,
        'consented_at' => now(),
        'consented_by' => $creator->id,
        'submitted_at' => in_array($status, [Testimonial::STATUS_PENDING_REVIEW, Testimonial::STATUS_APPROVED, Testimonial::STATUS_REJECTED, Testimonial::STATUS_PUBLISHED], true) ? now() : null,
        'reviewed_at' => in_array($status, [Testimonial::STATUS_APPROVED, Testimonial::STATUS_REJECTED, Testimonial::STATUS_PUBLISHED], true) ? now() : null,
        'published_at' => $status === Testimonial::STATUS_PUBLISHED ? now() : null,
    ])->save();

    return $testimonial->refresh();
}

test('workspace testimonial pages isolate records and allow members to preview them', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $otherOwner = User::factory()->create();
    $otherSlug = 'other-testimonial-workspace-'.fake()->unique()->numerify('####');
    $otherWorkspace = Workspace::create([
        'owner_id' => $otherOwner->id,
        'name' => 'Other Testimonial Workspace',
        'slug' => $otherSlug,
        'subdomain' => $otherSlug,
        'is_active' => true,
    ]);
    $workspaceTestimonial = makeTestimonial($workspace, $admin);
    $otherTestimonial = makeTestimonial($otherWorkspace, $otherOwner);
    $otherTestimonial->forceFill(['display_name' => 'Other Workspace Customer'])->save();

    $this->actingAs($admin)
        ->get(testimonialWorkspaceUrl('testimonials.index', $workspace))
        ->assertOk()
        ->assertSee($workspaceTestimonial->display_name)
        ->assertDontSee($otherTestimonial->display_name);

    $this->actingAs($admin)
        ->get(testimonialWorkspaceUrl('testimonials.show', $workspace, $workspaceTestimonial))
        ->assertOk()
        ->assertSee('IDT makes our invoicing');

    $this->actingAs($admin)
        ->get(testimonialWorkspaceUrl('testimonials.show', $workspace, $otherTestimonial))
        ->assertNotFound();
});

test('only workspace owners and admins can create or manage testimonials', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
    $payload = [
        'display_name' => 'Permitted Customer',
        'job_title' => 'Founder',
        'business_name' => 'Permitted Business',
        'content' => 'A sufficiently long customer testimonial for validation.',
        'rating' => 4,
        'consent_confirmed' => 1,
    ];

    $this->actingAs($admin)
        ->get(testimonialWorkspaceUrl('testimonials.index', $workspace))
        ->assertOk()
        ->assertSee('New testimonial');

    $this->actingAs($member)
        ->get(testimonialWorkspaceUrl('testimonials.index', $workspace))
        ->assertOk()
        ->assertDontSee('New testimonial');

    $this->actingAs($member)
        ->post(testimonialWorkspaceUrl('testimonials.store', $workspace), $payload)
        ->assertForbidden();

    expect(Testimonial::query()->count())->toBe(0)
        ->and($workspace->canBeManagedBy($admin))->toBeTrue();
});

test('creation requires consent, rejects unsafe image types, and never accepts moderation fields', function () {
    Storage::fake('public');
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $url = testimonialWorkspaceUrl('testimonials.store', $workspace);
    $payload = [
        'display_name' => 'Consent Customer',
        'job_title' => 'Director',
        'business_name' => 'Consent Business',
        'content' => 'This testimonial contains enough content to pass validation.',
        'rating' => 5,
        'status' => Testimonial::STATUS_PUBLISHED,
        'featured' => 1,
        'display_order' => 99,
    ];

    $this->actingAs($admin)
        ->post($url, $payload)
        ->assertSessionHasErrors('consent_confirmed');

    $payload['consent_confirmed'] = 1;
    $payload['image'] = UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml');
    $this->actingAs($admin)
        ->post($url, $payload)
        ->assertSessionHasErrors('image');

    $payload['image'] = UploadedFile::fake()->image('customer.png', 200, 200);
    $this->actingAs($admin)
        ->post($url, $payload)
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::query()->latest('id')->firstOrFail();
    expect($testimonial->status)->toBe(Testimonial::STATUS_DRAFT)
        ->and($testimonial->featured)->toBeFalse()
        ->and($testimonial->display_order)->toBe(0)
        ->and($testimonial->consented_by)->toBe($admin->id)
        ->and($testimonial->consented_at)->not->toBeNull();
    Storage::disk('public')->assertExists($testimonial->image_path);
});

test('testimonial content is limited to 500 characters on create and update', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $url = testimonialWorkspaceUrl('testimonials.store', $workspace);
    $payload = [
        'display_name' => 'Long Content Customer',
        'job_title' => 'Director',
        'business_name' => 'Long Content Business',
        'content' => str_repeat('A', 501),
        'rating' => 5,
        'consent_confirmed' => 1,
    ];

    $this->actingAs($admin)
        ->post($url, $payload)
        ->assertSessionHasErrors('content');

    $testimonial = makeTestimonial($workspace, $admin);

    $this->actingAs($admin)
        ->put(testimonialWorkspaceUrl('testimonials.update', $workspace, $testimonial), $payload)
        ->assertSessionHasErrors('content');
});

test('submission, approval, rejection, publication, unpublication, and correction transitions are protected', function () {
    Queue::fake();
    [$admin, $owner, $workspace] = testimonialWorkspaceFixture();
    $platformAdmin = User::factory()->create(['role' => 'admin']);
    $testimonial = makeTestimonial($workspace, $admin);

    $this->actingAs($admin)
        ->post(testimonialWorkspaceUrl('testimonials.submit', $workspace, $testimonial))
        ->assertRedirect();
    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_PENDING_REVIEW);
    expect(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'testimonial.submitted')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(testimonialWorkspaceUrl('testimonials.edit', $workspace, $testimonial))
        ->assertForbidden();

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.approve', $testimonial))
        ->assertRedirect();
    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_APPROVED);
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TestimonialStatusNotification && $job->notification->status === Testimonial::STATUS_APPROVED);
    expect(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'testimonial.approved')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->put(testimonialWorkspaceUrl('testimonials.update', $workspace, $testimonial), [
            'display_name' => 'Tampered Customer',
            'content' => 'This update must not be allowed after platform approval.',
            'rating' => 1,
            'consent_confirmed' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.publish', $testimonial))
        ->assertRedirect();
    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_PUBLISHED);
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TestimonialStatusNotification && $job->notification->status === Testimonial::STATUS_PUBLISHED);

    $this->actingAs($admin)
        ->delete(testimonialWorkspaceUrl('testimonials.destroy', $workspace, $testimonial))
        ->assertForbidden();

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.unpublish', $testimonial))
        ->assertRedirect();
    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_UNPUBLISHED);

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.return-to-draft', $testimonial), ['reason' => 'Please correct the customer role.'])
        ->assertRedirect();
    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_DRAFT)
        ->and($testimonial->rejection_reason)->toBe('Please correct the customer role.');
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TestimonialStatusNotification && $job->notification->status === 'returned_for_correction');
    expect($owner->id)->not->toBe($admin->id);
});

test('platform rejection requires a reason and notifies the workspace', function () {
    Queue::fake();
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $platformAdmin = User::factory()->create(['role' => 'admin']);
    $testimonial = makeTestimonial($workspace, $admin, Testimonial::STATUS_PENDING_REVIEW);

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.reject', $testimonial), ['rejection_reason' => 'No'])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($platformAdmin)
        ->post(route('platform.testimonials.reject', $testimonial), ['rejection_reason' => 'Please confirm the customer permission.'])
        ->assertRedirect();

    expect($testimonial->refresh()->status)->toBe(Testimonial::STATUS_REJECTED)
        ->and($testimonial->rejection_reason)->toBe('Please confirm the customer permission.');
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TestimonialStatusNotification && $job->notification->status === Testimonial::STATUS_REJECTED);
    expect(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'testimonial.rejected')->exists())->toBeTrue();
});

test('homepage only renders published consented testimonials and invalidates its cache', function () {
    Cache::spy();
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $published = makeTestimonial($workspace, $admin, Testimonial::STATUS_PUBLISHED);
    $draft = makeTestimonial($workspace, $admin, Testimonial::STATUS_DRAFT);
    $published->forceFill(['display_name' => 'Published Customer'])->save();
    $draft->forceFill(['display_name' => 'Draft Customer'])->save();
    $service = app(TestimonialService::class);

    $visible = $service->homepageTestimonials();
    expect($visible->pluck('id')->all())->toContain($published->id)
        ->not->toContain($draft->id);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($published->display_name)
        ->assertDontSee($draft->display_name);

    $service->unpublish($published, User::factory()->create(['role' => 'admin']));
    Cache::shouldHaveReceived('forget')->with('testimonials.homepage');

    expect(Testimonial::query()->whereKey($published->id)->value('status'))->toBe(Testimonial::STATUS_UNPUBLISHED);
});

test('homepage testimonials recover from legacy model cache entries', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $published = makeTestimonial($workspace, $admin, Testimonial::STATUS_PUBLISHED);

    Cache::put('testimonials.homepage', $published->newCollection([$published]), now()->addMinutes(10));

    $visible = app(TestimonialService::class)->homepageTestimonials();

    expect($visible)->toBeInstanceOf(Collection::class)
        ->and($visible->pluck('id')->all())->toBe([$published->id]);

    Cache::forget('testimonials.homepage');
});

test('platform moderation is unavailable to non-platform users', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();

    $this->actingAs($admin)
        ->get(route('platform.testimonials.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->get(route('platform.testimonials.index'))
        ->assertForbidden();

    expect($workspace->testimonials()->count())->toBe(0);
});

test('authorized platform moderators can filter and preview the moderation queue', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $platformAdmin = User::factory()->create(['role' => 'admin']);
    $testimonial = makeTestimonial($workspace, $admin, Testimonial::STATUS_PENDING_REVIEW);

    $this->actingAs($platformAdmin)
        ->get(route('platform.testimonials.index', [
            'workspace_id' => $workspace->id,
            'status' => Testimonial::STATUS_PENDING_REVIEW,
            'rating' => 5,
            'featured' => 0,
        ]))
        ->assertOk()
        ->assertSee($testimonial->display_name)
        ->assertSee($workspace->name)
        ->assertSee('Pending Review');

    $this->actingAs($platformAdmin)
        ->get(route('platform.testimonials.show', $testimonial))
        ->assertOk()
        ->assertSee('Moderation decision')
        ->assertSee($testimonial->content);
});

test('invalid transitions and missing consent are rejected by the service', function () {
    [$admin, , $workspace] = testimonialWorkspaceFixture();
    $platformAdmin = User::factory()->create(['role' => 'admin']);
    $service = app(TestimonialService::class);
    $draft = makeTestimonial($workspace, $admin);
    $draft->forceFill(['consent_confirmed' => false, 'consented_at' => null, 'consented_by' => null])->save();

    expect(fn () => $service->submit($workspace, $admin, $draft))
        ->toThrow(ValidationException::class);

    $pending = makeTestimonial($workspace, $admin, Testimonial::STATUS_PENDING_REVIEW);
    expect(fn () => $service->publish($pending, $platformAdmin))
        ->toThrow(ValidationException::class);
});
