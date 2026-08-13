<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\EmailTemplate;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceDefaultsService;

test('regular workspace creation provisions the standard reminders and email templates', function (): void {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workspace.store'), [
            'name' => 'Provisioned Workspace',
            'slug' => 'provisioned-workspace',
            'subdomain' => 'provisioned-workspace',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('workspace.index'));

    $workspace = Workspace::query()->where('slug', 'provisioned-workspace')->firstOrFail();

    expect($workspace->reminderSchedules()->count())->toBe(4)
        ->and($workspace->emailTemplates()->count())->toBe(count(EmailTemplate::TYPES))
        ->and($workspace->reminderSchedules()->where('direction', ReminderSchedule::DIRECTION_BEFORE_DUE)->where('days_offset', 0)->exists())->toBeTrue()
        ->and($workspace->reminderSchedules()->where('direction', ReminderSchedule::DIRECTION_BEFORE_DUE)->where('days_offset', 0)->value('include_invoice_pdf'))->toBeTrue()
        ->and($workspace->reminderSchedules()->where('direction', ReminderSchedule::DIRECTION_AFTER_DUE)->where('include_invoice_pdf', true)->count())->toBe(2)
        ->and($workspace->emailTemplates()->where('type', EmailTemplate::TYPE_DUE_TODAY)->where('is_default', true)->exists())->toBeTrue();
});

test('platform workspace creation provisions the same standard defaults', function (): void {
    $platformManager = User::factory()->create(['role' => 'manager']);
    $workspaceOwner = User::factory()->create(['role' => 'user']);

    $this->actingAs($platformManager)
        ->post('http://'.config('app.base_domain').route('platform.workspaces.store', [], false), [
            'name' => 'Platform Provisioned Workspace',
            'slug' => 'platform-provisioned-workspace',
            'subdomain' => 'platform-provisioned-workspace',
            'owner_id' => $workspaceOwner->id,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('http://'.config('app.base_domain').route('platform.workspaces.index', [], false));

    $workspace = Workspace::query()->where('slug', 'platform-provisioned-workspace')->firstOrFail();

    expect($workspace->reminderSchedules()->count())->toBe(4)
        ->and($workspace->emailTemplates()->count())->toBe(3)
        ->and($workspace->users()->whereKey($workspaceOwner->id)->wherePivot('role', 'owner')->exists())->toBeTrue();
});

test('workspace default provisioning is idempotent and preserves custom defaults', function (): void {
    $workspace = Workspace::factory()->create();
    $defaults = app(WorkspaceDefaultsService::class);

    $defaults->provision($workspace);

    $workspace->reminderSchedules()
        ->where('direction', ReminderSchedule::DIRECTION_BEFORE_DUE)
        ->where('days_offset', 3)
        ->firstOrFail()
        ->update(['name' => 'Custom Reminder Name']);
    $workspace->emailTemplates()
        ->where('type', EmailTemplate::TYPE_BEFORE_DUE)
        ->firstOrFail()
        ->update(['subject' => 'Custom Subject']);

    $defaults->provision($workspace);

    expect($workspace->reminderSchedules()->count())->toBe(4)
        ->and($workspace->emailTemplates()->count())->toBe(3)
        ->and($workspace->reminderSchedules()->where('name', 'Custom Reminder Name')->exists())->toBeTrue()
        ->and($workspace->emailTemplates()->where('subject', 'Custom Subject')->exists())->toBeTrue();
});
