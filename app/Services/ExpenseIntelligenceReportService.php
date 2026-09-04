<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseIntelligenceReportService
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    public function generate(User $user, string $frequency, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $report = $this->reportData($user, $frequency, $fromDate, $toDate);
        $viewData = [
            'user' => $user,
            'frequency' => $frequency,
            'title' => $this->title($frequency),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'report' => $report,
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render(
                'reports.expense-intelligence-pdf',
                $viewData,
                orientation: 'landscape',
            ),
            'filename' => sprintf(
                '%s-expense-intelligence-%s-to-%s.pdf',
                $frequency,
                $fromDate->toDateString(),
                $toDate->toDateString()
            ),
        ];
    }

    public function reportData(User $user, string $frequency, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        return match ($frequency) {
            'daily' => $this->daily($user->id, $fromDate, $toDate),
            'weekly' => $this->weekly($user->id, $fromDate, $toDate),
            'bi-weekly' => $this->biWeekly($user->id, $fromDate, $toDate),
            'monthly' => $this->monthly($user->id, $fromDate, $toDate),
            default => throw new InvalidArgumentException('Invalid report frequency.'),
        };
    }

    public function title(string $frequency): string
    {
        return match ($frequency) {
            'daily' => 'Daily Expense Intelligence Report',
            'weekly' => 'Weekly Expense Intelligence Report',
            'bi-weekly' => 'Bi-weekly Expense Intelligence Report',
            'monthly' => 'Monthly Expense Intelligence Report',
            default => 'Expense Intelligence Report',
        };
    }

    private function daily(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $summary = $this->summary($userId, $fromDate, $toDate);
        $historicalDailyAverage = $this->historicalDailyAverage($userId, $fromDate);
        $difference = BigDecimal::of($summary['total_expense'])->minus($historicalDailyAverage)->toScale(2, RoundingMode::HALF_UP);
        $increasePercentage = BigDecimal::of($historicalDailyAverage)->isZero()
            ? null
            : $this->percentage($difference, $historicalDailyAverage);
        $largestExpenses = $this->largestExpenses($userId, $fromDate, $toDate, 5);
        $leakage = $this->smallExpenseLeakage($userId, $fromDate, $toDate);
        $anomalies = $this->anomalies($userId, $fromDate, $toDate);
        $categoryRows = $this->categoryAnalysis($userId, $fromDate, $toDate);

        return [
            'summary' => [
                ...$summary,
                'historical_daily_average' => $this->money($historicalDailyAverage),
                'historical_difference' => (string) $difference,
                'historical_increase_percentage' => $increasePercentage,
            ],
            'category_rows' => $categoryRows,
            'largest_expenses' => $largestExpenses,
            'leakage' => $leakage,
            'anomalies' => $anomalies,
            'trend_rows' => $this->dailyHoursTrend($userId, $fromDate, $toDate),
            'daily_cost_rows' => $this->dailyTrend($userId, $fromDate, $toDate),
            'account_balance_rows' => $this->accountDailyBalances($userId, $fromDate, $toDate),
            'account_rows' => $this->accountBreakdown($userId, $fromDate, $toDate),
            'definitions' => $this->reportDefinitions('daily'),
            'section_guides' => $this->sectionGuides('daily'),
            'calculation_hints' => $this->calculationHints('daily'),
            'insights' => $this->dailyInsights($summary, $historicalDailyAverage, $increasePercentage, $largestExpenses, $leakage),
            'recommendations' => $this->dailyRecommendations($summary, $historicalDailyAverage, $increasePercentage, $leakage, $anomalies),
        ];
    }

    private function weekly(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $summary = $this->summary($userId, $fromDate, $toDate);
        $dailyTrend = $this->dailyTrend($userId, $fromDate, $toDate);
        $categoryRows = $this->categoryAnalysis($userId, $fromDate, $toDate);
        $leakage = $this->smallExpenseLeakage($userId, $fromDate, $toDate);
        $anomalies = $this->anomalies($userId, $fromDate, $toDate);
        $weekAverage = $this->recentPeriodAverage($userId, $fromDate, 'week', 4);
        $targetLow = BigDecimal::of($weekAverage)->multipliedBy('0.95')->toScale(2, RoundingMode::HALF_UP);
        $targetHigh = BigDecimal::of($weekAverage)->multipliedBy('1.05')->toScale(2, RoundingMode::HALF_UP);
        $periodDays = max(1, $fromDate->diffInDays($toDate) + 1);

        return [
            'summary' => [
                ...$summary,
                'average_daily_expense' => $this->money(BigDecimal::of($summary['total_expense'])->dividedBy((string) $periodDays, 2, RoundingMode::HALF_UP)),
                'highest_spending_day' => $dailyTrend->sortByDesc(fn (array $row): float => (float) $row['expense'])->first(),
                'lowest_spending_day' => $dailyTrend->sortBy(fn (array $row): float => (float) $row['expense'])->first(),
                'recurring_expense' => $this->recurringExpenseTotal($userId, $fromDate, $toDate),
            ],
            'trend_rows' => $dailyTrend,
            'category_rows' => $categoryRows->sortByDesc(fn (array $row): float => (float) $row['difference'])->values(),
            'daily_cost_rows' => $dailyTrend,
            'account_balance_rows' => $this->accountDailyBalances($userId, $fromDate, $toDate),
            'leakage' => [
                ...$leakage,
                'repeated_similar_expenses' => $this->repeatedSimilarExpenses($userId, $fromDate, $toDate),
            ],
            'account_rows' => $this->accountBreakdown($userId, $fromDate, $toDate),
            'comparison' => $this->periodComparison($summary['total_expense'], $this->sumAmounts($categoryRows, 'previous_expense')),
            'anomalies' => $anomalies,
            'alerts' => $this->weeklyAlerts($summary, $categoryRows),
            'opportunities' => $categoryRows->sortByDesc('opportunity_score')->take(5)->values(),
            'plan' => [
                'last_4_week_average' => $this->money($weekAverage),
                'current_week' => $summary['total_expense'],
                'target_low' => (string) $targetLow,
                'target_high' => (string) $targetHigh,
            ],
            'definitions' => $this->reportDefinitions('weekly'),
            'section_guides' => $this->sectionGuides('weekly'),
            'calculation_hints' => $this->calculationHints('weekly'),
            'recommendations' => $this->weeklyRecommendations($categoryRows, $leakage, $anomalies, (string) $targetHigh),
        ];
    }

    private function biWeekly(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $summary = $this->summary($userId, $fromDate, $toDate);
        $dailyTrend = $this->dailyTrend($userId, $fromDate, $toDate);
        $categoryRows = $this->categoryAnalysis($userId, $fromDate, $toDate);
        $leakage = $this->smallExpenseLeakage($userId, $fromDate, $toDate);
        $anomalies = $this->anomalies($userId, $fromDate, $toDate);
        $biWeeklyAverage = $this->recentPeriodAverage($userId, $fromDate, 'bi-week', 3);
        $targetLow = BigDecimal::of($biWeeklyAverage)->multipliedBy('0.95')->toScale(2, RoundingMode::HALF_UP);
        $targetHigh = BigDecimal::of($biWeeklyAverage)->multipliedBy('1.05')->toScale(2, RoundingMode::HALF_UP);
        $periodDays = max(1, $fromDate->diffInDays($toDate) + 1);

        return [
            'summary' => [
                ...$summary,
                'average_daily_expense' => $this->money(BigDecimal::of($summary['total_expense'])->dividedBy((string) $periodDays, 2, RoundingMode::HALF_UP)),
                'highest_spending_day' => $dailyTrend->sortByDesc(fn (array $row): float => (float) $row['expense'])->first(),
                'lowest_spending_day' => $dailyTrend->sortBy(fn (array $row): float => (float) $row['expense'])->first(),
                'recurring_expense' => $this->recurringExpenseTotal($userId, $fromDate, $toDate),
            ],
            'trend_rows' => $dailyTrend,
            'category_rows' => $categoryRows->sortByDesc(fn (array $row): float => (float) $row['difference'])->values(),
            'daily_cost_rows' => $dailyTrend,
            'account_balance_rows' => $this->accountDailyBalances($userId, $fromDate, $toDate),
            'leakage' => [
                ...$leakage,
                'repeated_similar_expenses' => $this->repeatedSimilarExpenses($userId, $fromDate, $toDate),
            ],
            'account_rows' => $this->accountBreakdown($userId, $fromDate, $toDate),
            'comparison' => $this->periodComparison($summary['total_expense'], $this->sumAmounts($categoryRows, 'previous_expense')),
            'anomalies' => $anomalies,
            'alerts' => $this->weeklyAlerts($summary, $categoryRows),
            'opportunities' => $categoryRows->sortByDesc('opportunity_score')->take(8)->values(),
            'plan' => [
                'last_3_bi_week_average' => $this->money($biWeeklyAverage),
                'current_bi_week' => $summary['total_expense'],
                'target_low' => (string) $targetLow,
                'target_high' => (string) $targetHigh,
            ],
            'definitions' => $this->reportDefinitions('bi-weekly'),
            'section_guides' => $this->sectionGuides('bi-weekly'),
            'calculation_hints' => $this->calculationHints('bi-weekly'),
            'recommendations' => $this->weeklyRecommendations($categoryRows, $leakage, $anomalies, (string) $targetHigh),
        ];
    }

    private function monthly(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $summary = $this->summary($userId, $fromDate, $toDate);
        $categoryRows = $this->categoryAnalysis($userId, $fromDate, $toDate);
        $monthlyTrend = $this->monthlyTrend($userId, $toDate, (int) config('expense_intelligence_reports.lookback_months'));
        $leakage = $this->smallExpenseLeakage($userId, $fromDate, $toDate);
        $anomalies = $this->anomalies($userId, $fromDate, $toDate);
        $forecast = $this->nextMonthForecast($userId, $toDate, $monthlyTrend);
        $recurring = $this->recurringAnalysis($userId, $summary['total_income']);

        return [
            'summary' => [
                ...$summary,
                'net_savings' => $summary['net_cash_flow'],
                'savings_percentage' => BigDecimal::of($summary['total_income'])->isZero()
                    ? '0.0'
                    : $this->percentage($summary['net_cash_flow'], $summary['total_income']),
                'average_daily_expense' => $this->money(BigDecimal::of($summary['total_expense'])->dividedBy((string) max(1, $fromDate->diffInDays($toDate) + 1), 2, RoundingMode::HALF_UP)),
                'highest_expense_category' => $categoryRows->sortByDesc(fn (array $row): float => (float) $row['current_expense'])->first()['category_name'] ?? null,
            ],
            'trend_rows' => $monthlyTrend,
            'daily_cost_rows' => $this->dailyTrend($userId, $fromDate, $toDate),
            'account_balance_rows' => $this->accountDailyBalances($userId, $fromDate, $toDate),
            'category_rows' => $categoryRows,
            'category_trends' => $this->categoryTrends($userId, $toDate, (int) config('expense_intelligence_reports.lookback_months')),
            'recurring' => $recurring,
            'leakage' => [
                ...$leakage,
                'cash_withdrawals' => $this->withdrawalTotal($userId, $fromDate, $toDate),
                'repeated_similar_expenses' => $this->repeatedSimilarExpenses($userId, $fromDate, $toDate),
            ],
            'behavior' => $this->behaviorAnalysis($userId, $fromDate, $toDate),
            'account_rows' => $this->accountBreakdown($userId, $fromDate, $toDate),
            'comparison' => $this->periodComparison($summary['total_expense'], $this->sumAmounts($categoryRows, 'previous_expense')),
            'anomalies' => $anomalies,
            'opportunities' => $categoryRows->sortByDesc('opportunity_score')->take(10)->values(),
            'potential_savings' => $this->potentialSavingsRange($categoryRows),
            'forecast' => $forecast,
            'budget' => $this->nextMonthBudget($forecast, $summary['total_income'], $recurring['monthly_commitment']),
            'risks' => $this->nextMonthRisks($categoryRows, $recurring, $leakage),
            'definitions' => $this->reportDefinitions('monthly'),
            'section_guides' => $this->sectionGuides('monthly'),
            'calculation_hints' => $this->calculationHints('monthly'),
            'recommendations' => $this->monthlyRecommendations($categoryRows, $recurring, $leakage, $forecast),
        ];
    }

    private function summary(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $income = $this->transactionSum($userId, $fromDate, $toDate, [TransactionType::INCOME->value]);
        $expense = $this->transactionSum($userId, $fromDate, $toDate, [TransactionType::EXPENSE->value, TransactionType::RECURRING->value]);
        $expenseTransactions = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString());

        $transactionCount = (int) (clone $expenseTransactions)->count();
        $largest = (clone $expenseTransactions)
            ->select([
                'transactions.amount',
                'transactions.note',
                'transactions.transaction_date',
                'categories.name as category_name',
                'accounts.name as account_name',
            ])
            ->orderByDesc('transactions.amount')
            ->first();

        return [
            'total_income' => $this->money($income),
            'total_expense' => $this->money($expense),
            'net_cash_flow' => $this->money(BigDecimal::of($income)->minus($expense)),
            'transaction_count' => $transactionCount,
            'average_transaction' => $transactionCount === 0 ? '0.00' : $this->money(BigDecimal::of($expense)->dividedBy((string) $transactionCount, 2, RoundingMode::HALF_UP)),
            'largest_transaction' => $largest ? [
                'amount' => $this->money($largest->amount),
                'transaction_date' => $largest->transaction_date,
                'category_name' => $largest->category_name,
                'account_name' => $largest->account_name,
                'note' => $largest->note,
            ] : null,
            'categories_used' => (int) (clone $expenseTransactions)->distinct('transactions.category_id')->count('transactions.category_id'),
        ];
    }

    private function categoryAnalysis(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        $periodDays = max(1, $fromDate->diffInDays($toDate) + 1);
        $previousFrom = $fromDate->subDays($periodDays);
        $previousTo = $fromDate->subDay()->endOfDay();
        $categories = DB::table('categories')
            ->where('user_id', $userId)
            ->where('type', TransactionType::EXPENSE->value)
            ->orderBy('name')
            ->get();
        $current = $this->categoryTotals($userId, $fromDate, $toDate);
        $previous = $this->categoryTotals($userId, $previousFrom, $previousTo);
        $historical = $this->historicalCategoryAverage($userId, $fromDate);
        $recurring = $this->recurringByCategory($userId);
        $total = $this->sumAmounts($current, 'total_expense');
        $maxCount = max((int) $current->max('transaction_count'), 1);

        return $categories->map(function (object $category) use ($current, $previous, $historical, $recurring, $total, $maxCount): array {
            $currentRow = $current->get($category->id);
            $previousRow = $previous->get($category->id);
            $historicalRow = $historical->get($category->id);
            $recurringRow = $recurring->get($category->id);
            $currentExpense = $this->money($currentRow->total_expense ?? '0');
            $previousExpense = $this->money($previousRow->total_expense ?? '0');
            $historicalAverage = $this->money($historicalRow->historical_average ?? '0');
            $difference = $this->money(BigDecimal::of($currentExpense)->minus($previousExpense));
            $growthPercentage = BigDecimal::of($previousExpense)->isZero() ? null : $this->percentage($difference, $previousExpense);
            $historicalDeviation = BigDecimal::of($historicalAverage)->isZero()
                ? null
                : $this->percentage(BigDecimal::of($currentExpense)->minus($historicalAverage), $historicalAverage);
            $transactionCount = (int) ($currentRow->transaction_count ?? 0);
            $expenseShare = $this->percentage($currentExpense, $total);
            $opportunityScore = $this->opportunityScore($expenseShare, $growthPercentage, $historicalDeviation, $transactionCount, $maxCount);

            return [
                'category_id' => (int) $category->id,
                'category_name' => $category->name,
                'current_expense' => $currentExpense,
                'previous_expense' => $previousExpense,
                'historical_average' => $historicalAverage,
                'difference' => $difference,
                'growth_percentage' => $growthPercentage,
                'historical_deviation_percentage' => $historicalDeviation,
                'expense_share' => $expenseShare,
                'transaction_count' => $transactionCount,
                'average_transaction' => $transactionCount === 0 ? '0.00' : $this->money(BigDecimal::of($currentExpense)->dividedBy((string) $transactionCount, 2, RoundingMode::HALF_UP)),
                'recurring_commitment' => $this->money($recurringRow->recurring_commitment ?? '0'),
                'opportunity_score' => $opportunityScore,
                'potential_saving' => $this->potentialSaving($currentExpense, $opportunityScore),
            ];
        })->sortByDesc(fn (array $row): int => $row['opportunity_score'])->values();
    }

    private function categoryTotals(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->select(['category_id', DB::raw('COUNT(id) as transaction_count'), DB::raw('SUM(amount) as total_expense')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereNotNull('category_id')
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function largestExpenses(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate, int $limit): Collection
    {
        return DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->select([
                'transactions.amount',
                'transactions.note',
                'transactions.transaction_date',
                'categories.name as category_name',
                'accounts.name as account_name',
            ])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->orderByDesc('transactions.amount')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'amount' => $this->money($row->amount),
                'note' => $row->note,
                'transaction_date' => $row->transaction_date,
                'category_name' => $row->category_name,
                'account_name' => $row->account_name,
            ]);
    }

    private function smallExpenseLeakage(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $threshold = (string) config('expense_intelligence_reports.small_transaction_threshold', '500.00');
        $row = DB::table('transactions')
            ->select([DB::raw('COUNT(id) as transaction_count'), DB::raw('SUM(amount) as total_amount'), DB::raw('AVG(amount) as average_amount')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->where('amount', '<', $threshold)
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->first();

        return [
            'threshold' => $this->money($threshold),
            'transaction_count' => (int) ($row->transaction_count ?? 0),
            'total_amount' => $this->money($row->total_amount ?? '0'),
            'average_amount' => $this->money($row->average_amount ?? '0'),
        ];
    }

    private function anomalies(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        $multiplier = (float) config('expense_intelligence_reports.anomaly_multiplier', '2.50');
        $minimumSamples = (int) config('expense_intelligence_reports.minimum_anomaly_samples', 5);
        $historical = DB::table('transactions')
            ->select(['category_id', DB::raw('AVG(amount) as average_amount'), DB::raw('COUNT(id) as sample_count')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereNotNull('category_id')
            ->whereDate('transaction_date', '<', $fromDate->toDateString())
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        return DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->select(['transactions.category_id', 'transactions.amount', 'transactions.note', 'transactions.transaction_date', 'categories.name as category_name'])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereNotNull('transactions.category_id')
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->get()
            ->filter(function (object $transaction) use ($historical, $multiplier, $minimumSamples): bool {
                $baseline = $historical->get($transaction->category_id);

                return $baseline
                    && (int) $baseline->sample_count >= $minimumSamples
                    && (float) $transaction->amount >= ((float) $baseline->average_amount * $multiplier);
            })
            ->map(function (object $transaction) use ($historical): array {
                $baseline = $historical->get($transaction->category_id);

                return [
                    'category_name' => $transaction->category_name,
                    'amount' => $this->money($transaction->amount),
                    'category_average' => $this->money($baseline->average_amount),
                    'deviation_percentage' => $this->percentage(BigDecimal::of($transaction->amount)->minus($baseline->average_amount), $baseline->average_amount),
                    'transaction_date' => $transaction->transaction_date,
                    'note' => $transaction->note,
                ];
            })
            ->values();
    }

    private function dailyTrend(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        $rows = DB::table('transactions')
            ->select([DB::raw('DATE(transaction_date) as day'), DB::raw('SUM(amount) as expense'), DB::raw('COUNT(id) as transaction_count')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->get()
            ->keyBy('day');

        $trend = collect();
        for ($date = $fromDate; $date->lessThanOrEqualTo($toDate); $date = $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $count = (int) ($row->transaction_count ?? 0);
            $expense = $this->money($row->expense ?? '0');
            $trend->push([
                'label' => $date->format('D, d M'),
                'expense' => $expense,
                'transaction_count' => $count,
                'average' => $count === 0 ? '0.00' : $this->money(BigDecimal::of($expense)->dividedBy((string) $count, 2, RoundingMode::HALF_UP)),
            ]);
        }

        return $trend;
    }

    private function dailyHoursTrend(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->select([DB::raw('HOUR(transaction_date) as hour'), DB::raw('SUM(amount) as expense'), DB::raw('COUNT(id) as transaction_count')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy(DB::raw('HOUR(transaction_date)'))
            ->orderBy('hour')
            ->get()
            ->map(fn (object $row): array => [
                'label' => sprintf('%02d:00', (int) $row->hour),
                'expense' => $this->money($row->expense),
                'transaction_count' => (int) $row->transaction_count,
            ]);
    }

    private function monthlyTrend(int $userId, CarbonImmutable $toDate, int $months): Collection
    {
        $startDate = $toDate->startOfMonth()->subMonths($months - 1);
        $rows = DB::table('transactions')
            ->select([
                DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key"),
                DB::raw("SUM(CASE WHEN type = '".TransactionType::INCOME->value."' THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN type IN ('".TransactionType::EXPENSE->value."', '".TransactionType::RECURRING->value."') THEN amount ELSE 0 END) as expense"),
                DB::raw('COUNT(id) as transaction_count'),
            ])
            ->where('user_id', $userId)
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy(DB::raw("DATE_FORMAT(transaction_date, '%Y-%m')"))
            ->get()
            ->keyBy('month_key');

        $trend = collect();
        for ($date = $startDate; $date->lessThanOrEqualTo($toDate); $date = $date->addMonth()) {
            $row = $rows->get($date->format('Y-m'));
            $income = $this->money($row->income ?? '0');
            $expense = $this->money($row->expense ?? '0');
            $savings = $this->money(BigDecimal::of($income)->minus($expense));
            $trend->push([
                'label' => $date->format('M Y'),
                'month_key' => $date->format('Y-m'),
                'income' => $income,
                'expense' => $expense,
                'savings' => $savings,
                'savings_percentage' => BigDecimal::of($income)->isZero() ? '0.0' : $this->percentage($savings, $income),
                'transaction_count' => (int) ($row->transaction_count ?? 0),
            ]);
        }

        return $trend;
    }

    private function categoryTrends(int $userId, CarbonImmutable $toDate, int $months): Collection
    {
        return DB::table('transactions')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select([
                'transactions.category_id',
                'categories.name as category_name',
                DB::raw("DATE_FORMAT(transactions.transaction_date, '%Y-%m') as month_key"),
                DB::raw('SUM(transactions.amount) as expense'),
            ])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transactions.transaction_date', '>=', $toDate->startOfMonth()->subMonths($months - 1)->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->groupBy('transactions.category_id', 'categories.name', DB::raw("DATE_FORMAT(transactions.transaction_date, '%Y-%m')"))
            ->orderBy('categories.name')
            ->get()
            ->groupBy('category_id')
            ->map(fn (Collection $rows): array => [
                'category_name' => $rows->first()->category_name,
                'months' => $rows->map(fn (object $row): array => [
                    'month' => $row->month_key,
                    'expense' => $this->money($row->expense),
                ])->values(),
                'is_continuously_increasing' => $this->isContinuouslyIncreasing($rows->pluck('expense')),
            ])
            ->values();
    }

    private function repeatedSimilarExpenses(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->select([
                'transactions.category_id',
                'categories.name as category_name',
                DB::raw("LOWER(COALESCE(transactions.note, '')) as note_pattern"),
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.amount) as total_amount'),
                DB::raw('AVG(transactions.amount) as average_amount'),
            ])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->groupBy('transactions.category_id', 'categories.name', DB::raw("LOWER(COALESCE(transactions.note, ''))"))
            ->havingRaw('COUNT(transactions.id) >= 2')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'category_name' => $row->category_name,
                'note_pattern' => $row->note_pattern,
                'transaction_count' => (int) $row->transaction_count,
                'total_amount' => $this->money($row->total_amount),
                'average_amount' => $this->money($row->average_amount),
            ]);
    }

    private function behaviorAnalysis(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $weekdayRows = DB::table('transactions')
            ->select([DB::raw('DAYNAME(transaction_date) as day_name'), DB::raw('AVG(amount) as average_expense'), DB::raw('SUM(amount) as total_expense')])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy(DB::raw('DAYNAME(transaction_date)'))
            ->orderByDesc('average_expense')
            ->get();
        $periodRows = DB::table('transactions')
            ->select([
                DB::raw("CASE WHEN HOUR(transaction_date) BETWEEN 5 AND 11 THEN 'Morning' WHEN HOUR(transaction_date) BETWEEN 12 AND 16 THEN 'Afternoon' WHEN HOUR(transaction_date) BETWEEN 17 AND 21 THEN 'Evening' ELSE 'Night' END as period_label"),
                DB::raw('SUM(amount) as total_expense'),
                DB::raw('COUNT(id) as transaction_count'),
            ])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy('period_label')
            ->orderByDesc('total_expense')
            ->get();

        return [
            'highest_spending_day' => $weekdayRows->first() ? [
                'label' => $weekdayRows->first()->day_name,
                'average_expense' => $this->money($weekdayRows->first()->average_expense),
                'total_expense' => $this->money($weekdayRows->first()->total_expense),
            ] : null,
            'highest_spending_period' => $periodRows->first() ? [
                'label' => $periodRows->first()->period_label,
                'total_expense' => $this->money($periodRows->first()->total_expense),
                'transaction_count' => (int) $periodRows->first()->transaction_count,
            ] : null,
        ];
    }

    private function nextMonthForecast(int $userId, CarbonImmutable $toDate, Collection $monthlyTrend): array
    {
        $lastThree = $monthlyTrend->pluck('expense')->filter(fn (string $expense): bool => BigDecimal::of($expense)->isGreaterThan('0'))->take(-3);
        $average = $lastThree->isEmpty() ? '0.00' : $this->money($lastThree->reduce(fn (BigDecimal $sum, string $expense): BigDecimal => $sum->plus($expense), BigDecimal::zero())->dividedBy((string) $lastThree->count(), 2, RoundingMode::HALF_UP));
        $last = $monthlyTrend->slice(-1)->first()['expense'] ?? '0.00';
        $previous = $monthlyTrend->slice(-2, 1)->first()['expense'] ?? $last;
        $trendAdjustment = $this->money(BigDecimal::of($last)->minus($previous)->dividedBy('2', 2, RoundingMode::HALF_UP));
        $recurring = $this->recurringExpenseTotal($userId, $toDate->startOfMonth(), $toDate->endOfMonth());
        $expected = BigDecimal::of($average)->plus($trendAdjustment);

        if ($expected->isLessThan($recurring)) {
            $expected = BigDecimal::of($recurring);
        }

        $expected = $expected->toScale(2, RoundingMode::HALF_UP);

        return [
            'basis' => 'Last 3-month average plus half of the latest month-over-month trend, floored by active recurring commitments.',
            'last_3_month_average' => $average,
            'trend_adjustment' => $trendAdjustment,
            'recurring_commitment' => $recurring,
            'expected' => (string) $expected,
            'range_low' => (string) $expected->multipliedBy('0.92')->toScale(2, RoundingMode::HALF_UP),
            'range_high' => (string) $expected->multipliedBy('1.08')->toScale(2, RoundingMode::HALF_UP),
        ];
    }

    private function nextMonthBudget(array $forecast, string $income, string $recurring): array
    {
        $expenseTarget = BigDecimal::of($forecast['expected']);
        $incomeValue = BigDecimal::of($income);

        if ($incomeValue->isGreaterThan('0') && $expenseTarget->isGreaterThan($incomeValue)) {
            $expenseTarget = $incomeValue;
        }

        $savingsTarget = $incomeValue->minus($expenseTarget);
        $discretionaryLimit = $expenseTarget->minus($recurring);

        return [
            'expense_target' => (string) $expenseTarget->toScale(2, RoundingMode::HALF_UP),
            'savings_target' => (string) $savingsTarget->toScale(2, RoundingMode::HALF_UP),
            'discretionary_limit' => (string) ($discretionaryLimit->isLessThan('0') ? BigDecimal::zero() : $discretionaryLimit)->toScale(2, RoundingMode::HALF_UP),
        ];
    }

    private function recurringAnalysis(int $userId, string $income): array
    {
        $rows = DB::table('recurring_expenses')
            ->join('categories', 'categories.id', '=', 'recurring_expenses.category_id')
            ->select(['recurring_expenses.title', 'recurring_expenses.amount', 'categories.name as category_name'])
            ->where('recurring_expenses.user_id', $userId)
            ->where('recurring_expenses.is_active', true)
            ->orderByDesc('recurring_expenses.amount')
            ->get();
        $commitment = $this->sumAmounts($rows, 'amount');

        return [
            'active_count' => $rows->count(),
            'monthly_commitment' => $commitment,
            'largest' => $rows->first() ? [
                'title' => $rows->first()->title,
                'category_name' => $rows->first()->category_name,
                'amount' => $this->money($rows->first()->amount),
            ] : null,
            'income_commitment_percentage' => BigDecimal::of($income)->isZero() ? '0.0' : $this->percentage($commitment, $income),
        ];
    }

    private function recurringExpenseTotal(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): string
    {
        return $this->money(DB::table('transactions')
            ->where('user_id', $userId)
            ->where('type', TransactionType::RECURRING->value)
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->sum('amount'));
    }

    private function withdrawalTotal(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): string
    {
        return $this->money(DB::table('transactions')
            ->where('user_id', $userId)
            ->where('type', TransactionType::WITHDRAW->value)
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->sum('amount'));
    }

    private function historicalDailyAverage(int $userId, CarbonImmutable $beforeDate): string
    {
        $row = DB::query()
            ->fromSub(function ($query) use ($userId, $beforeDate): void {
                $query->from('transactions')
                    ->select([DB::raw('DATE(transaction_date) as expense_day'), DB::raw('SUM(amount) as daily_total')])
                    ->where('user_id', $userId)
                    ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
                    ->whereDate('transaction_date', '<', $beforeDate->toDateString())
                    ->groupBy(DB::raw('DATE(transaction_date)'));
            }, 'daily_expenses')
            ->select(DB::raw('AVG(daily_total) as daily_average'))
            ->first();

        return $this->money($row->daily_average ?? '0');
    }

    private function historicalCategoryAverage(int $userId, CarbonImmutable $fromDate): Collection
    {
        return DB::query()
            ->fromSub(function ($query) use ($userId, $fromDate): void {
                $query->from('transactions')
                    ->select(['category_id', DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key"), DB::raw('SUM(amount) as monthly_total')])
                    ->where('user_id', $userId)
                    ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
                    ->whereNotNull('category_id')
                    ->whereDate('transaction_date', '<', $fromDate->toDateString())
                    ->groupBy('category_id', DB::raw("DATE_FORMAT(transaction_date, '%Y-%m')"));
            }, 'category_monthly_totals')
            ->select(['category_id', DB::raw('AVG(monthly_total) as historical_average')])
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function recurringByCategory(int $userId): Collection
    {
        return DB::table('recurring_expenses')
            ->select(['category_id', DB::raw('SUM(amount) as recurring_commitment')])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function transactionSum(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate, array $types): string
    {
        return $this->money(DB::table('transactions')
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->sum('amount'));
    }

    private function recentPeriodAverage(int $userId, CarbonImmutable $beforeDate, string $period, int $periodCount): string
    {
        $totals = collect();
        $periodDays = $period === 'bi-week' ? 14 : 7;

        for ($index = 1; $index <= $periodCount; $index++) {
            $periodTo = $beforeDate->subDays(($index - 1) * $periodDays + 1)->endOfDay();
            $periodFrom = $periodTo->subDays($periodDays - 1)->startOfDay();
            $totals->push($this->transactionSum($userId, $periodFrom, $periodTo, [
                TransactionType::EXPENSE->value,
                TransactionType::RECURRING->value,
            ]));
        }

        if ($totals->isEmpty()) {
            return '0.00';
        }

        return $this->money($totals->reduce(
            fn (BigDecimal $sum, string $total): BigDecimal => $sum->plus($total),
            BigDecimal::zero()
        )->dividedBy((string) $totals->count(), 2, RoundingMode::HALF_UP));
    }

    private function accountBreakdown(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->select([
                'transactions.account_id',
                'accounts.name as account_name',
                'accounts.type as account_type',
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.amount) as total_expense'),
                DB::raw('AVG(transactions.amount) as average_expense'),
            ])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->groupBy('transactions.account_id', 'accounts.name', 'accounts.type')
            ->orderByDesc('total_expense')
            ->get()
            ->map(fn (object $row): array => [
                'account_id' => (int) $row->account_id,
                'account_name' => $row->account_name,
                'account_type' => $row->account_type,
                'transaction_count' => (int) $row->transaction_count,
                'total_expense' => $this->money($row->total_expense),
                'average_expense' => $this->money($row->average_expense),
            ]);
    }

    private function accountDailyBalances(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->select([
                'transactions.account_id',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'transactions.balance_before',
                'transactions.balance_after',
                'transactions.transaction_date',
                DB::raw('DATE(transactions.transaction_date) as balance_date'),
            ])
            ->where('transactions.user_id', $userId)
            ->whereDate('transactions.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $toDate->toDateString())
            ->orderBy('transactions.transaction_date')
            ->orderBy('transactions.id')
            ->get()
            ->groupBy(fn (object $row): string => $row->balance_date.'-'.$row->account_id)
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $last = $rows->last();

                return [
                    'date' => $first->balance_date,
                    'label' => CarbonImmutable::parse($first->balance_date)->format('d M').' - '.$first->account_name,
                    'account_id' => (int) $first->account_id,
                    'account_name' => $first->account_name,
                    'account_type' => $first->account_type,
                    'opening_balance' => $this->money($first->balance_before),
                    'closing_balance' => $this->money($last->balance_after),
                    'movement' => $this->money(BigDecimal::of($last->balance_after)->minus($first->balance_before)),
                    'transaction_count' => $rows->count(),
                ];
            })
            ->sortBy([
                ['date', 'asc'],
                ['account_name', 'asc'],
            ])
            ->values();
    }

    private function periodComparison(string $currentExpense, string $previousExpense): array
    {
        $difference = BigDecimal::of($currentExpense)->minus($previousExpense)->toScale(2, RoundingMode::HALF_UP);

        return [
            'current_expense' => $this->money($currentExpense),
            'previous_expense' => $this->money($previousExpense),
            'difference' => (string) $difference,
            'growth_percentage' => BigDecimal::of($previousExpense)->isZero()
                ? null
                : $this->percentage($difference, $previousExpense),
        ];
    }

    private function calculationHints(string $frequency): array
    {
        $common = [
            'All category names, account names, transaction counts, and amounts are read from the database for the selected user and date range.',
            'Opportunity score = expense share score + positive growth score + relative frequency score + positive historical deviation score, capped at 100.',
            'Potential saving is an estimate: current category spending multiplied by a score-weighted configurable savings rate.',
            'Small expense leakage uses EXPENSE_INTELLIGENCE_SMALL_TRANSACTION_THRESHOLD from configuration.',
            'Anomalies require enough historical samples and compare transactions against category-level historical averages.',
        ];

        return match ($frequency) {
            'weekly' => [
                ...$common,
                'Next week target is calculated from the previous 4 completed weekly expense totals, plus/minus 5%.',
            ],
            'bi-weekly' => [
                ...$common,
                'Next bi-weekly target is calculated from the previous 3 completed 14-day expense totals, plus/minus 5%.',
            ],
            'monthly' => [
                ...$common,
                'Monthly graph uses EXPENSE_INTELLIGENCE_LOOKBACK_MONTHS; default is 12 months.',
                'Next month forecast = last 3-month average + half of latest month-over-month trend, floored by active recurring commitments.',
            ],
            default => [
                ...$common,
                'Daily comparison uses the average of historical daily expense totals before the report date.',
            ],
        };
    }

    private function reportDefinitions(string $frequency): array
    {
        $periodLabel = match ($frequency) {
            'daily' => 'selected day',
            'weekly' => 'selected week',
            'bi-weekly' => 'selected 14-day period',
            'monthly' => 'selected month',
            default => 'selected period',
        };

        return [
            [
                'name' => 'Total income',
                'definition' => "Sum of INCOME transactions during the {$periodLabel}.",
            ],
            [
                'name' => 'Total expense',
                'definition' => "Sum of EXPENSE and RECURRING transactions during the {$periodLabel}.",
            ],
            [
                'name' => 'Net cash flow',
                'definition' => 'Total income minus total expense. Positive means cash increased; negative means spending exceeded income.',
            ],
            [
                'name' => 'Average transaction',
                'definition' => 'Total expense divided by the number of expense transactions in the report period.',
            ],
            [
                'name' => 'Small Txn Threshold',
                'definition' => 'Transactions below '.config('expense_intelligence_reports.small_transaction_threshold', '500.00').' are counted as small expenses. Change EXPENSE_INTELLIGENCE_SMALL_TRANSACTION_THRESHOLD to adjust this.',
            ],
            [
                'name' => 'Money leakage',
                'definition' => 'The count, total, and average of small transactions. It shows when many small purchases become meaningful together.',
            ],
            [
                'name' => 'Previous period',
                'definition' => "The same number of days immediately before this {$periodLabel}. Growth and difference use this comparison period.",
            ],
            [
                'name' => 'Growth %',
                'definition' => '((Current period expense - previous period expense) / previous period expense) x 100. It is N/A when previous spending is zero.',
            ],
            [
                'name' => 'Historical average',
                'definition' => 'Average historical monthly spending for that category before the report start date. Daily reports also compare against historical daily average.',
            ],
            [
                'name' => 'Expense share',
                'definition' => '(Category expense / total report expense) x 100. Categories are read dynamically from the database.',
            ],
            [
                'name' => 'Opportunity score',
                'definition' => 'A 0-100 score: expense share up to 35 points + positive growth up to 25 + transaction frequency up to 20 + positive historical deviation up to 20.',
            ],
            [
                'name' => 'Potential saving',
                'definition' => 'Estimated only. Formula: current category expense x opportunity score x '.config('expense_intelligence_reports.max_savings_rate', '0.30').' / 100.',
            ],
            [
                'name' => 'Anomaly',
                'definition' => 'A transaction is flagged only when its category has at least '.config('expense_intelligence_reports.minimum_anomaly_samples', 5).' historical samples and the amount is at least '.config('expense_intelligence_reports.anomaly_multiplier', '2.50').'x that category average.',
            ],
            [
                'name' => 'Daily costing graph',
                'definition' => 'Groups EXPENSE and RECURRING transaction totals by each transaction date in the selected report period.',
            ],
            [
                'name' => 'Account balance graph',
                'definition' => 'For each account and day, opening balance is the first transaction balance_before and closing balance is the last transaction balance_after.',
            ],
        ];
    }

    private function sectionGuides(string $frequency): array
    {
        $period = match ($frequency) {
            'daily' => 'day',
            'weekly' => 'week',
            'bi-weekly' => '14-day period',
            'monthly' => 'month',
            default => 'period',
        };

        $guides = [
            [
                'section' => 'Summary',
                'definition' => "Shows income, expense, net cash flow, transaction count, average transaction, and used categories for the selected {$period}.",
            ],
            [
                'section' => 'Trend chart',
                'definition' => 'Shows day-by-day expense totals grouped by transaction date for the selected report period.',
            ],
            [
                'section' => 'Account balance chart',
                'definition' => 'Shows each account opening balance and closing balance for every day where that account had transactions.',
            ],
            [
                'section' => 'Category analysis',
                'definition' => 'Compares every database expense category against the previous equal-length period and historical category average.',
            ],
            [
                'section' => 'Opportunity ranking',
                'definition' => 'Ranks categories by score so the highest row is the strongest data-backed cost reduction candidate.',
            ],
            [
                'section' => 'Money leakage',
                'definition' => 'Adds up small repeated expenses under the configured threshold to reveal hidden cumulative spending.',
            ],
            [
                'section' => 'Anomaly detection',
                'definition' => 'Flags unusually large transactions only when enough historical category data exists.',
            ],
            [
                'section' => 'Recommendations',
                'definition' => 'Each recommendation is generated only from calculated report data such as top growth, leakage, anomaly, target, forecast, or recurring commitment.',
            ],
        ];

        if ($frequency === 'monthly') {
            $guides[] = [
                'section' => 'Forecast and budget',
                'definition' => 'Estimates next month using recent monthly averages, latest trend movement, and active recurring commitments.',
            ];
        }

        return $guides;
    }

    private function dailyInsights(array $summary, string $historicalAverage, ?string $increasePercentage, Collection $largestExpenses, array $leakage): array
    {
        $insights = [];

        if ($increasePercentage !== null && BigDecimal::of($increasePercentage)->isGreaterThan('0')) {
            $insights[] = "Spending was {$increasePercentage}% higher than historical daily average.";
        }

        if ($leakage['transaction_count'] > 0) {
            $insights[] = "{$leakage['transaction_count']} small transactions totaled {$leakage['total_amount']}.";
        }

        $largest = $largestExpenses->first();
        if ($largest && BigDecimal::of($summary['total_expense'])->isGreaterThan('0')) {
            $share = $this->percentage($largest['amount'], $summary['total_expense']);
            $insights[] = "The largest transaction represented {$share}% of today's expense.";
        }

        if ($historicalAverage === '0.00' && $insights === []) {
            $insights[] = 'Not enough historical spending data exists for comparison yet.';
        }

        return $insights;
    }

    private function dailyRecommendations(array $summary, string $historicalAverage, ?string $increasePercentage, array $leakage, Collection $anomalies): array
    {
        $recommendations = [];

        if ($increasePercentage !== null && BigDecimal::of($increasePercentage)->isGreaterThan(config('expense_intelligence_reports.significant_change_percentage'))) {
            $recommendations[] = "Tomorrow target: keep spending close to {$historicalAverage}, because today's expense was {$increasePercentage}% above the historical daily average.";
        }

        if ($leakage['transaction_count'] >= 3) {
            $recommendations[] = "Limit small purchases below {$leakage['threshold']}; {$leakage['transaction_count']} such transactions totaled {$leakage['total_amount']} today.";
        }

        if ($anomalies->isNotEmpty()) {
            $first = $anomalies->first();
            $recommendations[] = "Review {$first['category_name']} before repeating similar spending; {$first['amount']} was {$first['deviation_percentage']}% above its category average.";
        }

        if ($recommendations === []) {
            $recommendations[] = "No high-risk pattern crossed the configured thresholds; keep tomorrow spending near today's total of {$summary['total_expense']} or lower.";
        }

        return $recommendations;
    }

    private function weeklyAlerts(array $summary, Collection $categoryRows): array
    {
        $alerts = [];
        $totalPrevious = $this->sumAmounts($categoryRows, 'previous_expense');

        if (BigDecimal::of($totalPrevious)->isGreaterThan('0')) {
            $growth = $this->percentage(BigDecimal::of($summary['total_expense'])->minus($totalPrevious), $totalPrevious);
            if (BigDecimal::of($growth)->isGreaterThan(config('expense_intelligence_reports.significant_change_percentage'))) {
                $alerts[] = "Spending increased {$growth}% compared with the previous week.";
            }
        }

        $largestIncrease = $categoryRows->sortByDesc(fn (array $row): float => (float) $row['difference'])->first();
        if ($largestIncrease && BigDecimal::of($largestIncrease['difference'])->isGreaterThan('0')) {
            $alerts[] = "{$largestIncrease['category_name']} increased by {$largestIncrease['difference']} compared with the previous week.";
        }

        return $alerts;
    }

    private function weeklyRecommendations(Collection $categoryRows, array $leakage, Collection $anomalies, string $targetHigh): array
    {
        $recommendations = ["Keep next week's total spending near or below {$targetHigh}."];
        $topOpportunity = $categoryRows->sortByDesc('opportunity_score')->first();

        if ($topOpportunity && $topOpportunity['opportunity_score'] > 0) {
            $recommendations[] = "Focus first on {$topOpportunity['category_name']}; it has score {$topOpportunity['opportunity_score']}/100, current spending {$topOpportunity['current_expense']}, and estimated saving {$topOpportunity['potential_saving']}.";
        }

        $topGrowth = $categoryRows
            ->filter(fn (array $row): bool => $row['growth_percentage'] !== null)
            ->sortByDesc(fn (array $row): float => (float) $row['growth_percentage'])
            ->first();

        if ($topGrowth && BigDecimal::of($topGrowth['growth_percentage'])->isGreaterThan('0')) {
            $recommendations[] = "Monitor {$topGrowth['category_name']}; it grew {$topGrowth['growth_percentage']}% with a difference of {$topGrowth['difference']} versus the previous period.";
        }

        if ($leakage['transaction_count'] > 0) {
            $recommendations[] = "Control small transactions below {$leakage['threshold']}; {$leakage['transaction_count']} transactions totaled {$leakage['total_amount']}.";
        }

        if ($anomalies->isNotEmpty()) {
            $first = $anomalies->first();
            $recommendations[] = "Check unusual {$first['category_name']} spending; {$first['amount']} was {$first['deviation_percentage']}% above its category average.";
        }

        return array_slice($recommendations, 0, 5);
    }

    private function monthlyRecommendations(Collection $categoryRows, array $recurring, array $leakage, array $forecast): array
    {
        $recommendations = ["Next month expense target: keep spending near {$forecast['expected']} within the estimated range {$forecast['range_low']} - {$forecast['range_high']}."];
        $topOpportunity = $categoryRows->sortByDesc('opportunity_score')->first();

        if ($topOpportunity) {
            $recommendations[] = "Highest cost reduction focus: {$topOpportunity['category_name']} scored {$topOpportunity['opportunity_score']}/100 with current spending {$topOpportunity['current_expense']} and estimated saving {$topOpportunity['potential_saving']}.";
        }

        if (BigDecimal::of($recurring['monthly_commitment'])->isGreaterThan('0')) {
            $recommendations[] = "Review recurring expenses before the next billing cycle; active commitments total {$recurring['monthly_commitment']} and consume {$recurring['income_commitment_percentage']}% of this month's income.";
        }

        if ($leakage['transaction_count'] > 0) {
            $recommendations[] = "Reduce small expenses below {$leakage['threshold']}; {$leakage['transaction_count']} small transactions totaled {$leakage['total_amount']} this month.";
        }

        $recommendations[] = "Use the forecast basis in this report before setting the final budget: {$forecast['basis']}";

        return array_slice($recommendations, 0, 6);
    }

    private function nextMonthRisks(Collection $categoryRows, array $recurring, array $leakage): array
    {
        $risks = [];
        $growth = $categoryRows
            ->filter(fn (array $row): bool => $row['growth_percentage'] !== null && BigDecimal::of($row['growth_percentage'])->isGreaterThan('0'))
            ->sortByDesc(fn (array $row): float => (float) $row['growth_percentage'])
            ->first();

        if ($growth) {
            $risks[] = "{$growth['category_name']} increased this period and may cause overspending next month.";
        }

        if (BigDecimal::of($recurring['income_commitment_percentage'])->isGreaterThan('0')) {
            $risks[] = "Recurring expenses consume {$recurring['income_commitment_percentage']}% of current income.";
        }

        if ($leakage['transaction_count'] > 0) {
            $risks[] = "Small transactions totaled {$leakage['total_amount']} this month.";
        }

        return $risks;
    }

    private function potentialSavingsRange(Collection $categoryRows): array
    {
        $base = BigDecimal::of($this->sumAmounts($categoryRows, 'potential_saving'));

        return [
            'low' => (string) $base->multipliedBy('0.80')->toScale(2, RoundingMode::HALF_UP),
            'high' => (string) $base->multipliedBy('1.20')->toScale(2, RoundingMode::HALF_UP),
            'annual_low' => (string) $base->multipliedBy('0.80')->multipliedBy('12')->toScale(2, RoundingMode::HALF_UP),
            'annual_high' => (string) $base->multipliedBy('1.20')->multipliedBy('12')->toScale(2, RoundingMode::HALF_UP),
        ];
    }

    private function opportunityScore(string $share, ?string $growth, ?string $deviation, int $count, int $maxCount): int
    {
        // Formula: expense share 35 + positive growth 25 + relative frequency 20 + positive historical deviation 20.
        $shareScore = min(35.0, (float) $share * 0.35);
        $growthScore = $growth === null ? 0.0 : min(25.0, max(0.0, (float) $growth) * 0.25);
        $frequencyScore = min(20.0, ($count / $maxCount) * 20.0);
        $deviationScore = $deviation === null ? 0.0 : min(20.0, max(0.0, (float) $deviation) * 0.20);

        return (int) round(min(100.0, $shareScore + $growthScore + $frequencyScore + $deviationScore));
    }

    private function potentialSaving(string $expense, int $score): string
    {
        $rate = BigDecimal::of((string) config('expense_intelligence_reports.max_savings_rate', '0.30'))
            ->multipliedBy((string) $score)
            ->dividedBy('100', 6, RoundingMode::HALF_UP);

        return $this->money(BigDecimal::of($expense)->multipliedBy($rate));
    }

    private function isContinuouslyIncreasing(Collection $amounts): bool
    {
        if ($amounts->count() < 3) {
            return false;
        }

        $values = $amounts->values();
        for ($index = 1; $index < $values->count(); $index++) {
            if (BigDecimal::of((string) $values[$index])->isLessThanOrEqualTo((string) $values[$index - 1])) {
                return false;
            }
        }

        return true;
    }

    private function sumAmounts(Collection $rows, string $key): string
    {
        return $this->money($rows->reduce(
            fn (BigDecimal $sum, object|array $row): BigDecimal => $sum->plus((string) data_get($row, $key, '0')),
            BigDecimal::zero()
        ));
    }

    private function percentage(BigDecimal|string $value, BigDecimal|string $total): string
    {
        $total = BigDecimal::of($total);

        if ($total->isZero()) {
            return '0.0';
        }

        return (string) BigDecimal::of($value)->dividedBy($total, 4, RoundingMode::HALF_UP)->multipliedBy('100')->toScale(1, RoundingMode::HALF_UP);
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP);
    }
}
