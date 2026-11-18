<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title',
        'content',
        'components_json',
        'short_description',
        'category',
        'fa_icon',
        'view_count',
        'author_name',
        'published_date',
        'isPublished',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'isPublished' => 'boolean',
            'components_json' => 'array',
        ];
    }
}
