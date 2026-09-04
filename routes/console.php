<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
