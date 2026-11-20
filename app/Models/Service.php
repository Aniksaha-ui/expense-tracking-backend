<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'title',
        'description',
        'icon',
        'image_url',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
    }
}
