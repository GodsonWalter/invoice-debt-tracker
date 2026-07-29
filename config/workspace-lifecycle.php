<?php

return [
    'self_service_restore_days' => (int) env('WORKSPACE_SELF_SERVICE_RESTORE_DAYS', 30),
    'permanent_deletion_days' => (int) env('WORKSPACE_PERMANENT_DELETION_DAYS', 120),
    'owner_restore_warning_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('WORKSPACE_OWNER_RESTORE_WARNING_DAYS', '7,1')),
    ))),
    'permanent_deletion_warning_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('WORKSPACE_PERMANENT_DELETION_WARNING_DAYS', '30,7,1')),
    ))),
    'cleanup_batch_size' => (int) env('WORKSPACE_LIFECYCLE_CLEANUP_BATCH_SIZE', 100),
    'queue_connection' => env('WORKSPACE_LIFECYCLE_QUEUE_CONNECTION', 'database'),
];
