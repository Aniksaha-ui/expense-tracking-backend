<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProviderConfiguration extends Model { protected $fillable=['name','is_active','ocr_driver','ocr_enabled','ocr_openai_api_key','google_cloud_project','google_application_credentials','savings_offers_enabled','serper_api_key']; protected function casts(): array { return ['is_active'=>'boolean','ocr_enabled'=>'boolean','savings_offers_enabled'=>'boolean','ocr_openai_api_key'=>'encrypted','google_application_credentials'=>'encrypted','serper_api_key'=>'encrypted']; } }
