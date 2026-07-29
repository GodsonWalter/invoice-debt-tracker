<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\Dashboard\AiQueryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformWorkspaceRecoveryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\ReminderDashboardController;
use App\Http\Controllers\ReminderScheduleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceRecoveryController;
use App\Http\Controllers\WorkspaceUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('invoice/public')->name('public.invoice.')->group(function () {
    Route::get('/{token}', [PublicInvoiceController::class, 'show'])->middleware('signed')->name('show');
    Route::get('/{token}/pdf', [PublicInvoiceController::class, 'downloadPdf'])->middleware('signed')->name('pdf');
    Route::get('/{token}/print', [PublicInvoiceController::class, 'print'])->middleware('signed')->name('print');
});

Route::middleware(['auth', 'verified', 'workspace.active', 'resolve.workspace'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // AI Query routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/ai-query', [AiQueryController::class, 'index'])->name('ai-query');
        Route::post('/ai-query', [AiQueryController::class, 'search'])->name('ai-query.search');
    });

    // workspace management routes
    Route::get('/workspace', [WorkspaceController::class, 'index'])->name('workspace.index');
    Route::get('/workspace/create', [WorkspaceController::class, 'create'])->name('workspace.create');
    Route::post('/workspace', [WorkspaceController::class, 'store'])->name('workspace.store');
    Route::get('/workspace/{workspace}', [WorkspaceController::class, 'show'])->name('workspace.show');
    Route::get('/workspace/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('workspace.edit');
    Route::put('/workspace/{workspace}', [WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspace/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');
    Route::post('/workspace/{workspace}/exit', [WorkspaceController::class, 'exitWorkspace'])->name('workspace.exit');

    Route::prefix('workspace/{workspace}/user')->name('workspace.users.')->group(function () {
        Route::get('/', [WorkspaceUserController::class, 'index'])->name('index');
        Route::get('/create', [WorkspaceUserController::class, 'create'])->name('create');
        Route::post('/', [WorkspaceUserController::class, 'store'])->name('store');
        Route::get('/lookup', [WorkspaceUserController::class, 'lookup'])->name('lookup');
        Route::get('/{user}', [WorkspaceUserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [WorkspaceUserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [WorkspaceUserController::class, 'update'])->name('update');
        // Route::put('/workspaces/{workspace}/users/{user}', ...)
        Route::delete('/{user}', [WorkspaceUserController::class, 'destroy'])->name('destroy');
    });

    Route::domain('{workspace}.'.config('app.base_domain'))->get('/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');

    // business profiles routes
    Route::get('/business-profile', [BusinessProfileController::class, 'index'])->name('business-profile.index');
    Route::put('/business-profile/{businessProfile}/update', [BusinessProfileController::class, 'update'])->name('business-profile.update');

    // clients routes (workspace scoped)
    Route::prefix('workspace/{workspace}/clients')->name('clients.')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/create', [ClientController::class, 'create'])->name('create');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])->name('show');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('edit');
        Route::put('/{client}', [ClientController::class, 'update'])->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('workspace/{workspace}')->group(function () {
        Route::get('/dashboard', WorkspaceDashboardController::class)->name('workspace.dashboard');

        Route::post('email-templates/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
        Route::resource('email-templates', EmailTemplateController::class)->except(['show']);

        Route::resource('reminder-schedules', ReminderScheduleController::class)->except(['show']);
        Route::patch('reminder-schedules/{reminderSchedule}/toggle', [ReminderScheduleController::class, 'toggle'])->name('reminder-schedules.toggle');
    });

    // invoices routes (workspace scoped)
    Route::prefix('workspace/{workspace}/invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{invoice}/send', [InvoiceController::class, 'send'])->name('send');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::post('/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Workspace-scoped reports. The active workspace is resolved from the host, never from a request parameter.
    Route::middleware('authorized-workspace-user')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/exports/{reportExport}/download', [ReportController::class, 'download'])->name('exports.download');
        Route::post('/{report}/export/{format}/queue', [ReportController::class, 'queue'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->name('export.queue');
        Route::get('/{report}/export/{format}', [ReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->name('export');
        Route::get('/{report?}', [ReportController::class, 'index'])->name('index');
    });

    // Reminder Dashboard Routes - Only for workspace owners and admins
    Route::middleware('authorized-workspace-user')->prefix('reminders/')->name('reminders.')->group(function () {
        Route::get('/', [ReminderDashboardController::class, 'index'])->name('index');
        Route::get('/activity', [ReminderDashboardController::class, 'activity'])->name('activity');
        Route::get('/upcoming', [ReminderDashboardController::class, 'upcoming'])->name('upcoming');
        Route::get('/sent', [ReminderDashboardController::class, 'sent'])->name('sent');
        Route::get('/failed', [ReminderDashboardController::class, 'failed'])->name('failed');
        Route::post('/retry/{log}', [ReminderDashboardController::class, 'retry'])->name('retry');
    });
});

Route::domain(config('app.base_domain'))->middleware(['auth', 'verified'])->prefix('recovery')->name('workspace.recovery.')->group(function () {
    Route::get('/', [WorkspaceRecoveryController::class, 'index'])->name('index');
    Route::get('/{workspaceId}', [WorkspaceRecoveryController::class, 'show'])->name('show');
    Route::get('/{workspaceId}/audit', [WorkspaceRecoveryController::class, 'audit'])->name('audit');
    Route::post('/{workspaceId}/restore', [WorkspaceRecoveryController::class, 'restore'])->name('restore');
});

Route::domain(config('app.base_domain'))->middleware(['auth', 'verified', 'can:manage-platform-workspace-recovery'])->prefix('platform/workspace-recovery')->name('platform.recovery.')->group(function () {
    Route::get('/', [PlatformWorkspaceRecoveryController::class, 'index'])->name('index');
    Route::get('/audits', [PlatformWorkspaceRecoveryController::class, 'audits'])->name('audits');
    Route::get('/{workspaceId}', [PlatformWorkspaceRecoveryController::class, 'show'])->name('show');
    Route::post('/{workspaceId}/restore', [PlatformWorkspaceRecoveryController::class, 'restore'])->name('restore');
});

// system currency management routes
Route::domain(config('app.base_domain'))->prefix('currencies')->name('currencies.')->group(function () {
    Route::get('/', [CurrencyController::class, 'index'])->name('index');
    Route::get('/create', [CurrencyController::class, 'create'])->name('create');
    Route::post('/', [CurrencyController::class, 'store'])->name('store');
    Route::get('/{currency}/edit', [CurrencyController::class, 'edit'])->name('edit');
    Route::put('/{currency}', [CurrencyController::class, 'update'])->name('update');
    Route::patch('/{currency}/toggle', [CurrencyController::class, 'toggle'])->name('toggle');
    Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->name('destroy');
});

// Join workspace via shareable link (requires login)
// Route::get('/workspace/join/{workspace:slug}', [WorkspaceController::class, 'join'])
//     ->name('workspace.join')
//     ->middleware(['auth', 'verified']);

// Invitation acceptance route (requires login)
Route::get('/invitations/accept/{token}', [WorkspaceUserController::class, 'acceptInvitation'])
    ->name('workspace.users.accept')
    ->middleware('auth');

require __DIR__.'/auth.php';
