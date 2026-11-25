<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class AdminMenuItemsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_crud_and_user_role_sync_work_end_to_end(): void
    {
        config(['menu.manager_email' => 'menu-admin@example.com']);

        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Menu Admin',
            'email' => 'menu-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');
        $userId = $registerResponse->json('data.user.id');
        $headers = ['Authorization' => 'Bearer '.$token];

        // Fixed route catalog + users + roles.
        $this->withHeaders($headers)
            ->getJson('/api/admin/menu_items/options')
            ->assertOk()
            ->assertJsonPath('isExecute', 'success')
            ->assertJsonPath('data.routes.0.path', '/account');

        // Custom / unlisted routes must be rejected.
        $this->withHeaders($headers)
            ->postJson('/api/admin/menu_items', [
                'path' => '/custom-unlisted',
                'title' => 'Custom Name',
            ])
            ->assertUnprocessable();

        // Title is always locked to the fixed route name, ignoring client overrides.
        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/admin/menu_items', [
                'title' => 'Attempted Custom Name',
                'path' => '/account',
                'order' => 1,
                'roles' => ['admin', 'users'],
                'user_id' => $userId,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('isExecute', 'success')
            ->assertJsonPath('data.title', 'Accounts')
            ->assertJsonPath('data.user_name', 'Menu Admin')
            ->assertJsonPath('data.roles.0', 'admin');

        $menuItemId = $createResponse->json('data.id');

        $this->withHeaders($headers)
            ->putJson("/api/admin/menu_items/{$menuItemId}", [
                'title' => 'Still Cannot Edit Name',
                'path' => '/reports',
                'order' => 2,
                'roles' => ['admin'],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Reports')
            ->assertJsonPath('data.path', '/reports');

        // Sync user-wise and role-wise menus.
        $this->withHeaders($headers)
            ->putJson("/api/admin/menu_items/assign/user/{$userId}", [
                'paths' => ['/account', '/budgets'],
            ])
            ->assertOk()
            ->assertJsonPath('isExecute', 'success');

        $this->withHeaders($headers)
            ->putJson('/api/admin/menu_items/assign/role', [
                'role' => 'users',
                'paths' => ['/categories'],
            ])
            ->assertOk()
            ->assertJsonPath('isExecute', 'success');

        $this->withHeaders($headers)
            ->deleteJson("/api/admin/menu_items/{$menuItemId}")
            ->assertOk();
    }
}
