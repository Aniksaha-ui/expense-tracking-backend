<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $table = 'expriences';

    protected $fillable = [
        'job_title',
        'company',
        'location',
        'description',
        'start_date',
        'end_date',
        'is_current',
        'image_url',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
    }
}
