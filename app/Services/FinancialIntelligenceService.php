<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\FinancialGoal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Deterministic, user-scoped financial calculations. No LLM is used for arithmetic. */
class FinancialIntelligenceService
{
    public function dashboard(int $userId, array $filters = []): array
    {
        [$from, $to] = $this->period($filters);
        $metrics = $this->metrics($userId, $from, $to);
        $weeklySpending = $this->weeklySpending($userId, $from, $to);
        // Scheduled historical reports supply the reconciled portfolio balance
        // at their reporting cutoff. Do not mix it with today's account balances.
        if (array_key_exists('closing_balance', $filters)) {
            $metrics['current_balance'] = $this->money($filters['closing_balance']);
        }
        $forecast = $this->forecast($userId, $to, $filters);

        return [
            'period' => ['from_date' => $from->toDateString(), 'to_date' => $to->toDateString()],
            'metrics' => $metrics,
            'weekly_spending' => $weeklySpending,
            'forecast' => $forecast,
            'insights' => $this->insights($metrics, $forecast),
            'recommendations' => $this->recommendations($metrics, $forecast),
        ];
    }

    public function forecast(int $userId, CarbonImmutable $asOf, array $scenario = []): array
    {
        $start = $asOf->startOfMonth();
        $months = $this->monthlyHistory($userId, $start->subMonths(3), $asOf);
        $completed = $months->filter(fn (array $month): bool => $month['month'] !== $start->format('Y-m'));
        $daysElapsed = max(1, $asOf->day);
        $daysInMonth = $asOf->daysInMonth;
        $current = $months->firstWhere('month', $start->format('Y-m'));

        if ($completed->count() < 2 || $current === null) {
            return ['available' => false, 'reason' => 'At least two completed months and current-month activity are required for a forecast.', 'period' => $start->format('Y-m')];
        }

        $incomeChange = (string) ($scenario['income_change_percent'] ?? 0);
        $expenseChange = (string) ($scenario['expense_change_percent'] ?? 0);
        $incomeRunRate = BigDecimal::of($current['income'])->dividedBy($daysElapsed, 2, RoundingMode::HALF_UP)->multipliedBy($daysInMonth);
        $expenseRunRate = BigDecimal::of($current['expenses'])->dividedBy($daysElapsed, 2, RoundingMode::HALF_UP)->multipliedBy($daysInMonth);
        $income = $incomeRunRate->multipliedBy(BigDecimal::of('1')->plus(BigDecimal::of($incomeChange)->dividedBy(100, 4, RoundingMode::HALF_UP)));
        $expenses = $expenseRunRate->multipliedBy(BigDecimal::of('1')->plus(BigDecimal::of($expenseChange)->dividedBy(100, 4, RoundingMode::HALF_UP)));
        $balance = BigDecimal::of($scenario['closing_balance'] ?? $this->currentBalance($userId));
        $remainingNet = $income->minus($expenses)->multipliedBy($daysInMonth - $daysElapsed)->dividedBy($daysInMonth, 2, RoundingMode::HALF_UP);

        return [
            'available' => true,
            'period' => $start->format('Y-m'),
            'method' => 'Current-month daily run rate, adjusted by the requested scenario.',
            'assumptions' => ['Transaction timing remains similar for the rest of the month.', 'Internal transfers are excluded from income and expenses.'],
            'limitations' => ['This is an estimate, not a guarantee. Irregular income and one-off purchases can materially change results.'],
            'actual_to_date' => ['income' => $current['income'], 'expenses' => $current['expenses']],
            'estimated_month_end' => ['income' => $this->money($income), 'expenses' => $this->money($expenses), 'net_cash_flow' => $this->money($income->minus($expenses)), 'financial_position' => $this->money($balance->plus($remainingNet))],
            'scenario' => ['income_change_percent' => $incomeChange, 'expense_change_percent' => $expenseChange],
        ];
    }

