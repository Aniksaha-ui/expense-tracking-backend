<?php

namespace App\Console\Commands;

use App\Mail\ExpenseIntelligenceReportMail;
use App\Models\User;
use App\Services\ExpenseIntelligenceReportService;
use App\Services\NotificationService;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailExpenseIntelligenceReports extends Command
{
    protected $signature = 'expense-intelligence-reports:email
        {frequency : daily, weekly, or monthly}
        {--from= : Report start date in YYYY-MM-DD format}
        {--to= : Report end date in YYYY-MM-DD format}
        {--user= : Send only to this user ID}
        {--include-empty : Send reports even when no expenses exist}';

    protected $description = 'Email data-driven expense intelligence PDF reports to users';

    public function handle(ExpenseIntelligenceReportService $reportService, NotificationService $notificationService): int
    {
        $frequency = strtolower((string) $this->argument('frequency'));

        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $this->error('Frequency must be daily, weekly, or monthly.');

            return self::FAILURE;
        }

        try {
            [$fromDate, $toDate] = $this->resolveDateRange($frequency);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $recipient = config('expense_intelligence_reports.recipient');
        $cc = config('expense_intelligence_reports.cc');
        $bcc = config('expense_intelligence_reports.bcc');
        $query = User::query()->select(['id', 'name', 'email'])->orderBy('id');

        if ($this->option('user')) {
            $query->whereKey((int) $this->option('user'));
        }

        $query->chunkById(100, function ($users) use ($reportService, $notificationService, $frequency, $fromDate, $toDate, $recipient, $cc, $bcc, &$sent, &$skipped, &$failed): void {
            foreach ($users as $user) {
                try {
                    $report = $reportService->generate($user, $frequency, $fromDate, $toDate);

                    if (
                        BigDecimal::of($report['report']['summary']['total_expense'] ?? '0')->isZero()
                        && config('expense_intelligence_reports.skip_empty', true)
                        && ! $this->option('include-empty')
                    ) {
                        $skipped++;
                        continue;
                    }

                    $notificationService->sendEmail(
                        to: $recipient ?: $user->email,
                        mail: new ExpenseIntelligenceReportMail(
                            user: $user,
                            frequency: $frequency,
                            title: $report['title'],
                            fromDate: $fromDate,
                            toDate: $toDate,
                            summary: $report['report']['summary'],
                            pdf: $report['pdf'],
                            filename: $report['filename'],
                        ),
                        cc: $cc,
                        bcc: $bcc,
                    );

                    $sent++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Unable to email expense intelligence report.', [
                        'frequency' => $frequency,
                        'user_id' => $user->id,
                        'from_date' => $fromDate->toDateString(),
                        'to_date' => $toDate->toDateString(),
                        'exception' => $exception,
                    ]);
                    $this->warn("{$frequency} intelligence report failed for user ID {$user->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->info("{$frequency} expense intelligence reports finished. Sent: {$sent}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDateRange(string $frequency): array
    {
        $timezone = config('expense_intelligence_reports.timezone', config('app.timezone'));
        $today = CarbonImmutable::now($timezone);

        if ($this->option('from') || $this->option('to')) {
            $fromDate = $this->option('from')
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('from'), $timezone)
                : $today->startOfDay();
            $toDate = $this->option('to')
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('to'), $timezone)->endOfDay()
                : $fromDate->endOfDay();
        } else {
            [$fromDate, $toDate] = match ($frequency) {
                'daily' => [$today->startOfDay(), $today->endOfDay()],
                'weekly' => [$today->startOfWeek(), $today->endOfWeek()],
                'monthly' => [$today->startOfMonth(), $today->endOfMonth()],
            };
        }

        if (! $fromDate || ! $toDate) {
            throw new \InvalidArgumentException('Dates must use the YYYY-MM-DD format.');
        }

        if ($fromDate->greaterThan($toDate)) {
            throw new \InvalidArgumentException('The from date must be before or equal to the to date.');
        }

        return [$fromDate, $toDate];
    }
}
