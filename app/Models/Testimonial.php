<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'name',
        'designation',
        'feedback',
        'rating',
        'image_url',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
    }
}
