<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileManagerFile extends Model
{
    protected $fillable = ['user_id', 'folder_id', 'name', 'original_name', 'disk', 'path', 'mime_type', 'size'];
}
