<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'institution_name' => $this->institution_name,
            'current_balance' => $this->current_balance,
            // AccountService returns a stdClass record. Older databases will not
            // have this newly added column until the migration is applied.
            'low_balance_threshold' => data_get($this->resource, 'low_balance_threshold'),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
