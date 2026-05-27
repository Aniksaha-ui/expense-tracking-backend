<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class JwtService
{
    public function createToken(User $user): array
    {
        $now = now();
        $expiresAt = $now->copy()->addMinutes(config('services.jwt.ttl'));
        $payload = [
            'iss' => config('services.jwt.issuer'),
            'sub' => $user->id,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => $this->encode($payload),
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt->diffInSeconds($now),
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    public function authenticate(Request $request): ?Authenticatable
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                return null;
            }

            $payload = $this->decode($token);

            if ($this->isBlacklisted($payload['jti'])) {
                return null;
            }

            return User::query()->find($payload['sub']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function invalidate(string $token, int $userId): void
    {
        $payload = $this->decode($token);

        DB::table('jwt_token_blacklists')->updateOrInsert(
            ['jti' => $payload['jti']],
            [
                'user_id' => $userId,
                'expires_at' => Carbon::createFromTimestamp($payload['exp'])->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new RuntimeException('Invalid token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Invalid token.');
        }

        $expectedSignature = $this->sign($encodedHeader.'.'.$encodedPayload);
        $providedSignature = $this->base64UrlDecode($encodedSignature);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Token expired.');
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ], JSON_THROW_ON_ERROR));

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode($this->sign($header.'.'.$encodedPayload));

        return $header.'.'.$encodedPayload.'.'.$signature;
    }

    private function sign(string $data): string
    {
        $secret = (string) config('services.jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return hash_hmac('sha256', $data, $secret, true);
    }

    private function isBlacklisted(string $jti): bool
    {
        return DB::table('jwt_token_blacklists')
            ->where('jti', $jti)
            ->exists();
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = 4 - (strlen($data) % 4);

        if ($padding < 4) {
            $data .= str_repeat('=', $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
