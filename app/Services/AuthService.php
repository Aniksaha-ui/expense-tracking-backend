<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly CategoryService $categoryService,
    ) {
    }

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = User::query()->findOrFail($userId);
            $this->categoryService->seedDefaultCategoriesForUser($userId);
            $tokenData = $this->jwtService->createToken($user);

            return [
                'user' => $user->fresh(),
                ...$tokenData,
            ];
        });
    }

    public function login(array $data): array
    {
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new RuntimeException('Invalid email or password.');
        }

        $tokenData = $this->jwtService->createToken($user);

        return [
            'user' => $user,
            ...$tokenData,
        ];
    }

    public function logout(User $user, string $token): void
    {
        $this->jwtService->invalidate($token, $user->id);
    }
}
