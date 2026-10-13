<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialGoal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'description', 'target_amount', 'current_saved_amount', 'target_date'];

    protected function casts(): array
    {
        return ['target_amount' => 'decimal:2', 'current_saved_amount' => 'decimal:2', 'target_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
