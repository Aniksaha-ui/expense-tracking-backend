<?php

return [
    'enabled' => env('EXPENSE_INTELLIGENCE_REPORT_ENABLED', true),
    'timezone' => env('EXPENSE_INTELLIGENCE_REPORT_TIMEZONE', config('app.timezone')),
    'daily_send_time' => env('EXPENSE_INTELLIGENCE_DAILY_SEND_TIME', '21:00'),
    'weekly_send_time' => env('EXPENSE_INTELLIGENCE_WEEKLY_SEND_TIME', '21:30'),
    'monthly_send_time' => env('EXPENSE_INTELLIGENCE_MONTHLY_SEND_TIME', '22:00'),
    'weekly_send_day' => env('EXPENSE_INTELLIGENCE_WEEKLY_SEND_DAY', 'sunday'),
    'monthly_send_day' => (int) env('EXPENSE_INTELLIGENCE_MONTHLY_SEND_DAY', 1),
    'skip_empty' => env('EXPENSE_INTELLIGENCE_SKIP_EMPTY', true),
    'recipient' => env('EXPENSE_INTELLIGENCE_RECIPIENT'),
    'cc' => array_filter(array_map('trim', explode(',', env('EXPENSE_INTELLIGENCE_CC', '')))),
    'bcc' => array_filter(array_map('trim', explode(',', env('EXPENSE_INTELLIGENCE_BCC', '')))),
    'lookback_months' => max(1, (int) env('EXPENSE_INTELLIGENCE_LOOKBACK_MONTHS', 12)),
    'top_categories' => max(1, (int) env('EXPENSE_INTELLIGENCE_TOP_CATEGORIES', 10)),
    'small_transaction_threshold' => env('EXPENSE_INTELLIGENCE_SMALL_TRANSACTION_THRESHOLD', '500.00'),
    'significant_change_percentage' => env('EXPENSE_INTELLIGENCE_SIGNIFICANT_CHANGE_PERCENTAGE', '20.0'),
    'anomaly_multiplier' => env('EXPENSE_INTELLIGENCE_ANOMALY_MULTIPLIER', '2.50'),
    'minimum_anomaly_samples' => max(1, (int) env('EXPENSE_INTELLIGENCE_MINIMUM_ANOMALY_SAMPLES', 5)),
    'max_savings_rate' => env('EXPENSE_INTELLIGENCE_MAX_SAVINGS_RATE', '0.30'),
];
