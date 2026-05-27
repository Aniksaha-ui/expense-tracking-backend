<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'note' => $this->note,
            'transfer_date' => $this->transfer_date,
            'is_withdrawal' => (bool) $this->is_withdrawal,
            'from_account' => [
                'id' => $this->from_account_id,
                'name' => $this->from_account_name,
                'type' => $this->from_account_type,
            ],
            'to_account' => [
                'id' => $this->to_account_id,
                'name' => $this->to_account_name,
                'type' => $this->to_account_type,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
