<?php

return [
    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mariadb-dump'),
    'client_binary' => env('BACKUP_CLIENT_BINARY', 'mariadb'),
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 7),
    'disk' => env('BACKUP_DISK', 'local'),
    'prefix' => env('BACKUP_S3_PREFIX', 'backups'),
];
