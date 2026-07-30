<?php

use App\Data\ReportFilters;
use App\Jobs\GenerateReportExport;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\Workspace;
use App\Report\ReportExportService;
use App\Report\ReportService;
use App\ReportType;
use App\Services\UserAccountService;
use App\WorkspaceDashboardPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 7, 29, 15, 30, 0, 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Workspace, 2: Client, 3: Invoice}
 */
function createReportsFixture(string $key = 'reports'): array
{
    $user = User::factory()->create();
    $currency = Currency::create([
        'code' => strtoupper(substr($key, 0, 2)).fake()->unique()->randomLetter(),
        'symbol' => '$',
        'name' => ucfirst($key).' currency',
        'is_active' => true,
    ]);
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => ucfirst($key).' workspace',
        'slug' => $key.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $key.'-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => ucfirst($key).' customer',
        'email' => $key.'@example.test',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
        'issue_date' => '2026-07-10',
        'due_date' => '2026-07-20',
        'status' => Invoice::STATUS_OVERDUE,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ]);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'amount' => 250,
        'payment_date' => '2026-07-11',
        'payment_method' => 'Bank transfer',
    ]);

    return [$user, $workspace, $client, $invoice];
}

function reportsUrl(Workspace $workspace, string $report = 'revenue', array $query = []): string
{
    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route('reports.index', ['report' => $report], false);

    return $query ? $url.'?'.http_build_query($query) : $url;
}

test('all report types render from the active workspace', function (string $report, string $label): void {
    [$user, $workspace] = array_values(array_slice(createReportsFixture('render-'.$report), 0, 2));

    $this->actingAs($user)
        ->get(reportsUrl($workspace, $report))
        ->assertOk()
        ->assertSee($label);
})->with([
    'revenue' => [ReportType::REVENUE->value, 'Revenue Report'],
    'payments' => [ReportType::PAYMENTS->value, 'Payments Report'],
    'outstanding' => [ReportType::OUTSTANDING->value, 'Outstanding Debt'],
    'overdue' => [ReportType::OVERDUE->value, 'Overdue Invoices'],
    'customers' => [ReportType::CUSTOMERS->value, 'Customer Balances'],
    'reminders' => [ReportType::REMINDERS->value, 'Reminder Activity'],
    'invoices' => [ReportType::INVOICES->value, 'Invoice Report'],
]);

test('outstanding report includes workspace-scoped aging buckets', function (): void {
    [$user, $workspace] = array_values(array_slice(createReportsFixture('aging-report'), 0, 2));

    $report = app(ReportService::class)->build(
        $workspace,
        new ReportFilters(
            type: ReportType::OUTSTANDING,
            period: WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::THIS_MONTH),
        ),
    );

    expect($report['summary']['total'])->toBe(750.0)
        ->and($report['breakdown']['aging'])->toHaveCount(1)
        ->and($report['breakdown']['aging'][0]['count'])->toBe(1);
});

test('reports use business dates, filters, and preserve tenant isolation', function (): void {
    [$user, $workspace, $client, $invoice] = createReportsFixture('primary-report');
    [, $otherWorkspace, $otherClient, $otherInvoice] = createReportsFixture('other-report');

    Payment::create([
        'workspace_id' => $otherWorkspace->id,
        'invoice_id' => $otherInvoice->id,
        'amount' => 900,
        'payment_date' => '2026-07-11',
    ]);

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'payments', [
            'period' => WorkspaceDashboardPeriod::CUSTOM,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-29',
            'customer_id' => $client->id,
            'amount_min' => 200,
        ]))
        ->assertOk()
        ->assertSee($invoice->invoice_number)
        ->assertDontSee($otherInvoice->invoice_number)
        ->assertSee('250.00');

    $report = app(ReportService::class)->build(
        $workspace,
        new ReportFilters(
            type: ReportType::REVENUE,
            period: WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::THIS_MONTH),
        ),
    );

    expect($report['summary']['total'])->toBe(250.0)
        ->and($report['rows'])->toHaveCount(1);
});