    public function goals(int $userId): array
    {
        $monthlyCapacity = $this->estimatedMonthlyCapacity($userId);

        return FinancialGoal::query()->where('user_id', $userId)->orderBy('target_date')->get()->map(function (FinancialGoal $goal) use ($monthlyCapacity): array {
            $remaining = BigDecimal::of($goal->target_amount)->minus($goal->current_saved_amount)->max(BigDecimal::zero());
            $months = $goal->target_date ? max(1, now()->startOfMonth()->diffInMonths($goal->target_date->startOfMonth(), false)) : null;
            $suggested = $months ? $remaining->dividedBy($months, 2, RoundingMode::UP) : null;
            $capacity = BigDecimal::of($monthlyCapacity);
            $estimatedCompletion = $capacity->isPositive() ? now()->addMonths((int) ceil((float) $remaining->dividedBy($capacity, 6, RoundingMode::UP)))->toDateString() : null;

            return ['id' => $goal->id, 'name' => $goal->name, 'description' => $goal->description, 'target_amount' => $goal->target_amount, 'current_saved_amount' => $goal->current_saved_amount, 'target_date' => $goal->target_date?->toDateString(), 'remaining_amount' => $this->money($remaining), 'progress_percent' => min(100, round((float) BigDecimal::of($goal->current_saved_amount)->dividedBy($goal->target_amount, 6, RoundingMode::HALF_UP)->multipliedBy(100), 1)), 'suggested_monthly_contribution' => $suggested ? $this->money($suggested) : null, 'estimated_completion_date' => $estimatedCompletion, 'scenarios' => $suggested ? ['conservative' => $this->money($suggested->multipliedBy('0.75')), 'on_target' => $this->money($suggested), 'accelerated' => $this->money($suggested->multipliedBy('1.25'))] : []];
        })->all();
    }

    public function saveGoal(int $userId, array $attributes, ?int $goalId = null): FinancialGoal
    {
        $goal = $goalId ? FinancialGoal::query()->where('user_id', $userId)->findOrFail($goalId) : new FinancialGoal(['user_id' => $userId]);
        $goal->fill($attributes)->save();
        return $goal;
    }

    public function deleteGoal(int $userId, int $goalId): void
    {
        FinancialGoal::query()->where('user_id', $userId)->findOrFail($goalId)->delete();
    }

