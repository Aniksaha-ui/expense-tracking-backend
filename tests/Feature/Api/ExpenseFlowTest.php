<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class ExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_and_withdrawal_update_owned_account_balances(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Bob Example',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Cash Wallet',
                'type' => 'CASH',
                'opening_balance' => '1000.00',
            ]);

        $bankAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Main Bank',
                'type' => 'BANK',
                'opening_balance' => '500.00',
            ]);

        $cashAccountId = $cashAccountResponse->json('data.id');
        $bankAccountId = $bankAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '100.00',
                'note' => 'Groceries',
            ])
            ->assertCreated()
            ->assertJsonPath('isExecute', 'success');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transfers/withdraw-to-cash', [
                'from_account_id' => $bankAccountId,
                'to_account_id' => $cashAccountId,
                'amount' => '200.00',
                'note' => 'ATM withdrawal',
            ])
            ->assertCreated()
            ->assertJsonPath('isExecute', 'success');

        $accountsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/accounts')
            ->assertOk();

        $accounts = collect($accountsResponse->json('data'))->keyBy('name');

        $this->assertSame('1100.00', $accounts['Cash Wallet']['current_balance']);
        $this->assertSame('300.00', $accounts['Main Bank']['current_balance']);
    }

    public function test_updating_manual_transaction_recalculates_following_account_balances(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Daily Cash',
                'type' => 'CASH',
                'opening_balance' => '1000.00',
                'opening_balance_date' => '2026-01-01',
            ])
            ->assertCreated();

        $cashAccountId = $cashAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];

        $firstExpenseResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '100.00',
                'note' => 'Groceries',
                'transaction_date' => '2026-01-02',
            ])
            ->assertCreated();

        $secondExpenseResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '50.00',
                'note' => 'Snacks',
                'transaction_date' => '2026-01-03',
            ])
            ->assertCreated();

        $firstExpenseId = $firstExpenseResponse->json('data.id');
        $secondExpenseId = $secondExpenseResponse->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/transactions/'.$firstExpenseId, [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '200.00',
                'note' => 'Updated groceries',
                'transaction_date' => '2026-01-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '200.00')
            ->assertJsonPath('data.note', 'Updated groceries');

        $transactionsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/transactions')
            ->assertOk();

        $transactions = collect($transactionsResponse->json('data'))->keyBy('id');

        $this->assertSame('1000.00', $transactions[$firstExpenseId]['balance_before']);
        $this->assertSame('800.00', $transactions[$firstExpenseId]['balance_after']);
        $this->assertSame('800.00', $transactions[$secondExpenseId]['balance_before']);
        $this->assertSame('750.00', $transactions[$secondExpenseId]['balance_after']);

        $accountsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/accounts')
            ->assertOk();

        $accounts = collect($accountsResponse->json('data'))->keyBy('id');

        $this->assertSame('750.00', $accounts[$cashAccountId]['current_balance']);
    }

    public function test_updating_transfer_recalculates_transfer_and_following_cash_transaction_balances(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Carol Example',
            'email' => 'carol@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Cash Wallet',
                'type' => 'CASH',
                'opening_balance' => '100.00',
                'opening_balance_date' => '2026-01-01',
            ])
            ->assertCreated();

        $bankAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Main Bank',
                'type' => 'BANK',
                'opening_balance' => '1000.00',
                'opening_balance_date' => '2026-01-01',
            ])
            ->assertCreated();

        $cashAccountId = $cashAccountResponse->json('data.id');
        $bankAccountId = $bankAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];

        $transferResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transfers', [
                'from_account_id' => $bankAccountId,
                'to_account_id' => $cashAccountId,
                'amount' => '200.00',
                'note' => 'Move to cash',
                'transfer_date' => '2026-01-02',
            ])
            ->assertCreated();

        $transferId = $transferResponse->json('data.id');

        $expenseResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '50.00',
                'note' => 'Lunch',
                'transaction_date' => '2026-01-03',
            ])
            ->assertCreated();

        $expenseId = $expenseResponse->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/transfers/'.$transferId, [
                'from_account_id' => $bankAccountId,
                'to_account_id' => $cashAccountId,
                'amount' => '300.00',
                'note' => 'Move more to cash',
                'transfer_date' => '2026-01-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '300.00')
            ->assertJsonPath('data.note', 'Move more to cash');

        $accountsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/accounts')
            ->assertOk();

        $accounts = collect($accountsResponse->json('data'))->keyBy('id');

        $this->assertSame('350.00', $accounts[$cashAccountId]['current_balance']);
        $this->assertSame('700.00', $accounts[$bankAccountId]['current_balance']);

        $transactionsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/transactions')
            ->assertOk();

        $transactions = collect($transactionsResponse->json('data'));
        $sourceTransferTransaction = $transactions->first(
            fn (array $transaction): bool => $transaction['reference_type'] === 'TRANSFER'
                && $transaction['reference_id'] === $transferId
                && $transaction['type'] === 'TRANSFER'
        );
        $destinationTransferTransaction = $transactions->first(
            fn (array $transaction): bool => $transaction['reference_type'] === 'TRANSFER'
                && $transaction['reference_id'] === $transferId
                && $transaction['type'] === 'DEPOSIT'
                && $transaction['account']['id'] === $cashAccountId
        );
        $expenseTransaction = $transactions->firstWhere('id', $expenseId);

        $this->assertNotNull($sourceTransferTransaction);
        $this->assertNotNull($destinationTransferTransaction);
        $this->assertNotNull($expenseTransaction);
        $this->assertSame('1000.00', $sourceTransferTransaction['balance_before']);
        $this->assertSame('700.00', $sourceTransferTransaction['balance_after']);
        $this->assertSame('100.00', $destinationTransferTransaction['balance_before']);
        $this->assertSame('400.00', $destinationTransferTransaction['balance_after']);
        $this->assertSame('400.00', $expenseTransaction['balance_before']);
        $this->assertSame('350.00', $expenseTransaction['balance_after']);
    }
}
