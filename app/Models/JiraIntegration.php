<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JiraIntegration extends Model { protected $fillable = ['user_id','base_url','email','api_token','jql']; protected function casts(): array { return ['api_token' => 'encrypted']; } }