    private function metrics(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $transactions = DB::table('transactions')->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')->where('transactions.user_id', $userId)->whereBetween('transactions.transaction_date', [$from, $to])->select('transactions.type', 'transactions.amount', 'transactions.transaction_date', 'transactions.note', DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"))->get();
        $income = $this->sum($transactions->where('type', TransactionType::INCOME->value));
        $expenses = $this->sum($transactions->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value]));
        $categories = $transactions->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])->groupBy('category_name')->map(fn (Collection $items, string $name): array => ['name' => $name, 'amount' => $this->sum($items), 'transaction_count' => $items->count()])->sortByDesc('amount')->values();
        $recurring = $transactions->where('type', TransactionType::RECURRING->value);
        $daily = $transactions->groupBy(fn (object $item): string => CarbonImmutable::parse($item->transaction_date)->toDateString())->map(function (Collection $items, string $date): array { return ['date' => $date, 'income' => $this->sum($items->where('type', TransactionType::INCOME->value)), 'expenses' => $this->sum($items->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value]))]; })->values();

        return ['current_balance' => $this->currentBalance($userId), 'total_income' => $income, 'total_expenses' => $expenses, 'net_cash_flow' => $this->money(BigDecimal::of($income)->minus($expenses)), 'transaction_count' => $transactions->count(), 'category_spending' => $categories->all(), 'daily_cash_flow' => $daily->all(), 'recurring_expense_total' => $this->sum($recurring), 'recurring_expense_count' => $recurring->count(), 'spending_concentration_percent' => $categories->isEmpty() || BigDecimal::of($expenses)->isZero() ? '0.0' : (string) BigDecimal::of($categories->first()['amount'])->dividedBy($expenses, 4, RoundingMode::HALF_UP)->multipliedBy(100)->toScale(1, RoundingMode::HALF_UP), 'data_sufficiency' => $transactions->count() >= 3 ? 'sufficient_for_descriptive_analysis' : 'limited'];
    }

    private function insights(array $metrics, array $forecast): array
    {
        $insights = [];
        $insights[] = ['title' => 'Cash-flow position', 'type' => 'financial_health', 'observation' => 'Income was BDT '.$metrics['total_income'].' and expenses were BDT '.$metrics['total_expenses'].' for the selected period.', 'interpretation' => 'Net cash flow is BDT '.$metrics['net_cash_flow'].'. A positive result does not by itself indicate long-term financial health.', 'supporting_metrics' => ['net_cash_flow' => $metrics['net_cash_flow']], 'priority' => BigDecimal::of($metrics['net_cash_flow'])->isNegative() ? 'high' : 'medium', 'confidence' => 'high', 'recommended_action' => 'Review the category breakdown alongside upcoming obligations.', 'limitations' => ['Only recorded transactions are included.']];
        if ($metrics['category_spending'] !== []) { $top = $metrics['category_spending'][0]; $insights[] = ['title' => 'Largest expense category', 'type' => 'spending', 'observation' => $top['name'].' accounted for BDT '.$top['amount'].' across '.$top['transaction_count'].' transaction(s).', 'interpretation' => 'This is a concentration signal, not evidence that the spending is unnecessary.', 'supporting_metrics' => $top, 'priority' => 'medium', 'confidence' => 'high', 'recommended_action' => 'Confirm whether this category is essential, contractual, or flexible before setting a reduction target.', 'limitations' => ['Category labels determine this classification.']]; }
        if (! $forecast['available']) { $insights[] = ['title' => 'Forecast unavailable', 'type' => 'forecast', 'observation' => $forecast['reason'], 'interpretation' => 'A numerical forecast would not be reliable yet.', 'supporting_metrics' => [], 'priority' => 'low', 'confidence' => 'high', 'recommended_action' => 'Record regular income and expenses over future months.', 'limitations' => []]; }
        return $insights;
    }

    private function recommendations(array $metrics, array $forecast): array
    {
        $recommendations = [];
        if (BigDecimal::of($metrics['net_cash_flow'])->isNegative()) { $recommendations[] = ['title' => 'Address the period cash-flow gap', 'observation' => 'Recorded expenses exceeded income by BDT '.BigDecimal::of($metrics['net_cash_flow'])->abs().'.', 'supporting_metrics' => ['net_cash_flow' => $metrics['net_cash_flow']], 'why_it_matters' => 'Repeated gaps can reduce available balance.', 'recommended_action' => 'Review the highest categories and upcoming recurring bills before committing to discretionary spending.', 'priority' => 'high', 'confidence' => 'high', 'potential_impact' => null, 'assumptions' => ['All relevant transactions were recorded.']]; }
        if ((float) $metrics['spending_concentration_percent'] >= 50) { $recommendations[] = ['title' => 'Review concentrated spending', 'observation' => 'The largest category represents '.$metrics['spending_concentration_percent'].'% of recorded expenses.', 'supporting_metrics' => ['spending_concentration_percent' => $metrics['spending_concentration_percent']], 'why_it_matters' => 'A single category has an outsized effect on the budget.', 'recommended_action' => 'Check whether part of that category is flexible; do not reduce essential or contractual costs without review.', 'priority' => 'medium', 'confidence' => 'high', 'potential_impact' => null, 'assumptions' => []]; }
        return $recommendations;
    }

    private function monthlyHistory(int $userId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('transactions')->where('user_id', $userId)->whereBetween('transaction_date', [$from->startOfMonth(), $to->endOfDay()])->whereIn('type', [TransactionType::INCOME->value, TransactionType::EXPENSE->value, TransactionType::RECURRING->value])->get()->groupBy(fn (object $row): string => CarbonImmutable::parse($row->transaction_date)->format('Y-m'))->map(fn (Collection $rows, string $month): array => ['month' => $month, 'income' => $this->sum($rows->where('type', TransactionType::INCOME->value)), 'expenses' => $this->sum($rows->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value]))])->values();
    }

    private function weeklySpending(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('transactions')->where('user_id', $userId)->whereBetween('transaction_date', [$from, $to])
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])->get()
            ->groupBy(fn (object $row): string => CarbonImmutable::parse($row->transaction_date)->startOfWeek()->toDateString())
            ->map(fn (Collection $items, string $week): array => ['week_start' => $week, 'week_end' => CarbonImmutable::parse($week)->addDays(6)->min($to)->toDateString(), 'total_expenses' => $this->sum($items), 'transaction_count' => $items->count()])
            ->sortBy('week_start')->values()->all();

        return ['rows' => $rows, 'note' => 'Weekly totals include recorded expense and recurring-expense entries only.'];
    }

    private function currentBalance(int $userId): string { return $this->money(DB::table('accounts')->where('user_id', $userId)->sum('current_balance')); }
    private function sum(Collection $rows): string { return $this->money($rows->reduce(fn (BigDecimal $carry, object $row): BigDecimal => $carry->plus($row->amount), BigDecimal::zero())); }
    private function money(mixed $value): string { return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP); }
    private function period(array $filters): array { $to = CarbonImmutable::parse($filters['to_date'] ?? now())->endOfDay(); return [CarbonImmutable::parse($filters['from_date'] ?? $to->startOfMonth())->startOfDay(), $to]; }
    private function estimatedMonthlyCapacity(int $userId): string { $history = $this->monthlyHistory($userId, now()->subMonths(3)->startOfMonth(), now()); if ($history->isEmpty()) return '0.00'; return $this->money($history->reduce(fn (BigDecimal $carry, array $row): BigDecimal => $carry->plus(BigDecimal::of($row['income'])->minus($row['expenses'])), BigDecimal::zero())->dividedBy($history->count(), 2, RoundingMode::HALF_UP)->max(BigDecimal::zero())); }
}
