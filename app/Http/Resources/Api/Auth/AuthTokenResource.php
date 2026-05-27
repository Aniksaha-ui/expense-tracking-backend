<?php

namespace App\Http\Resources\Api\Auth;

use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this['user']),
            'token' => $this['token'],
            'token_type' => $this['token_type'],
            'expires_in' => $this['expires_in'],
            'expires_at' => $this['expires_at'],
        ];
    }
}
