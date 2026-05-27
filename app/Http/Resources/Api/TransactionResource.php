<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'note' => $this->note,
            'transaction_date' => $this->transaction_date,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'account' => [
                'id' => $this->account_id,
                'name' => $this->account_name,
                'type' => $this->account_type,
            ],
            'category' => $this->category_id ? [
                'id' => $this->category_id,
                'name' => $this->category_name,
                'type' => $this->category_type,
            ] : null,
            'related_account' => $this->related_account_id ? [
                'id' => $this->related_account_id,
                'name' => $this->related_account_name,
                'type' => $this->related_account_type,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
