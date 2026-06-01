<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Support\MoneyHelper;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionService extends BaseFinanceService
{
    public function listTransactions(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('transactions')
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
            ->where('transactions.user_id', $userId);

        if (! empty($filters['account_id'])) {
            $query->where('transactions.account_id', $filters['account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('transactions.category_id', $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $this->applySearchFilter($query, $filters['search']);
        }

        if (! empty($filters['type'])) {
            $query->where('transactions.type', strtoupper($filters['type']));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('transactions.transaction_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('transactions.transaction_date', '<=', $filters['to_date']);
        }

        return $query
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->get();
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $normalizedSearch = mb_strtolower(trim($search));

        if ($normalizedSearch === '') {
            return;
        }

        $likeSearch = '%'.$normalizedSearch.'%';
        $numericSearch = ctype_digit($normalizedSearch) ? (int) $normalizedSearch : null;

        $query->where(function (Builder $searchQuery) use ($likeSearch, $numericSearch) {
            $searchQuery
                ->whereRaw('LOWER(accounts.name) LIKE ?', [$likeSearch])
                ->orWhereRaw('LOWER(REPLACE(accounts.type, "_", " ")) LIKE ?', [$likeSearch])
                ->orWhereRaw('LOWER(accounts.type) LIKE ?', [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(categories.name, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(REPLACE(COALESCE(categories.type, ''), '_', ' ')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(categories.type, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(related_accounts.name, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(REPLACE(COALESCE(related_accounts.type, ''), '_', ' ')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(related_accounts.type, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(transactions.note, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw('LOWER(REPLACE(transactions.type, "_", " ")) LIKE ?', [$likeSearch])
                ->orWhereRaw('LOWER(transactions.type) LIKE ?', [$likeSearch])
                ->orWhereRaw("LOWER(REPLACE(COALESCE(transactions.reference_type, ''), '_', ' ')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(transactions.reference_type, '')) LIKE ?", [$likeSearch])
                ->orWhereRaw("LOWER(COALESCE(transactions.transaction_date, '')) LIKE ?", [$likeSearch]);

            if ($numericSearch !== null) {
                $searchQuery
                    ->orWhere('transactions.id', $numericSearch)
                    ->orWhere('transactions.reference_id', $numericSearch);
            }
        });
    }

    public function createIncome(array $data, int $userId): object
    {
        return $this->createTransaction($data, $userId, TransactionType::INCOME, true, 'INCOME');
    }

    public function createExpense(array $data, int $userId): object
    {
        return $this->createTransaction($data, $userId, TransactionType::EXPENSE, false, 'EXPENSE');
    }

    public function createDeposit(array $data, int $userId): object
    {
        return $this->createTransaction($data, $userId, TransactionType::DEPOSIT, true);
    }

    public function createRecurringExpense(array $data, int $userId): object
    {
        return $this->createTransaction(
            $data,
            $userId,
            TransactionType::RECURRING,
            false,
            'EXPENSE',
            'RECURRING_EXPENSE',
            $data['reference_id'] ?? null
        );
    }

    public function updateTransaction(int $transactionId, array $data, int $userId): object
    {
        return DB::transaction(function () use ($transactionId, $data, $userId) {
            $existingTransaction = $this->getOwnedTransaction($userId, $transactionId, true);

            if (
                $existingTransaction->reference_type !== null
                || ! in_array($existingTransaction->type, [
                    TransactionType::INCOME->value,
                    TransactionType::EXPENSE->value,
                    TransactionType::DEPOSIT->value,
                ], true)
            ) {
                throw new RuntimeException('Only manual income, expense, and deposit transactions can be updated.');
            }

            $accountId = (int) ($data['account_id'] ?? $existingTransaction->account_id);
            $account = $this->getOwnedAccount($userId, $accountId);
            $amount = $this->normalizeMoney($data['amount'] ?? $existingTransaction->amount);
            $categoryId = $this->resolveUpdatedCategoryId($existingTransaction->type, $data, $userId);

            DB::table('transactions')
                ->where('user_id', $userId)
                ->where('id', $transactionId)
                ->update([
                    'account_id' => $account->id,
                    'category_id' => $categoryId,
                    'amount' => $amount,
                    'note' => $data['note'] ?? null,
                    'transaction_date' => $data['transaction_date'] ?? $existingTransaction->transaction_date,
                    'updated_at' => now(),
                ]);

            $this->recalculateAccountBalances($userId, [
                (int) $existingTransaction->account_id,
                $account->id,
            ]);

            return $this->fetchTransactionRecord($userId, $transactionId);
        });
    }

    private function createTransaction(
        array $data,
        int $userId,
        TransactionType $type,
        bool $isCredit,
        ?string $categoryType = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $relatedAccountId = null
    ): object {
        return DB::transaction(function () use ($data, $userId, $type, $isCredit, $categoryType, $referenceType, $referenceId, $relatedAccountId) {
            $account = $this->getOwnedAccount($userId, (int) $data['account_id'], true);
            $amount = $this->normalizeMoney($data['amount']);
            $categoryId = null;

            if (! empty($data['category_id'])) {
                $category = $this->getOwnedCategory($userId, (int) $data['category_id']);

                if ($categoryType !== null && strtoupper($category->type) !== $categoryType) {
                    throw new RuntimeException('Invalid category type for this transaction.');
                }

                $categoryId = $category->id;
            } elseif ($categoryType !== null) {
                throw new RuntimeException('Category is required for this transaction.');
            }

            $balanceBefore = $this->normalizeMoney($account->current_balance);

            if (! $isCredit && MoneyHelper::greaterThan($amount, $balanceBefore)) {
                throw new RuntimeException('Insufficient account balance.');
            }

            $balanceAfter = $isCredit
                ? MoneyHelper::add($balanceBefore, $amount)
                : MoneyHelper::subtract($balanceBefore, $amount);

            DB::table('accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance' => $balanceAfter,
                    'updated_at' => now(),
                ]);

            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $userId,
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'related_account_id' => $relatedAccountId,
                'type' => $type->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $data['note'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->fetchTransactionRecord($userId, $transactionId);
        });
    }

    private function resolveUpdatedCategoryId(string $transactionType, array $data, int $userId): ?int
    {
        $normalizedType = strtoupper($transactionType);

        if ($normalizedType === TransactionType::DEPOSIT->value) {
            if (! empty($data['category_id'])) {
                throw new RuntimeException('Deposit transactions cannot use a category.');
            }

            return null;
        }

        if (empty($data['category_id'])) {
            if ($normalizedType === TransactionType::EXPENSE->value) {
                throw new RuntimeException('Category is required for this transaction.');
            }

            return null;
        }

        $category = $this->getOwnedCategory($userId, (int) $data['category_id']);
        $expectedCategoryType = $normalizedType === TransactionType::EXPENSE->value ? 'EXPENSE' : 'INCOME';

        if (strtoupper($category->type) !== $expectedCategoryType) {
            throw new RuntimeException('Invalid category type for this transaction.');
        }

        return $category->id;
    }
}
