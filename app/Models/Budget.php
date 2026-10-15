<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $fillable = ['user_id', 'category_id', 'period_type', 'amount', 'carry_forward'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'carry_forward' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
