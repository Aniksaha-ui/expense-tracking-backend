<?php

namespace App\Services;

use Brick\Math\BigDecimal;
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
}
