<?php

return [
    'enabled' => env('COST_REPORT_ENABLED', true),
    'send_time' => env('COST_REPORT_SEND_TIME', '08:30'),
    'timezone' => env('COST_REPORT_TIMEZONE', config('app.timezone')),
    'skip_empty' => env('COST_REPORT_SKIP_EMPTY', true),
    'recipient' => env('COST_REPORT_RECIPIENT'),
    'cc' => array_filter(array_map('trim', explode(',', env('COST_REPORT_CC', '')))),
    'bcc' => array_filter(array_map('trim', explode(',', env('COST_REPORT_BCC', '')))),
    'top_categories' => (int) env('COST_REPORT_TOP_CATEGORIES', 10),
    'max_savings_rate' => env('COST_REPORT_MAX_SAVINGS_RATE', '0.30'),
];
