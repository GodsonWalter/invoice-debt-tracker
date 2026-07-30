<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:process')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('workspaces:lifecycle')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:cleanup-exports')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
