<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'user_name' => $this->user_name ?? null,
            'user_email' => $this->user_email ?? null,
            'title' => (string) $this->title,
            'path' => $this->path,
            'icon' => $this->icon,
            'location' => (string) ($this->location ?? 'main'),
            'parent_id' => $this->parent_id !== null ? (int) $this->parent_id : null,
            'order' => (int) ($this->order ?? 0),
            'roles' => $this->normalizeRoles($this->roles ?? null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeRoles(mixed $roles): ?array
    {
        if ($roles === null) {
            return null;
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($roles) ? array_values($roles) : [];
    }
}
