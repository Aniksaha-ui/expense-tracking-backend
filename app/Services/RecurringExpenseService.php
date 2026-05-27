<?php

namespace App\Services;

use App\Enums\RecurringFrequency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecurringExpenseService extends BaseFinanceService
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    public function listRecurringExpenses(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('recurring_expenses')
            ->join('accounts', 'accounts.id', '=', 'recurring_expenses.account_id')
            ->join('categories', 'categories.id', '=', 'recurring_expenses.category_id')
            ->select([
                'recurring_expenses.*',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'categories.name as category_name',
                'categories.type as category_type',
            ])
            ->where('recurring_expenses.user_id', $userId);

        if (array_key_exists('is_active', $filters)) {
            $query->where('recurring_expenses.is_active', $filters['is_active']);
        }

        return $query
            ->orderBy('recurring_expenses.next_run_date')
            ->orderBy('recurring_expenses.title')
            ->get();
    }

    public function storeRecurringExpense(array $data, int $userId): object
    {
        $this->getOwnedAccount($userId, (int) $data['account_id']);
        $category = $this->getOwnedCategory($userId, (int) $data['category_id']);

        if (strtoupper($category->type) !== 'EXPENSE') {
            throw new RuntimeException('Recurring expenses require an expense category.');
        }

        $recurringExpenseId = DB::table('recurring_expenses')->insertGetId([
            'user_id' => $userId,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'amount' => $this->normalizeMoney($data['amount']),
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'next_run_date' => $data['next_run_date'] ?? $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'last_run_at' => null,
            'is_active' => $data['is_active'] ?? true,
            'note' => $data['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->fetchRecurringExpenseRecord($userId, $recurringExpenseId);
    }

    public function updateRecurringExpense(array $data, int $userId, int $recurringExpenseId): object
    {
        $existing = DB::table('recurring_expenses')
            ->where('user_id', $userId)
            ->where('id', $recurringExpenseId)
            ->first();

        if (! $existing) {
            throw new RuntimeException('Invalid recurring expense id.');
        }

        $accountId = $data['account_id'] ?? $existing->account_id;
        $categoryId = $data['category_id'] ?? $existing->category_id;
        $this->getOwnedAccount($userId, (int) $accountId);
        $category = $this->getOwnedCategory($userId, (int) $categoryId);

        if (strtoupper($category->type) !== 'EXPENSE') {
            throw new RuntimeException('Recurring expenses require an expense category.');
        }

        $updateData = [
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'title' => $data['title'] ?? $existing->title,
            'amount' => array_key_exists('amount', $data) ? $this->normalizeMoney($data['amount']) : $existing->amount,
            'frequency' => $data['frequency'] ?? $existing->frequency,
            'start_date' => $data['start_date'] ?? $existing->start_date,
            'next_run_date' => $data['next_run_date'] ?? $existing->next_run_date,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $existing->end_date,
            'is_active' => $data['is_active'] ?? $existing->is_active,
            'note' => array_key_exists('note', $data) ? $data['note'] : $existing->note,
            'updated_at' => now(),
        ];

        DB::table('recurring_expenses')
            ->where('user_id', $userId)
            ->where('id', $recurringExpenseId)
            ->update($updateData);

        return $this->fetchRecurringExpenseRecord($userId, $recurringExpenseId);
    }

    public function runRecurringExpense(int $userId, int $recurringExpenseId, ?string $runDate = null): array
    {
        return DB::transaction(function () use ($userId, $recurringExpenseId, $runDate) {
            $recurringExpense = DB::table('recurring_expenses')
                ->where('user_id', $userId)
                ->where('id', $recurringExpenseId)
                ->lockForUpdate()
                ->first();

            if (! $recurringExpense) {
                throw new RuntimeException('Invalid recurring expense id.');
            }

            if (! $recurringExpense->is_active) {
                throw new RuntimeException('Recurring expense is inactive.');
            }

            $transactionDate = $runDate ?? now()->toDateTimeString();
            $transaction = $this->transactionService->createRecurringExpense([
                'account_id' => $recurringExpense->account_id,
                'category_id' => $recurringExpense->category_id,
                'amount' => $recurringExpense->amount,
                'note' => $recurringExpense->note ?? $recurringExpense->title,
                'transaction_date' => $transactionDate,
                'reference_id' => $recurringExpense->id,
            ], $userId);

            $nextRunDate = $this->calculateNextRunDate(
                $recurringExpense->frequency,
                Carbon::parse($recurringExpense->next_run_date)
            );

            DB::table('recurring_expenses')
                ->where('id', $recurringExpense->id)
                ->update([
                    'last_run_at' => $transactionDate,
                    'next_run_date' => $nextRunDate->toDateString(),
                    'updated_at' => now(),
                ]);

            return [
                'recurring_expense' => $this->fetchRecurringExpenseRecord($userId, $recurringExpense->id),
                'transaction' => $transaction,
            ];
        });
    }

    public function runDueRecurringExpenses(int $userId, ?string $throughDate = null): array
    {
        $targetDate = $throughDate ? Carbon::parse($throughDate)->toDateString() : now()->toDateString();

        $dueItems = DB::table('recurring_expenses')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', $targetDate)
            ->orderBy('next_run_date')
            ->get();

        $executed = [];

        foreach ($dueItems as $dueItem) {
            $executed[] = $this->runRecurringExpense($userId, $dueItem->id, $targetDate.' '.now()->format('H:i:s'));
        }

        return [
            'count' => count($executed),
            'items' => $executed,
        ];
    }

    private function calculateNextRunDate(string $frequency, Carbon $fromDate): Carbon
    {
        return match ($frequency) {
            RecurringFrequency::DAILY->value => $fromDate->copy()->addDay(),
            RecurringFrequency::WEEKLY->value => $fromDate->copy()->addWeek(),
            RecurringFrequency::MONTHLY->value => $fromDate->copy()->addMonth(),
            RecurringFrequency::YEARLY->value => $fromDate->copy()->addYear(),
            default => throw new RuntimeException('Invalid recurring frequency.'),
        };
    }
}