test('report filters reject invalid dates and cross-workspace customers', function (): void {
    [$user, $workspace] = array_values(array_slice(createReportsFixture('validation-report'), 0, 2));
    [, $otherWorkspace, $otherClient] = createReportsFixture('validation-other');

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'revenue', ['period' => 'invalid']))
        ->assertSessionHasErrors('period');

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'revenue', ['period' => 'custom']))
        ->assertSessionHasErrors('start_date');

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'revenue', [
            'period' => 'custom',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-10',
        ]))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'revenue', ['customer_id' => $otherClient->id]))
        ->assertSessionHasErrors('customer_id');
});

test('report reminder lifecycle uses sent timestamp for successful logs and created timestamp for failures', function (): void {
    [$user, $workspace, $client, $invoice] = createReportsFixture('reminder-report');
    $schedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Report schedule',
        'days_offset' => 1,
        'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
        'is_active' => true,
    ]);
    $sent = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_SENT,
        'sent_at' => '2026-07-12 10:00:00',
    ]);
    $sent->forceFill(['created_at' => '2026-06-30 10:00:00'])->saveQuietly();
    $failedSchedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Failed report schedule',
        'days_offset' => 2,
        'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
        'is_active' => true,
    ]);
    $failed = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $failedSchedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_FAILED,
        'error_message' => 'Failed',
    ]);
    $failed->forceFill(['created_at' => '2026-07-13 10:00:00'])->saveQuietly();

    $this->actingAs($user)
        ->get(reportsUrl($workspace, 'reminders', ['period' => 'this_month']))
        ->assertOk()
        ->assertSee('Sent')
        ->assertSee('Failed');

    $report = app(ReportService::class)->build(
        $workspace,
        new ReportFilters(
            type: ReportType::REMINDERS,
            period: WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::THIS_MONTH),
        ),
    );

    expect($report['summary']['sent'])->toBe(1)
        ->and($report['summary']['failed'])->toBe(1);
});

test('report exports are workspace scoped in csv, xlsx, and pdf formats', function (string $format, string $contentType): void {
    [$user, $workspace, , $invoice] = createReportsFixture('export-report');
    [, $otherWorkspace, , $otherInvoice] = createReportsFixture('export-other');

    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route('reports.export', [
        'report' => 'payments',
        'format' => $format,
    ], false);

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($contentType);

    if ($format === 'csv') {
        $csv = $response->streamedContent();

        expect($csv)->toContain($invoice->invoice_number)->not->toContain($otherInvoice->invoice_number);
    }

    expect($otherWorkspace->id)->not->toBe($workspace->id);
})->with([
    'csv' => ['csv', 'text/csv'],
    'xlsx' => ['xlsx', 'spreadsheetml.sheet'],
    'pdf' => ['pdf', 'application/pdf'],
]);

test('csv exports neutralize spreadsheet formulas in user-controlled text', function (): void {
    [$user, $workspace, $client, $invoice] = createReportsFixture('formula-report');
    $client->update(['name' => '=HYPERLINK("https://evil.test","Open")']);
    $invoice->update(['invoice_number' => '+SUM(1,1)']);

    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route('reports.export', [
        'report' => 'payments',
        'format' => 'csv',
    ], false);

    $csv = $this->actingAs($user)->get($url)->streamedContent();

    expect($csv)
        ->toContain("'=HYPERLINK(")
        ->toContain("'+SUM(1,1)")
        ->not->toContain(',=HYPERLINK(')
        ->not->toContain(',+SUM(');
});

