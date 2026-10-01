<?php

return [
    'enabled' => env('FINANCIAL_OVERVIEW_REPORT_ENABLED', true),
    'send_time' => env('FINANCIAL_OVERVIEW_REPORT_SEND_TIME', '09:00'),
    'timezone' => env('FINANCIAL_OVERVIEW_REPORT_TIMEZONE', config('app.timezone')),
    'recipient' => env('FINANCIAL_OVERVIEW_REPORT_RECIPIENT'),
    'cc' => array_filter(array_map('trim', explode(',', env('FINANCIAL_OVERVIEW_REPORT_CC', '')))),
    'bcc' => array_filter(array_map('trim', explode(',', env('FINANCIAL_OVERVIEW_REPORT_BCC', '')))),
];
