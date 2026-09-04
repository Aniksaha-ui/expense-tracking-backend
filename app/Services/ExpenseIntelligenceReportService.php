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
            'monthly' => $this->monthly($user->id, $fromDate, $toDate),
            default => throw new InvalidArgumentException('Invalid report frequency.'),
        };
    }

    public function title(string $frequency): string
    {
        return match ($frequency) {
            'daily' => 'Daily Expense Intelligence Report',
            'weekly' => 'Weekly Expense Intelligence Report',
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
            'chart_rows' => $this->chartRows($categoryRows, 'current_expense', (int) config('expense_intelligence_reports.top_categories')),
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

        return [
            'summary' => [
                ...$summary,
                'average_daily_expense' => $this->money(BigDecimal::of($summary['total_expense'])->dividedBy('7', 2, RoundingMode::HALF_UP)),
                'highest_spending_day' => $dailyTrend->sortByDesc(fn (array $row): float => (float) $row['expense'])->first(),
                'lowest_spending_day' => $dailyTrend->sortBy(fn (array $row): float => (float) $row['expense'])->first(),
                'recurring_expense' => $this->recurringExpenseTotal($userId, $fromDate, $toDate),
            ],
            'trend_rows' => $dailyTrend,
            'category_rows' => $categoryRows->sortByDesc(fn (array $row): float => (float) $row['difference'])->values(),
            'chart_rows' => $dailyTrend,
            'top_categories' => $categoryRows->sortByDesc(fn (array $row): float => (float) $row['current_expense'])->take(5)->values(),
            'leakage' => [
                ...$leakage,
                'repeated_similar_expenses' => $this->repeatedSimilarExpenses($userId, $fromDate, $toDate),
            ],
            'anomalies' => $anomalies,
            'alerts' => $this->weeklyAlerts($summary, $categoryRows),
            'opportunities' => $categoryRows->sortByDesc('opportunity_score')->take(5)->values(),
            'plan' => [
                'last_4_week_average' => $this->money($weekAverage),
                'current_week' => $summary['total_expense'],
                'target_low' => (string) $targetLow,
                'target_high' => (string) $targetHigh,
            ],
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
            'category_rows' => $categoryRows,
            'category_trends' => $this->categoryTrends($userId, $toDate, (int) config('expense_intelligence_reports.lookback_months')),
            'recurring' => $recurring,
            'leakage' => [
                ...$leakage,
                'cash_withdrawals' => $this->withdrawalTotal($userId, $fromDate, $toDate),
                'repeated_similar_expenses' => $this->repeatedSimilarExpenses($userId, $fromDate, $toDate),
            ],
            'behavior' => $this->behaviorAnalysis($userId, $fromDate, $toDate),
            'anomalies' => $anomalies,
            'opportunities' => $categoryRows->sortByDesc('opportunity_score')->take(10)->values(),
            'potential_savings' => $this->potentialSavingsRange($categoryRows),
            'forecast' => $forecast,
            'budget' => $this->nextMonthBudget($forecast, $summary['total_income'], $recurring['monthly_commitment']),
            'risks' => $this->nextMonthRisks($categoryRows, $recurring, $leakage),
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
            $recommendations[] = "Try to keep tomorrow's spending close to the historical daily average of {$historicalAverage}.";
        }

        if ($leakage['transaction_count'] >= 3) {
            $recommendations[] = 'Review repeated small purchases before making similar transactions tomorrow.';
        }

        if ($anomalies->isNotEmpty()) {
            $recommendations[] = 'Review unusually large transactions before repeating similar spending.';
        }

        return $recommendations ?: ['Keep tomorrow spending aligned with your recent database-backed average.'];
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
        $topGrowth = $categoryRows
            ->filter(fn (array $row): bool => $row['growth_percentage'] !== null)
            ->sortByDesc(fn (array $row): float => (float) $row['growth_percentage'])
            ->first();

        if ($topGrowth && BigDecimal::of($topGrowth['growth_percentage'])->isGreaterThan('0')) {
            $recommendations[] = "Monitor {$topGrowth['category_name']}, the highest-growth category.";
        }

        if ($leakage['transaction_count'] > 0) {
            $recommendations[] = 'Limit repeated small transactions that became meaningful in total.';
        }

        if ($anomalies->isNotEmpty()) {
            $recommendations[] = 'Review large unusual transactions before repeating them next week.';
        }

        return array_slice($recommendations, 0, 5);
    }

    private function monthlyRecommendations(Collection $categoryRows, array $recurring, array $leakage, array $forecast): array
    {
        $recommendations = ["Keep total spending near the forecast target of {$forecast['expected']}."];
        $topOpportunity = $categoryRows->sortByDesc('opportunity_score')->first();

        if ($topOpportunity) {
            $recommendations[] = "Monitor {$topOpportunity['category_name']}, the highest opportunity category.";
        }

        if (BigDecimal::of($recurring['monthly_commitment'])->isGreaterThan('0')) {
            $recommendations[] = 'Review active recurring expenses before the next billing cycle.';
        }

        if ($leakage['transaction_count'] > 0) {
            $recommendations[] = 'Reduce repeated small expenses before they accumulate.';
        }

        $recommendations[] = 'Maintain the recommended savings target when actual income allows it.';

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

    private function chartRows(Collection $rows, string $valueKey, int $limit): Collection
    {
        $sortedRows = $rows->sortByDesc(fn (array $row): float => (float) $row[$valueKey]);
        $topRows = $sortedRows->take($limit)->values();
        $remaining = $sortedRows->slice($limit);

        if ($remaining->isEmpty()) {
            return $topRows;
        }

        return $topRows->push([
            'category_name' => 'Other',
            $valueKey => $this->sumAmounts($remaining, $valueKey),
        ]);
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
