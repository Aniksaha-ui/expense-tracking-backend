<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class FinancialIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_excludes_transfers_and_returns_an_honest_insufficient_forecast(): void
    {
        $token = $this->register('intelligence@example.com');
        $account = $this->createAccount($token);
        $categoryId = $this->categoryId($token);
        $this->authenticated($token)->postJson('/api/transactions/income', ['account_id' => $account, 'amount' => '500.00', 'note' => 'Salary'])->assertCreated();
        $this->authenticated($token)->postJson('/api/transactions/expense', ['account_id' => $account, 'category_id' => $categoryId, 'amount' => '100.00', 'note' => 'Food'])->assertCreated();

        $this->withToken($token)->getJson('/api/financial-intelligence/dashboard')
            ->assertOk()
            ->assertJsonPath('data.metrics.total_income', '500.00')
            ->assertJsonPath('data.metrics.total_expenses', '100.00')
            ->assertJsonPath('data.metrics.net_cash_flow', '400.00')
            ->assertJsonPath('data.forecast.available', false);
    }

    public function test_goals_are_owned_by_the_authenticated_user(): void
    {
        $owner = $this->register('goal-owner@example.com');
        $other = $this->register('goal-other@example.com');
        $goal = $this->authenticated($owner)->postJson('/api/financial-intelligence/goals', ['name' => 'Emergency fund', 'target_amount' => '12000.00', 'current_saved_amount' => '2000.00', 'target_date' => now()->addYear()->toDateString()])->assertCreated()->json('data');

        $this->authenticated($owner)->getJson('/api/financial-intelligence/goals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.remaining_amount', '10000.00');
        $this->authenticated($other)->putJson('/api/financial-intelligence/goals/'.$goal['id'], ['name' => 'Not allowed'])->assertNotFound();
    }

    private function register(string $email): string
    {
        return $this->postJson('/api/auth/register', ['name' => 'Financial User', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated()->json('data.token');
    }

    private function createAccount(string $token): int
    {
        return $this->authenticated($token)->postJson('/api/accounts', ['name' => 'Cash', 'type' => 'CASH', 'opening_balance' => '0.00'])->assertCreated()->json('data.id');
    }

    private function categoryId(string $token): int
    {
        return collect($this->authenticated($token)->getJson('/api/categories')->assertOk()->json('data'))->firstWhere('type', 'EXPENSE')['id'];
    }

    private function authenticated(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
