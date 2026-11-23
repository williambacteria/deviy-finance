<?php

return [
    'controller_url' => env('DEVIY_CONTROLLER_URL', 'http://127.0.0.1:8088'),
    'controller_public_url' => env('DEVIY_CONTROLLER_PUBLIC_URL', 'https://demovps.deviy.cn'),
    'controller_token' => env('DEVIY_CONTROLLER_TOKEN', ''),
    'controller_ca' => env('DEVIY_CONTROLLER_CA', ''),
    'controller_cert' => env('DEVIY_CONTROLLER_CERT', ''),
    'controller_key' => env('DEVIY_CONTROLLER_KEY', ''),
    'controller_state_path' => env('DEVIY_CONTROLLER_STATE_PATH', ''),
    'timeout' => (int) env('DEVIY_CONTROLLER_TIMEOUT', 30),
    'network_repair_threshold' => (int) env('DEVIY_NETWORK_REPAIR_THRESHOLD', 20),
    'upload_max_bytes' => (int) env('DEVIY_UPLOAD_MAX_BYTES', 20 * 1024 * 1024),
    'upload_disk' => env('DEVIY_UPLOAD_DISK', 'local'),
    'ticket_attachment_quota' => (int) env('DEVIY_TICKET_ATTACHMENT_QUOTA', 50 * 1024 * 1024),
    'attachment_quota_groups' => [
        'standard' => (int) env('DEVIY_ATTACHMENT_QUOTA_STANDARD', 50 * 1024 * 1024),
        'vip' => (int) env('DEVIY_ATTACHMENT_QUOTA_VIP', 200 * 1024 * 1024),
    ],
    'virus_scanner' => env('DEVIY_VIRUS_SCANNER', 'none'),
    'clamav_binary' => env('DEVIY_CLAMAV_BINARY', 'clamdscan'),
    'settings_approval_threshold' => (int) env('DEVIY_SETTINGS_APPROVAL_THRESHOLD', 25),
    'settings_dual_approval' => env('DEVIY_SETTINGS_DUAL_APPROVAL', false),
    'settings_requester_can_approve' => env('DEVIY_SETTINGS_REQUESTER_CAN_APPROVE', true),
    'ops_webhook_url' => env('OPS_WEBHOOK_URL', ''),
    'ops_webhook_token' => env('OPS_WEBHOOK_TOKEN', ''),
    'ops_escalate_minutes' => (int) env('OPS_ESCALATE_MINUTES', 15),
    'ops_oncall_admin_id' => env('OPS_ONCALL_ADMIN_ID'),
];
