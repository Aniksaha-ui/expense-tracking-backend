<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronReportDeliverySetting extends Model
{
    protected $fillable = ['user_id', 'job_key', 'is_enabled', 'email_enabled', 'email_recipients', 'telegram_enabled', 'telegram_chat_ids'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'email_enabled' => 'boolean', 'telegram_enabled' => 'boolean', 'email_recipients' => 'array', 'telegram_chat_ids' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
