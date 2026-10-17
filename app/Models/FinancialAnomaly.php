<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class FinancialAnomaly extends Model { protected $fillable=['user_id','transaction_id','type','title','message','amount','baseline_amount','detected_at','reviewed_at']; protected function casts():array{return ['detected_at'=>'datetime','reviewed_at'=>'datetime'];} }
