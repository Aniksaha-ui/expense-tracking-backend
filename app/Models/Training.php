<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'title',
        'provider',
        'description',
        'start_date',
        'end_date',
        'certificate_url',
        'image_url',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
    }
}
