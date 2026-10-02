<?php

namespace App\Console\Commands;

use App\Mail\FinancialOverviewReportMail;
use App\Models\User;
use App\Services\CronReportDeliveryService;
use App\Services\FinancialOverviewReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailFinancialOverviewReports extends Command
{
    protected $signature = 'financial-overview-reports:email
        {--from= : Report start date in YYYY-MM-DD format}
        {--to= : Report end date in YYYY-MM-DD format}
        {--user= : Send only to this user ID}';

    protected $description = 'Send financial overview reports to configured recipients';

    public function handle(FinancialOverviewReportService $reportService, CronReportDeliveryService $deliveryService): int
    {
        try {
            [$fromDate, $toDate] = $this->resolveDateRange();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $sent = 0;
        $failed = 0;
        $users = User::query()->select(['id', 'name', 'email'])->orderBy('id');
        if ($this->option('user')) {
            $users->whereKey((int) $this->option('user'));
        }

        $users->chunkById(100, function ($users) use ($reportService, $deliveryService, $fromDate, $toDate, &$sent, &$failed): void {
            foreach ($users as $user) {
                try {
                    $report = $reportService->generate($user, $fromDate, $toDate);
                    $summary = $report['summary'];
                    $deliveryService->deliver(
                        $user,
                        'financial_overview_reports',
                        new FinancialOverviewReportMail($user, $fromDate, $toDate, $summary, $report['transferReceiptsByAccount'], $report['pdf'], $report['filename']),
                        "<b>Financial overview ready</b>\nPeriod: {$fromDate->toDateString()} to {$toDate->toDateString()}\nClosing balance: {$summary['closing_balance']}",
                    );
                    $sent++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Unable to send financial overview report.', ['user_id' => $user->id, 'exception' => $exception]);
                    $this->warn("Financial overview report failed for user ID {$user->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->info("Financial overview reports finished. Sent: {$sent}; failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDateRange(): array
    {
        $timezone = config('financial_overview_reports.timezone', config('app.timezone'));
        $today = CarbonImmutable::now($timezone);
        $fromDate = $this->option('from')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('from'), $timezone)
            : $today->startOfMonth();
        $toDate = $this->option('to')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('to'), $timezone)
            : ($this->option('from') ? $fromDate->endOfDay() : $today->endOfDay());

        if (! $fromDate || ! $toDate || $fromDate->greaterThan($toDate)) {
            throw new \InvalidArgumentException('Dates must use YYYY-MM-DD format and the start date cannot be after the end date.');
        }

        return [$fromDate, $toDate];
    }
}
