<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'title',
        'amount',
        'frequency',
        'start_date',
        'next_run_date',
        'end_date',
        'last_run_at',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
