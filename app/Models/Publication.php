<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $fillable = [
        'title', 'abstract', 'authors', 'publication_date', 'publisher', 'doi', 'url', 'pdf_url', 'status',
    ];
}
