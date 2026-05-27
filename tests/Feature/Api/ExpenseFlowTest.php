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
}
