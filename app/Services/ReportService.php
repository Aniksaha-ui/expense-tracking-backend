<?php

namespace App\Services;

use App\Enums\TransactionType;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportService extends BaseFinanceService
{
    public function summary(int $userId, array $filters): array
    {
        $baseQuery = DB::table('transactions')->where('user_id', $userId);
        $this->applyTransactionDateFilters($baseQuery, $filters);

        $currentBalance = DB::table('accounts')
            ->where('user_id', $userId)
            ->sum('current_balance');

        return [
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
            'total_income' => $this->formatAggregate((clone $baseQuery)->where('type', TransactionType::INCOME->value)->sum('amount')),
            'total_expense' => $this->formatAggregate((clone $baseQuery)->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])->sum('amount')),
            'total_withdraw' => $this->formatAggregate((clone $baseQuery)->where('type', TransactionType::WITHDRAW->value)->sum('amount')),
            'total_transfer_out' => $this->formatAggregate((clone $baseQuery)->where('type', TransactionType::TRANSFER->value)->sum('amount')),
            'current_balance' => $this->formatAggregate($currentBalance),
            'account_count' => DB::table('accounts')->where('user_id', $userId)->count(),
        ];
    }

    public function accountBalances(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('accounts')
            ->where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function categoryBreakdown(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('transactions')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select([
                'transactions.category_id',
                'categories.name as category_name',
                'categories.type as category_type',
                DB::raw('SUM(transactions.amount) as total_amount'),
            ])
            ->where('transactions.user_id', $userId)
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->groupBy('transactions.category_id', 'categories.name', 'categories.type')
            ->orderByDesc('total_amount');

        $this->applyTransactionDateFilters($query, $filters);

        return $query->get();
    }

    public function cashFlow(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('transactions')
            ->select([
                DB::raw('DATE(transaction_date) as date'),
                DB::raw("SUM(CASE WHEN type = '".TransactionType::INCOME->value."' THEN amount ELSE 0 END) as income_total"),
                DB::raw("SUM(CASE WHEN type IN ('".TransactionType::EXPENSE->value."', '".TransactionType::RECURRING->value."') THEN amount ELSE 0 END) as expense_total"),
            ])
            ->where('user_id', $userId)
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->orderBy('date');

        $this->applyTransactionDateFilters($query, $filters);

        return $query->get()->map(function ($item) {
            $income = $this->formatAggregate($item->income_total);
            $expense = $this->formatAggregate($item->expense_total);

            return [
                'date' => $item->date,
                'income_total' => $income,
                'expense_total' => $expense,
                'net_total' => (string) BigDecimal::of($income)->minus($expense)->toScale(2),
            ];
        });
    }

    public function dueRecurringExpenses(int $userId, ?string $throughDate = null): \Illuminate\Support\Collection
    {
        $date = $throughDate ?? now()->toDateString();

        return DB::table('recurring_expenses')
            ->join('accounts', 'accounts.id', '=', 'recurring_expenses.account_id')
            ->join('categories', 'categories.id', '=', 'recurring_expenses.category_id')
            ->select([
                'recurring_expenses.*',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'categories.name as category_name',
                'categories.type as category_type',
            ])
            ->where('recurring_expenses.user_id', $userId)
            ->where('recurring_expenses.is_active', true)
            ->whereDate('recurring_expenses.next_run_date', '<=', $date)
            ->orderBy('recurring_expenses.next_run_date')
            ->get();
    }

    private function applyTransactionDateFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }
    }

    private function formatAggregate(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2);
    }
}
