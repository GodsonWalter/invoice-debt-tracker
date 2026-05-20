<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', 'workspace.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // workspace management routes
    Route::get('/workspace', [WorkspaceController::class, 'index'])->name('workspace.index');
    Route::get('/workspace/create', [WorkspaceController::class, 'create'])->name('workspace.create');
    Route::post('/workspace', [WorkspaceController::class, 'store'])->name('workspace.store');
    Route::get('/workspace/{workspace}', [WorkspaceController::class, 'show'])->name('workspace.show');
    Route::get('/workspace/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('workspace.edit');
    Route::put('/workspace/{workspace}', [WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspace/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');

    Route::domain('{workspace}.' . config('app.base_domain'))->get('/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
   
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
