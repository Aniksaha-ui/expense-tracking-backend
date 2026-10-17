<?php

namespace Tests\Feature;

use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_deduplicated_and_follow_preferences(): void
    {
        $token = $this->register('notification-owner@example.com');
        $service = app(NotificationService::class);
        $userId = \App\Models\User::where('email', 'notification-owner@example.com')->value('id');
        $service->create($userId, 'budget_threshold', 'Budget alert', 'Budget is nearly used.', 'budget:1:80', [], '/budgets');
        $service->create($userId, 'budget_threshold', 'Budget alert', 'Budget is nearly used.', 'budget:1:80', [], '/budgets');

        $this->withToken($token)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 1);
        $this->withToken($token)->putJson('/api/notification-preferences', ['preferences' => [['type' => 'budget_threshold', 'in_app_enabled' => false]]])->assertOk();
        $service->create($userId, 'budget_threshold', 'New budget alert', 'This must stay suppressed.', 'budget:1:100');
        $this->withToken($token)->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_bill_lifecycle_is_scoped_to_the_owner(): void
    {
        $owner = $this->register('bill-owner@example.com');
        $other = $this->register('bill-other@example.com');
        $bill = $this->withToken($owner)->postJson('/api/bills', ['name' => 'Internet', 'amount' => '1200.00', 'due_date' => now()->addDay()->toDateString(), 'frequency' => 'monthly'])->assertCreated()->json('data');

        $this->withToken($other)->putJson('/api/bills/'.$bill['id'], ['name' => 'Changed'])->assertNotFound();
        $this->withToken($owner)->postJson('/api/bills/'.$bill['id'].'/paid')->assertOk()->assertJsonPath('data.due_date', now()->addMonth()->addDay()->toDateString());
    }

    private function register(string $email): string
    {
        return $this->postJson('/api/auth/register', ['name' => 'Notification User', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated()->json('data.token');
    }
}
