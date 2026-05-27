<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Support\MoneyHelper;
use Illuminate\Support\Facades\DB;

class AccountService extends BaseFinanceService
{
    public function listAccounts(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('accounts')
            ->where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function getAccount(int $userId, int $accountId): object
    {
        return $this->fetchAccountRecord($userId, $accountId);
    }

    public function storeAccount(array $data, int $userId): object
    {
        return DB::transaction(function () use ($data, $userId) {
            $openingBalance = $this->normalizeMoney($data['opening_balance'] ?? '0');
            $accountId = DB::table('accounts')->insertGetId([
                'user_id' => $userId,
                'name' => $data['name'],
                'type' => $data['type'],
                'institution_name' => $data['institution_name'] ?? null,
                'current_balance' => $openingBalance,
                'is_active' => $data['is_active'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (MoneyHelper::greaterThan($openingBalance, '0.00')) {
                DB::table('transactions')->insert([
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'category_id' => null,
                    'related_account_id' => null,
                    'type' => TransactionType::OPENING_BALANCE->value,
                    'amount' => $openingBalance,
                    'balance_before' => '0.00',
                    'balance_after' => $openingBalance,
                    'note' => 'Opening balance',
                    'transaction_date' => $data['opening_balance_date'] ?? now(),
                    'reference_type' => 'ACCOUNT',
                    'reference_id' => $accountId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->fetchAccountRecord($userId, $accountId);
        });
    }

    public function updateAccount(array $data, int $userId, int $accountId): object
    {
        $this->getOwnedAccount($userId, $accountId);

        $updateData = ['updated_at' => now()];

        foreach (['name', 'type', 'institution_name', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        DB::table('accounts')
            ->where('user_id', $userId)
            ->where('id', $accountId)
            ->update($updateData);

        return $this->fetchAccountRecord($userId, $accountId);
    }
}
