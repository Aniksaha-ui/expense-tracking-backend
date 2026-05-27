<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Support\MoneyHelper;
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
}
