<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'description', 'achieved_on', 'organization', 'reference_url'];

    protected function casts(): array
    {
        return ['achieved_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
