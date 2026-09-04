<?php

namespace App\Console\Commands;

use App\Mail\CostReductionReportMail;
use App\Models\User;
use App\Services\CostReductionReportService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailCostReductionReports extends Command
{
    protected $signature = 'cost-reduction-reports:email
        {--from= : Report start date in YYYY-MM-DD format}
        {--to= : Report end date in YYYY-MM-DD format}
        {--user= : Send only to this user ID}
        {--include-empty : Send reports even when no expenses exist}';

    protected $description = 'Email database-driven cost reduction PDF reports to users';

    public function handle(CostReductionReportService $reportService, NotificationService $notificationService): int
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
        $recipient = config('cost_reduction_reports.recipient');
        $cc = config('cost_reduction_reports.cc');
        $bcc = config('cost_reduction_reports.bcc');
        $query = User::query()->select(['id', 'name', 'email'])->orderBy('id');

        if ($this->option('user')) {
            $query->whereKey((int) $this->option('user'));
        }

        $query->chunkById(100, function ($users) use ($reportService, $notificationService, $fromDate, $toDate, $recipient, $cc, $bcc, &$sent, &$skipped, &$failed): void {
            foreach ($users as $user) {
                try {
                    $analysis = $reportService->summary($user, $fromDate, $toDate);

                    if (
                        $analysis['rows']->where('transaction_count', '>', 0)->isEmpty()
                        && config('cost_reduction_reports.skip_empty', true)
                        && ! $this->option('include-empty')
                    ) {
                        $skipped++;
                        continue;
                    }

                    $report = $reportService->generate($user, $fromDate, $toDate, $analysis);

                    $notificationService->sendEmail(
                        to: $recipient ?: $user->email,
                        mail: new CostReductionReportMail(
                            user: $user,
                            fromDate: $fromDate,
                            toDate: $toDate,
                            summary: $report['summary'],
                            pdf: $report['pdf'],
                            filename: $report['filename'],
                        ),
                        cc: $cc,
                        bcc: $bcc,
                    );

                    $sent++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Unable to email cost reduction report.', [
                        'user_id' => $user->id,
                        'from_date' => $fromDate->toDateString(),
                        'to_date' => $toDate->toDateString(),
                        'exception' => $exception,
                    ]);
                    $this->warn("Cost reduction report failed for user ID {$user->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->info("Cost reduction reports finished. Sent: {$sent}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDateRange(): array
    {
        $timezone = config('cost_reduction_reports.timezone', config('app.timezone'));
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
