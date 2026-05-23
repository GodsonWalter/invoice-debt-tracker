<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', 'workspace.active', 'resolve.workspace'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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

    Route::domain('{workspace}.' . config('app.base_domain'))->get('/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');   
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// // Join workspace via shareable link (requires login)
// Route::get('/workspace/join/{workspace:slug}', [WorkspaceController::class, 'join'])
//     ->name('workspace.join')
//     ->middleware(['auth', 'verified']);

// Invitation acceptance route (requires login)
Route::get('/invitations/accept/{token}', [WorkspaceUserController::class, 'acceptInvitation'])
    ->name('workspace.users.accept')
    ->middleware('auth');

require __DIR__ . '/auth.php';
