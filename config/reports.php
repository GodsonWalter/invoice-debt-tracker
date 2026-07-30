<?php

return [
    'large_export_row_threshold' => (int) env('REPORT_LARGE_EXPORT_ROW_THRESHOLD', 5000),
    'export_disk' => env('REPORT_EXPORT_DISK', 'local'),
    'export_retention_days' => (int) env('REPORT_EXPORT_RETENTION_DAYS', 7),
];