test('queued exports are recorded and dispatched for later download', function (): void {
    Queue::fake();
    [$user, $workspace] = array_values(array_slice(createReportsFixture('queued-report'), 0, 2));

    $this->actingAs($user)
        ->post('http://'.$workspace->subdomain.'.'.config('app.base_domain').route('reports.export.queue', [
            'report' => 'payments',
            'format' => 'xlsx',
        ], false), [
            'period' => WorkspaceDashboardPeriod::THIS_MONTH,
        ])
        ->assertRedirect(route('reports.index', ['report' => 'payments']));

    $export = ReportExport::query()->firstOrFail();

    expect($export->workspace_id)->toBe($workspace->id)
        ->and($export->report_type)->toBe('payments')
        ->and($export->format)->toBe('xlsx')
        ->and($export->status)->toBe(ReportExport::STATUS_PENDING);

    Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job): bool => $job->reportExportId === $export->id);
});

test('queued export jobs write a workspace-scoped file and mark it complete', function (): void {
    Storage::fake('local');
    [$user, $workspace] = array_values(array_slice(createReportsFixture('job-report'), 0, 2));
    $export = ReportExport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'report_type' => ReportType::PAYMENTS->value,
        'format' => 'xlsx',
        'filters' => [
            'report' => ReportType::PAYMENTS->value,
            'period' => WorkspaceDashboardPeriod::THIS_MONTH,
        ],
        'status' => ReportExport::STATUS_PENDING,
        'path' => 'reports/'.$workspace->id.'/job.xlsx',
    ]);

    (new GenerateReportExport($export->id))->handle(app(ReportExportService::class));
    $export->refresh();

    expect($export->status)->toBe(ReportExport::STATUS_COMPLETED)
        ->and(Storage::disk('local')->exists($export->path))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($export->path)))->toBeTrue()
        ->and($zip->locateName('xl/worksheets/sheet1.xml'))->not->toBeFalse();
    $zip->close();
});

test('queued export jobs reject a requester whose workspace membership is deactivated', function (): void {
    [$user, $workspace] = array_values(array_slice(createReportsFixture('inactive-job-report'), 0, 2));
    $workspace->users()->updateExistingPivot($user->id, ['is_active' => false]);
    $export = ReportExport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'report_type' => ReportType::PAYMENTS->value,
        'format' => 'csv',
        'filters' => [
            'report' => ReportType::PAYMENTS->value,
            'period' => WorkspaceDashboardPeriod::THIS_MONTH,
        ],
        'status' => ReportExport::STATUS_PENDING,
    ]);

    (new GenerateReportExport($export->id))->handle(app(ReportExportService::class));

    expect($export->refresh())
        ->status->toBe(ReportExport::STATUS_FAILED)
        ->error_message->toBe('The export requester is no longer authorized for this workspace.');
});

test('queued export jobs reject a requester whose account is soft deleted', function (): void {
    [$owner, $workspace] = array_values(array_slice(createReportsFixture('deleted-requester-report'), 0, 2));
    $requester = User::factory()->create();
    $workspace->users()->attach($requester->id, ['role' => 'admin', 'is_active' => true]);
    $export = ReportExport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $requester->id,
        'report_type' => ReportType::PAYMENTS->value,
        'format' => 'csv',
        'filters' => [
            'report' => ReportType::PAYMENTS->value,
            'period' => WorkspaceDashboardPeriod::THIS_MONTH,
        ],
        'status' => ReportExport::STATUS_PENDING,
    ]);

    app(UserAccountService::class)->softDelete($requester);
    (new GenerateReportExport($export->id))->handle(app(ReportExportService::class));

    expect($export->refresh())
        ->status->toBe(ReportExport::STATUS_FAILED)
        ->error_message->toBe('The export requester is no longer authorized for this workspace.');
});

test('members and users outside the active workspace cannot access reports', function (): void {
    [$owner, $workspace] = array_values(array_slice(createReportsFixture('authorized-report'), 0, 2));
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
    $outsider = User::factory()->create();

    $this->actingAs($member)
        ->get(reportsUrl($workspace))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($outsider)
        ->get(reportsUrl($workspace))
        ->assertRedirect(route('dashboard'));

    $workspace->delete();
    $this->actingAs($owner)
        ->get(reportsUrl($workspace))
        ->assertRedirect();
});
