<?php

use Illuminate\Support\Facades\Route;

test('private local storage is not exposed through a public storage route', function (): void {
    expect(Route::has('storage.local'))->toBeFalse();
});
