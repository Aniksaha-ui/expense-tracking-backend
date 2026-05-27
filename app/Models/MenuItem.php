<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'title',
        'path',
        'icon',
        'location',
        'parent_id',
        'order',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->with('children')->orderBy('order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
