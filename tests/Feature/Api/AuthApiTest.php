<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_profile_logout_flow_uses_bearer_token_authentication(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('isExecute', 'success')
            ->assertJsonPath('data.user.email', 'alice@example.com');

        $token = $registerResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('isExecute', 'success')
            ->assertJsonPath('data.email', 'alice@example.com');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('isExecute', 'success');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/profile')
            ->assertStatus(401)
            ->assertJsonPath('isExecute', 'failed');
    }
}
