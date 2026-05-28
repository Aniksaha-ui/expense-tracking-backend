<?php

namespace App\Services;

use App\Enums\TransactionType;
use Brick\Math\BigDecimal;
use App\Support\MoneyHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class BaseFinanceService
{
    protected function normalizeMoney(string|int|float $amount): string
    {
        return (string) BigDecimal::of((string) $amount)->toScale(2);
    }

    protected function getOwnedAccount(int $userId, int $accountId, bool $lockForUpdate = false): object
    {
        $query = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('id', $accountId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $account = $query->first();

        if (! $account) {
            throw new RuntimeException('Invalid account id.');
        }

        return $account;
    }

    protected function getOwnedCategory(int $userId, int $categoryId): object
    {
        $category = DB::table('categories')
            ->where('user_id', $userId)
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            throw new RuntimeException('Invalid category id.');
        }

        return $category;
    }

    protected function getOwnedTransaction(int $userId, int $transactionId, bool $lockForUpdate = false): object
    {
        $query = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('id', $transactionId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $transaction = $query->first();

        if (! $transaction) {
            throw new RuntimeException('Invalid transaction id.');
        }

        return $transaction;
    }

    protected function getOwnedTransfer(int $userId, int $transferId, bool $lockForUpdate = false): object
    {
        $query = DB::table('transfers')
            ->where('user_id', $userId)
            ->where('id', $transferId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $transfer = $query->first();

        if (! $transfer) {
            throw new RuntimeException('Invalid transfer id.');
        }

        return $transfer;
    }

    protected function recalculateAccountBalances(int $userId, array $accountIds): void
    {
        $normalizedAccountIds = array_values(array_unique(array_map(
            static fn (mixed $accountId): int => (int) $accountId,
            array_filter(
                $accountIds,
                static fn (mixed $accountId): bool => $accountId !== null && $accountId !== ''
            )
        )));

        sort($normalizedAccountIds);

        foreach ($normalizedAccountIds as $accountId) {
            $this->recalculateAccountBalance($userId, $accountId);
        }
    }

    protected function fetchAccountRecord(int $userId, int $accountId): object
    {
        $account = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            throw new RuntimeException('Invalid account id.');
        }

        return $account;
    }

    protected function fetchCategoryRecord(int $userId, int $categoryId): object
    {
        $category = DB::table('categories')
            ->where('user_id', $userId)
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            throw new RuntimeException('Invalid category id.');
        }

        return $category;
    }

    protected function fetchTransactionRecord(int $userId, int $transactionId): object
    {
        $transaction = DB::table('transactions')
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts as related_accounts', 'related_accounts.id', '=', 'transactions.related_account_id')
            ->select([
                'transactions.*',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'categories.name as category_name',
                'categories.type as category_type',
                'related_accounts.name as related_account_name',
                'related_accounts.type as related_account_type',
            ])
            ->where('transactions.user_id', $userId)
            ->where('transactions.id', $transactionId)
            ->first();

        if (! $transaction) {
            throw new RuntimeException('Invalid transaction id.');
        }

        return $transaction;
    }

    protected function fetchTransferRecord(int $userId, int $transferId): object
    {
        $transfer = DB::table('transfers')
            ->join('accounts as from_accounts', 'from_accounts.id', '=', 'transfers.from_account_id')
            ->join('accounts as to_accounts', 'to_accounts.id', '=', 'transfers.to_account_id')
            ->select([
                'transfers.*',
                'from_accounts.name as from_account_name',
                'from_accounts.type as from_account_type',
                'to_accounts.name as to_account_name',
                'to_accounts.type as to_account_type',
            ])
            ->where('transfers.user_id', $userId)
            ->where('transfers.id', $transferId)
            ->first();

        if (! $transfer) {
            throw new RuntimeException('Invalid transfer id.');
        }

        return $transfer;
    }

    protected function fetchRecurringExpenseRecord(int $userId, int $recurringExpenseId): object
    {
        $recurringExpense = DB::table('recurring_expenses')
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
            ->where('recurring_expenses.id', $recurringExpenseId)
            ->first();

        if (! $recurringExpense) {
            throw new RuntimeException('Invalid recurring expense id.');
        }

        return $recurringExpense;
    }

    private function recalculateAccountBalance(int $userId, int $accountId): void
    {
        $this->getOwnedAccount($userId, $accountId, true);

        $transactions = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [TransactionType::OPENING_BALANCE->value])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $runningBalance = '0.00';

        foreach ($transactions as $transaction) {
            $balanceBefore = $runningBalance;
            $balanceAfter = $this->calculateBalanceAfter(
                $balanceBefore,
                $this->normalizeMoney($transaction->amount),
                (string) $transaction->type
            );

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'updated_at' => now(),
                ]);

            $runningBalance = $balanceAfter;
        }

        DB::table('accounts')
            ->where('user_id', $userId)
            ->where('id', $accountId)
            ->update([
                'current_balance' => $runningBalance,
                'updated_at' => now(),
            ]);
    }

    private function calculateBalanceAfter(string $balanceBefore, string $amount, string $type): string
    {
        if ($this->isCreditTransactionType($type)) {
            return MoneyHelper::add($balanceBefore, $amount);
        }

        if (MoneyHelper::greaterThan($amount, $balanceBefore)) {
            throw new RuntimeException('Insufficient account balance.');
        }

        return MoneyHelper::subtract($balanceBefore, $amount);
    }

    private function isCreditTransactionType(string $type): bool
    {
        return in_array(strtoupper($type), [
            TransactionType::OPENING_BALANCE->value,
            TransactionType::INCOME->value,
            TransactionType::DEPOSIT->value,
        ], true);
    }
}
