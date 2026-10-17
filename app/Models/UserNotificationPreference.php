<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserNotificationPreference extends Model { protected $fillable=['user_id','type','in_app_enabled']; protected function casts(): array { return ['in_app_enabled'=>'boolean']; } }
