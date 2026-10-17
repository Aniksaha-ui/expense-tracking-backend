<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Bill extends Model { protected $fillable=['user_id','account_id','category_id','name','amount','due_date','frequency','is_active','paid_at','note']; protected function casts():array{return ['due_date'=>'date','paid_at'=>'datetime','is_active'=>'boolean'];} }
