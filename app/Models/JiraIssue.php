<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JiraIssue extends Model { protected $fillable=['user_id','issue_key','summary','payload','jira_created_at','jira_updated_at','last_synced_at']; protected function casts(): array { return ['payload'=>'array','jira_created_at'=>'datetime','jira_updated_at'=>'datetime','last_synced_at'=>'datetime']; } }
