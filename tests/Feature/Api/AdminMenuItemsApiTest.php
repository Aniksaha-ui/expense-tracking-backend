<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class AdminMenuItemsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_crud_matches_cloned_admin_behavior(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Menu Admin',
            'email' => 'menu-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.token');

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/menu_items', [
                'title' => 'Dashboard',
                'path' => '/admin/dashboard',
                'icon' => 'DashboardIcon',
                'location' => 'main',
                'order' => 1,
                'roles' => ['admin', 'guide'],
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('isExecture', 'success')
            ->assertJsonPath('message', 'Menu item created successfully')
            ->assertJsonPath('data.title', 'Dashboard');

        $menuItemId = $createResponse->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/menu_items?page=1&search=Dash')
            ->assertOk()
            ->assertJsonPath('isExecture', 'success')
            ->assertJsonPath('message', 'Menu items retrieved successfully')
            ->assertJsonPath('data.data.0.title', 'Dashboard')
            ->assertJsonPath('data.data.0.roles.0', 'admin');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/admin/menu_items/{$menuItemId}")
            ->assertOk()
            ->assertJsonPath('isExecture', 'success')
            ->assertJsonPath('message', 'Menu item retrieved successfully')
            ->assertJsonPath('data.roles.1', 'guide');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/admin/menu_items/update/{$menuItemId}", [
                'title' => 'Dashboard Updated',
                'path' => '/admin/dashboard',
                'icon' => 'DashboardIcon',
                'location' => 'bottom',
                'order' => 2,
                'roles' => ['admin'],
            ])
            ->assertOk()
            ->assertJsonPath('isExecture', 'success')
            ->assertJsonPath('message', 'Menu item updated successfully')
            ->assertJsonPath('data.title', 'Dashboard Updated');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/admin/menu_items/{$menuItemId}")
            ->assertOk()
            ->assertJsonPath('isExecture', 'success')
            ->assertJsonPath('message', 'Menu item deleted successfully');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/admin/menu_items/{$menuItemId}")
            ->assertNotFound()
            ->assertJsonPath('isExecture', 'failed')
            ->assertJsonPath('message', 'Menu item not found');
    }
}
