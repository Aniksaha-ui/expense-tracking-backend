<?php

namespace App\Services;

use App\Enums\TransactionType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryExpenseAnalyzer
{
    public function analyze(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $categories = DB::table('categories')
            ->where('user_id', $userId)
            ->where('type', TransactionType::EXPENSE->value)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $currentRows = $this->expenseTotalsByCategory($userId, $fromDate, $toDate);
        $previousRows = $this->expenseTotalsByCategory(
            $userId,
            $fromDate->subDays($this->periodDays($fromDate, $toDate)),
            $fromDate->subDay()->endOfDay()
        );
        $historicalRows = $this->historicalAverageByCategory($userId, $fromDate);
        $recurringRows = $this->recurringCommitmentsByCategory($userId);
        $totalExpense = $this->sumAmounts($currentRows, 'total_expense');
        $maxTransactionCount = max((int) $currentRows->max('transaction_count'), 1);

        $rows = $categories->map(function (object $category) use (
            $currentRows,
            $previousRows,
            $historicalRows,
            $recurringRows,
            $totalExpense,
            $maxTransactionCount
        ): array {
            $current = $currentRows->get($category->id);
            $previous = $previousRows->get($category->id);
            $historical = $historicalRows->get($category->id);
            $recurring = $recurringRows->get($category->id);
            $currentExpense = $this->money($current->total_expense ?? '0');
            $previousExpense = $this->money($previous->total_expense ?? '0');
            $historicalAverage = $this->money($historical->historical_average ?? '0');
            $transactionCount = (int) ($current->transaction_count ?? 0);
            $averageExpense = $transactionCount === 0
                ? '0.00'
                : (string) BigDecimal::of($currentExpense)->dividedBy((string) $transactionCount, 2, RoundingMode::HALF_UP);
            $expensePercentage = $this->percentage($currentExpense, $totalExpense);
            $growthPercentage = BigDecimal::of($previousExpense)->isZero()
                ? null
                : $this->percentage(BigDecimal::of($currentExpense)->minus($previousExpense), $previousExpense);
            $historicalDeviationPercentage = BigDecimal::of($historicalAverage)->isZero()
                ? null
                : $this->percentage(BigDecimal::of($currentExpense)->minus($historicalAverage), $historicalAverage);
            $opportunityScore = $this->opportunityScore(
                $expensePercentage,
                $growthPercentage,
                $historicalDeviationPercentage,
                $transactionCount,
                $maxTransactionCount
            );

            return [
                'category_id' => (int) $category->id,
                'category_name' => $category->name,
                'transaction_count' => $transactionCount,
                'current_month_expense' => $currentExpense,
                'previous_month_expense' => $previousExpense,
                'historical_average' => $historicalAverage,
                'average_transaction' => $averageExpense,
                'expense_percentage' => $expensePercentage,
                'growth_percentage' => $growthPercentage,
                'historical_deviation_percentage' => $historicalDeviationPercentage,
                'recurring_commitment' => $this->money($recurring->recurring_commitment ?? '0'),
                'active_recurring_count' => (int) ($recurring->active_recurring_count ?? 0),
                'opportunity_score' => $opportunityScore,
                'potential_saving' => $this->potentialSaving($currentExpense, $opportunityScore),
            ];
        })->sort(function (array $left, array $right): int {
            return BigDecimal::of($right['current_month_expense'])->compareTo($left['current_month_expense']);
        })->values();

        return [
            'rows' => $rows,
            'alerts' => $this->alerts($rows),
            'chart_rows' => $this->chartRows($rows, (int) config('cost_reduction_reports.top_categories', 10)),
            'summary' => [
                'total_expense' => $this->money($totalExpense),
                'category_count' => $rows->count(),
                'active_category_count' => $rows->where('transaction_count', '>', 0)->count(),
                'transaction_count' => $rows->sum('transaction_count'),
                'potential_saving' => $this->sumAmounts($rows, 'potential_saving'),
            ],
        ];
    }

    private function expenseTotalsByCategory(int $userId, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        return DB::table('transactions')
            ->select([
                'category_id',
                DB::raw('COUNT(id) as transaction_count'),
                DB::raw('SUM(amount) as total_expense'),
            ])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->whereNotNull('category_id')
            ->whereDate('transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('transaction_date', '<=', $toDate->toDateString())
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function historicalAverageByCategory(int $userId, CarbonImmutable $fromDate): Collection
    {
        return DB::query()
            ->fromSub(function ($query) use ($userId, $fromDate): void {
                $query->from('transactions')
                    ->select([
                        'category_id',
                        DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as expense_month"),
                        DB::raw('SUM(amount) as monthly_total'),
                    ])
                    ->where('user_id', $userId)
                    ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
                    ->whereNotNull('category_id')
                    ->whereDate('transaction_date', '<', $fromDate->toDateString())
                    ->groupBy('category_id', DB::raw("DATE_FORMAT(transaction_date, '%Y-%m')"));
            }, 'monthly_category_expenses')
            ->select([
                'category_id',
                DB::raw('AVG(monthly_total) as historical_average'),
            ])
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function recurringCommitmentsByCategory(int $userId): Collection
    {
        return DB::table('recurring_expenses')
            ->select([
                'category_id',
                DB::raw('COUNT(id) as active_recurring_count'),
                DB::raw('SUM(amount) as recurring_commitment'),
            ])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    private function periodDays(CarbonImmutable $fromDate, CarbonImmutable $toDate): int
    {
        return (int) $fromDate->diffInDays($toDate) + 1;
    }

    private function opportunityScore(
        string $expensePercentage,
        ?string $growthPercentage,
        ?string $historicalDeviationPercentage,
        int $transactionCount,
        int $maxTransactionCount
    ): int {
        // Formula: share score 40 + positive MoM growth score 25 + relative frequency score 20
        // + positive historical deviation score 15. Each factor is capped before summing.
        $shareScore = min(40.0, (float) $expensePercentage * 0.4);
        $growthScore = $growthPercentage === null ? 0.0 : min(25.0, max(0.0, (float) $growthPercentage) * 0.25);
        $frequencyScore = min(20.0, ($transactionCount / $maxTransactionCount) * 20.0);
        $historicalScore = $historicalDeviationPercentage === null
            ? 0.0
            : min(15.0, max(0.0, (float) $historicalDeviationPercentage) * 0.15);

        return (int) round(min(100.0, $shareScore + $growthScore + $frequencyScore + $historicalScore));
    }

    private function potentialSaving(string $currentExpense, int $opportunityScore): string
    {
        $rate = BigDecimal::of((string) config('cost_reduction_reports.max_savings_rate', '0.30'))
            ->multipliedBy((string) $opportunityScore)
            ->dividedBy('100', 6, RoundingMode::HALF_UP);

        return (string) BigDecimal::of($currentExpense)->multipliedBy($rate)->toScale(2, RoundingMode::HALF_UP);
    }

    private function alerts(Collection $rows): Collection
    {
        return $rows->flatMap(function (array $row): array {
            $alerts = [];

            if ($row['growth_percentage'] !== null && BigDecimal::of($row['growth_percentage'])->isGreaterThan('0')) {
                $alerts[] = sprintf(
                    '%s spending increased %s%% compared with the previous period.',
                    $row['category_name'],
                    $row['growth_percentage']
                );
            }

            if (
                $row['historical_deviation_percentage'] !== null
                && BigDecimal::of($row['historical_deviation_percentage'])->isGreaterThan('0')
            ) {
                $alerts[] = sprintf(
                    '%s spending is %s%% above historical average.',
                    $row['category_name'],
                    $row['historical_deviation_percentage']
                );
            }

            return $alerts;
        })->values();
    }

    private function chartRows(Collection $rows, int $limit): Collection
    {
        $limit = max(1, $limit);
        $topRows = $rows->take($limit)->values();
        $remainingRows = $rows->slice($limit);

        if ($remainingRows->isEmpty()) {
            return $topRows;
        }

        return $topRows->push([
            'category_id' => null,
            'category_name' => 'Other',
            'current_month_expense' => $this->sumAmounts($remainingRows, 'current_month_expense'),
        ]);
    }

    private function sumAmounts(Collection $rows, string $key): string
    {
        return (string) $rows->reduce(
            fn (BigDecimal $sum, object|array $row): BigDecimal => $sum->plus((string) data_get($row, $key, '0')),
            BigDecimal::zero()
        )->toScale(2, RoundingMode::HALF_UP);
    }

    private function percentage(BigDecimal|string $value, BigDecimal|string $total): string
    {
        $total = BigDecimal::of($total);

        if ($total->isZero()) {
            return '0.0';
        }

        return (string) BigDecimal::of($value)
            ->dividedBy($total, 4, RoundingMode::HALF_UP)
            ->multipliedBy('100')
            ->toScale(1, RoundingMode::HALF_UP);
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP);
    }
}
