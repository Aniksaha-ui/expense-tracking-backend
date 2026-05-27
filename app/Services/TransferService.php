<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Support\MoneyHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransferService extends BaseFinanceService
{
    public function listTransfers(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('transfers')
            ->join('accounts as from_accounts', 'from_accounts.id', '=', 'transfers.from_account_id')
            ->join('accounts as to_accounts', 'to_accounts.id', '=', 'transfers.to_account_id')
            ->select([
                'transfers.*',
                'from_accounts.name as from_account_name',
                'from_accounts.type as from_account_type',
                'to_accounts.name as to_account_name',
                'to_accounts.type as to_account_type',
            ])
            ->where('transfers.user_id', $userId);

        if (! empty($filters['account_id'])) {
            $query->where(function ($subQuery) use ($filters) {
                $subQuery
                    ->where('transfers.from_account_id', $filters['account_id'])
                    ->orWhere('transfers.to_account_id', $filters['account_id']);
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('transfers.transfer_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('transfers.transfer_date', '<=', $filters['to_date']);
        }

        return $query
            ->orderByDesc('transfers.transfer_date')
            ->orderByDesc('transfers.id')
            ->get();
    }

    public function createTransfer(array $data, int $userId): object
    {
        return $this->processTransfer($data, $userId, false);
    }

    public function createWithdrawalToCash(array $data, int $userId): object
    {
        return $this->processTransfer($data, $userId, true);
    }

    private function processTransfer(array $data, int $userId, bool $isWithdrawal): object
    {
        return DB::transaction(function () use ($data, $userId, $isWithdrawal) {
            $fromAccount = $this->getOwnedAccount($userId, (int) $data['from_account_id'], true);
            $toAccount = $this->getOwnedAccount($userId, (int) $data['to_account_id'], true);

            if ($fromAccount->id === $toAccount->id) {
                throw new RuntimeException('Source and destination accounts must be different.');
            }

            if ($isWithdrawal) {
                if ($fromAccount->type === AccountType::CASH->value) {
                    throw new RuntimeException('Cash account cannot be used as the withdrawal source.');
                }

                if ($toAccount->type !== AccountType::CASH->value) {
                    throw new RuntimeException('Withdrawal destination must be a cash account.');
                }
            }

            $amount = $this->normalizeMoney($data['amount']);
            $fromBefore = $this->normalizeMoney($fromAccount->current_balance);

            if (MoneyHelper::greaterThan($amount, $fromBefore)) {
                throw new RuntimeException('Insufficient account balance.');
            }

            $toBefore = $this->normalizeMoney($toAccount->current_balance);
            $fromAfter = MoneyHelper::subtract($fromBefore, $amount);
            $toAfter = MoneyHelper::add($toBefore, $amount);
            $transferDate = $data['transfer_date'] ?? now();

            DB::table('accounts')
                ->where('id', $fromAccount->id)
                ->update([
                    'current_balance' => $fromAfter,
                    'updated_at' => now(),
                ]);

            DB::table('accounts')
                ->where('id', $toAccount->id)
                ->update([
                    'current_balance' => $toAfter,
                    'updated_at' => now(),
                ]);

            $transferId = DB::table('transfers')->insertGetId([
                'user_id' => $userId,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $amount,
                'note' => $data['note'] ?? null,
                'transfer_date' => $transferDate,
                'is_withdrawal' => $isWithdrawal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                [
                    'user_id' => $userId,
                    'account_id' => $fromAccount->id,
                    'category_id' => null,
                    'related_account_id' => $toAccount->id,
                    'type' => $isWithdrawal ? TransactionType::WITHDRAW->value : TransactionType::TRANSFER->value,
                    'amount' => $amount,
                    'balance_before' => $fromBefore,
                    'balance_after' => $fromAfter,
                    'note' => $data['note'] ?? null,
                    'transaction_date' => $transferDate,
                    'reference_type' => 'TRANSFER',
                    'reference_id' => $transferId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $userId,
                    'account_id' => $toAccount->id,
                    'category_id' => null,
                    'related_account_id' => $fromAccount->id,
                    'type' => TransactionType::DEPOSIT->value,
                    'amount' => $amount,
                    'balance_before' => $toBefore,
                    'balance_after' => $toAfter,
                    'note' => $data['note'] ?? null,
                    'transaction_date' => $transferDate,
                    'reference_type' => 'TRANSFER',
                    'reference_id' => $transferId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            return $this->fetchTransferRecord($userId, $transferId);
        });
    }
}
