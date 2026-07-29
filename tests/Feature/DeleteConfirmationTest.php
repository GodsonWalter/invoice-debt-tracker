<?php

use Illuminate\Support\Facades\File;

test('all delete confirmations use the shared SweetAlert2 implementation', function () {
    $bladeContents = collect(File::allFiles(resource_path('views')))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($bladeContents)->not->toContain('confirm(');

    $deleteFormViews = [
        'views/client/index.blade.php',
        'views/currencies/index.blade.php',
        'views/email-templates/index.blade.php',
        'views/invoice/index.blade.php',
        'views/profile/edit.blade.php',
        'views/profile/partials/delete-user-form.blade.php',
        'views/reminder-schedules/index.blade.php',
        'views/workspace-users/index.blade.php',
        'views/workspace-users/show.blade.php',
        'views/workspace/index.blade.php',
    ];

    foreach ($deleteFormViews as $view) {
        expect(File::get(resource_path($view)))
            ->toContain('data-delete-confirm');
    }

    $handler = File::get(public_path('js/delete-confirmation.js'));
    $appLayout = File::get(resource_path('views/layouts/app.blade.php'));

    expect($handler)
        ->toContain('Swal.fire')
        ->toContain("title: 'Delete Record?'")
        ->toContain("'This action cannot be undone.'")
        ->toContain('data-delete-confirm-name')
        ->toContain('The workspace name must match exactly.')
        ->toContain('This workspace will be deactivated and moved to recovery status.')
        ->toContain("confirmButtonText: 'Yes, Delete'")
        ->toContain("cancelButtonText: 'Cancel'")
        ->toContain('requestSubmit');

    expect($appLayout)
        ->toContain('sweetalert2@11')
        ->toContain("asset('js/delete-confirmation.js')");
});
