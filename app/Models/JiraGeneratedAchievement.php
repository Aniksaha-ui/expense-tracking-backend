<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JiraGeneratedAchievement extends Model { protected $fillable=['user_id','type','title','achievement_date','organization','description','reference_url','status','manually_edited','generated_at']; protected function casts(): array { return ['achievement_date'=>'date','generated_at'=>'datetime','manually_edited'=>'boolean']; } public function issues() { return $this->belongsToMany(JiraIssue::class, 'jira_generated_achievement_issue'); } }
