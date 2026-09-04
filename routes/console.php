<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

if (! function_exists('weeklyScheduleDay')) {
    function weeklyScheduleDay(string $day): int
    {
        return [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ][strtolower($day)] ?? 0;
    }
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('expense_reports.enabled', true)) {
    Schedule::command('expense-reports:email')
        ->dailyAt(config('expense_reports.send_time', '08:00'))
        ->timezone(config('expense_reports.timezone', config('app.timezone')))
        ->withoutOverlapping()
        ->onOneServer();
}

if (config('cost_reduction_reports.enabled', true)) {
    Schedule::command('cost-reduction-reports:email')
        ->dailyAt(config('cost_reduction_reports.send_time', '08:30'))
        ->timezone(config('cost_reduction_reports.timezone', config('app.timezone')))
        ->withoutOverlapping()
        ->onOneServer();
}

if (config('expense_intelligence_reports.enabled', true)) {
    Schedule::command('expense-intelligence-reports:email daily')
        ->dailyAt(config('expense_intelligence_reports.daily_send_time', '21:00'))
        ->timezone(config('expense_intelligence_reports.timezone', config('app.timezone')))
        ->withoutOverlapping()
        ->onOneServer();

    Schedule::command('expense-intelligence-reports:email weekly')
        ->weeklyOn(weeklyScheduleDay(config('expense_intelligence_reports.weekly_send_day', 'sunday')), config('expense_intelligence_reports.weekly_send_time', '21:30'))
        ->timezone(config('expense_intelligence_reports.timezone', config('app.timezone')))
        ->withoutOverlapping()
        ->onOneServer();

    Schedule::command('expense-intelligence-reports:email bi-weekly')
        ->weeklyOn(weeklyScheduleDay(config('expense_intelligence_reports.bi_weekly_send_day', 'sunday')), config('expense_intelligence_reports.bi_weekly_send_time', '21:45'))
        ->timezone(config('expense_intelligence_reports.timezone', config('app.timezone')))
        ->when(fn (): bool => now(config('expense_intelligence_reports.timezone', config('app.timezone')))->weekOfYear % 2 === 0)
        ->withoutOverlapping()
        ->onOneServer();

    Schedule::command('expense-intelligence-reports:email monthly')
        ->monthlyOn(config('expense_intelligence_reports.monthly_send_day', 1), config('expense_intelligence_reports.monthly_send_time', '22:00'))
        ->timezone(config('expense_intelligence_reports.timezone', config('app.timezone')))
        ->withoutOverlapping()
        ->onOneServer();
}
