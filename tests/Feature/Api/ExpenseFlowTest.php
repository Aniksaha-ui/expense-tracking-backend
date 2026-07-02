<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Carbon;
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

    public function test_transactions_can_be_filtered_by_backend_search_query(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Search Example',
            'email' => 'search@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Office Cash',
                'type' => 'CASH',
                'opening_balance' => '1200.00',
                'opening_balance_date' => '2026-02-01',
            ])
            ->assertCreated();

        $cashAccountId = $cashAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];

        $transportCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Transportation')['id'];

        $groceriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '150.00',
                'note' => 'Monthly groceries',
                'transaction_date' => '2026-02-02',
            ])
            ->assertCreated();

        $taxiResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $transportCategoryId,
                'amount' => '80.00',
                'note' => 'Taxi fare',
                'transaction_date' => '2026-02-03',
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/transactions?search=grocer')
            ->assertOk();

        $transactions = $response->json('data');

        $this->assertCount(1, $transactions);
        $this->assertSame($groceriesResponse->json('data.id'), $transactions[0]['id']);
        $this->assertSame('Monthly groceries', $transactions[0]['note']);
        $this->assertNotSame($taxiResponse->json('data.id'), $transactions[0]['id']);
    }

    public function test_daywise_expense_report_groups_expenses_by_date_and_category(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Report Example',
            'email' => 'report@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Report Cash',
                'type' => 'CASH',
                'opening_balance' => '2000.00',
                'opening_balance_date' => '2026-03-01',
            ])
            ->assertCreated();

        $cashAccountId = $cashAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];
        $transportCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Transportation')['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '100.00',
                'note' => 'Breakfast',
                'transaction_date' => '2026-03-02',
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '50.00',
                'note' => 'Dinner',
                'transaction_date' => '2026-03-02',
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $transportCategoryId,
                'amount' => '80.00',
                'note' => 'Taxi',
                'transaction_date' => '2026-03-02',
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/expense', [
                'account_id' => $cashAccountId,
                'category_id' => $foodCategoryId,
                'amount' => '60.00',
                'note' => 'Lunch',
                'transaction_date' => '2026-03-03',
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/daywise-expenses?from_date=2026-03-02&to_date=2026-03-03')
            ->assertOk();

        $rows = collect($response->json('data'));

        $foodMarchSecond = $rows->first(fn (array $row): bool => $row['expense_date'] === '2026-03-02' && $row['category_id'] === $foodCategoryId);
        $transportMarchSecond = $rows->first(fn (array $row): bool => $row['expense_date'] === '2026-03-02' && $row['category_id'] === $transportCategoryId);
        $foodMarchThird = $rows->first(fn (array $row): bool => $row['expense_date'] === '2026-03-03' && $row['category_id'] === $foodCategoryId);

        $this->assertCount(3, $rows);
        $this->assertNotNull($foodMarchSecond);
        $this->assertNotNull($transportMarchSecond);
        $this->assertNotNull($foodMarchThird);
        $this->assertSame('150.00', $foodMarchSecond['total_amount']);
        $this->assertSame(2, $foodMarchSecond['transaction_count']);
        $this->assertSame('80.00', $transportMarchSecond['total_amount']);
        $this->assertSame(1, $transportMarchSecond['transaction_count']);
        $this->assertSame('60.00', $foodMarchThird['total_amount']);
        $this->assertSame(1, $foodMarchThird['transaction_count']);
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

    public function test_current_month_weekly_expense_analysis_groups_expenses_by_week_for_the_signed_in_user(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');

        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Weekly Report Example',
            'email' => 'weekly-report@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $cashAccountResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Weekly Cash',
                'type' => 'CASH',
                'opening_balance' => '3000.00',
                'opening_balance_date' => '2026-07-01',
            ])
            ->assertCreated();

        $cashAccountId = $cashAccountResponse->json('data.id');

        $categoriesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories')
            ->assertOk();

        $foodCategoryId = collect($categoriesResponse->json('data'))
            ->firstWhere('name', 'Food')['id'];

        $datesAndAmounts = [
            ['2026-07-02', '100.00'],
            ['2026-07-05', '50.00'],
            ['2026-07-08', '75.00'],
            ['2026-07-14', '125.00'],
        ];

        foreach ($datesAndAmounts as [$transactionDate, $amount]) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/transactions/expense', [
                    'account_id' => $cashAccountId,
                    'category_id' => $foodCategoryId,
                    'amount' => $amount,
                    'note' => 'Weekly expense',
                    'transaction_date' => $transactionDate,
                ])
                ->assertCreated();
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/weekly-current-month-analysis')
            ->assertOk();

        $data = $response->json('data');
        $weeks = collect($data['weeks']);

        $this->assertSame('July', $data['month']['month_name']);
        $this->assertSame('2026-07-01', $data['month']['from_date']);
        $this->assertSame('2026-07-31', $data['month']['to_date']);
        $this->assertSame(5, $data['summary']['weeks_in_month']);
        $this->assertSame(3, $data['summary']['active_weeks']);
        $this->assertSame(4, $data['summary']['total_transactions']);
        $this->assertSame('350.00', $data['summary']['total_expense']);

        $firstWeek = $weeks->firstWhere('week_sequence', 1);
        $secondWeek = $weeks->firstWhere('week_sequence', 2);
        $thirdWeek = $weeks->firstWhere('week_sequence', 3);
        $fifthWeek = $weeks->firstWhere('week_sequence', 5);

        $this->assertSame('2026-07-01', $firstWeek['week_start']);
        $this->assertSame('2026-07-05', $firstWeek['week_end']);
        $this->assertSame(2, $firstWeek['transaction_count']);
        $this->assertSame('150.00', $firstWeek['total_expense']);
        $this->assertSame('75.00', $firstWeek['average_expense']);

        $this->assertSame('2026-07-06', $secondWeek['week_start']);
        $this->assertSame('2026-07-12', $secondWeek['week_end']);
        $this->assertSame(1, $secondWeek['transaction_count']);
        $this->assertSame('75.00', $secondWeek['total_expense']);

        $this->assertSame('2026-07-13', $thirdWeek['week_start']);
        $this->assertSame('2026-07-19', $thirdWeek['week_end']);
        $this->assertSame(1, $thirdWeek['transaction_count']);
        $this->assertSame('125.00', $thirdWeek['total_expense']);

        $this->assertSame('2026-07-27', $fifthWeek['week_start']);
        $this->assertSame('2026-07-31', $fifthWeek['week_end']);
        $this->assertSame(0, $fifthWeek['transaction_count']);
        $this->assertSame('0.00', $fifthWeek['total_expense']);

        Carbon::setTestNow();
    }
}
