<?php

namespace App\Console\Commands;

use App\Mail\CategoryExpenseReportMail;
use App\Models\User;
use App\Services\CategoryExpenseReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailCategoryExpenseReports extends Command
{
    protected $signature = 'expense-reports:email
        {--from= : Report start date in YYYY-MM-DD format}
        {--to= : Report end date in YYYY-MM-DD format}
        {--user= : Send only to this user ID}
        {--include-empty : Send reports even when no expenses exist}';

    protected $description = 'Email category-wise expense PDF reports to users';

    public function handle(CategoryExpenseReportService $reportService): int
    {
        try {
            [$fromDate, $toDate] = $this->resolveDateRange();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $recipient = config('expense_reports.recipient');
        $query = User::query()->select(['id', 'name', 'email'])->orderBy('id');

        if ($this->option('user')) {
            $query->whereKey((int) $this->option('user'));
        }

        $query->chunkById(100, function ($users) use ($reportService, $fromDate, $toDate, $recipient, &$sent, &$skipped, &$failed): void {
            foreach ($users as $user) {
                try {
                    $summary = $reportService->summary($user, $fromDate, $toDate);

                    if (
                        $summary['rows']->isEmpty()
                        && config('expense_reports.skip_empty', true)
                        && ! $this->option('include-empty')
                    ) {
                        $skipped++;
                        continue;
                    }

                    $report = $reportService->generate($user, $fromDate, $toDate, $summary);

                    Mail::to($recipient ?: $user->email)->send(new CategoryExpenseReportMail(
                        user: $user,
                        fromDate: $fromDate,
                        toDate: $toDate,
                        rows: $report['rows'],
                        total: $report['total'],
                        pdf: $report['pdf'],
                        filename: $report['filename'],
                    ));

                    $sent++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Unable to email category expense report.', [
                        'user_id' => $user->id,
                        'from_date' => $fromDate->toDateString(),
                        'to_date' => $toDate->toDateString(),
                        'exception' => $exception,
                    ]);
                    $this->warn("Report failed for user ID {$user->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->info("Category expense reports finished. Sent: {$sent}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDateRange(): array
    {
        $timezone = config('expense_reports.timezone', config('app.timezone'));
        $today = CarbonImmutable::now($timezone);
        $fromDate = $this->option('from')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('from'), $timezone)
            : $today->startOfMonth();
        $toDate = $this->option('to')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('to'), $timezone)
            : ($this->option('from') ? $fromDate : $today)->endOfDay();

        if (! $fromDate || ! $toDate) {
            throw new \InvalidArgumentException('Dates must use the YYYY-MM-DD format.');
        }

        if ($fromDate->greaterThan($toDate)) {
            throw new \InvalidArgumentException('The from date must be before or equal to the to date.');
        }

        return [$fromDate, $toDate];
    }
}
