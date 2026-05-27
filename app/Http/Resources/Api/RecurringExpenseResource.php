<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'amount' => $this->amount,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date,
            'next_run_date' => $this->next_run_date,
            'end_date' => $this->end_date,
            'last_run_at' => $this->last_run_at,
            'is_active' => (bool) $this->is_active,
            'note' => $this->note,
            'account' => [
                'id' => $this->account_id,
                'name' => $this->account_name,
                'type' => $this->account_type,
            ],
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category_name,
                'type' => $this->category_type,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
