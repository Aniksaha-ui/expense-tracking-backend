<?php

return [
    'enabled' => env('EXPENSE_REPORT_ENABLED', true),
    'send_time' => env('EXPENSE_REPORT_SEND_TIME', '08:00'),
    'timezone' => env('EXPENSE_REPORT_TIMEZONE', 'Asia/Dhaka'),
    'skip_empty' => env('EXPENSE_REPORT_SKIP_EMPTY', true),
    'recipient' => env('EXPENSE_REPORT_RECIPIENT', 'sahaanik1045@gmail.com'),
    'cc' => array_filter(array_map('trim', explode(',', env('EXPENSE_REPORT_CC', '')))),
    'bcc' => array_filter(array_map('trim', explode(',', env('EXPENSE_REPORT_BCC', '')))),
];
