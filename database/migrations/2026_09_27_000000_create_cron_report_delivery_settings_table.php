<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cron_report_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_key', 80);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->json('email_recipients')->nullable();
            $table->boolean('telegram_enabled')->default(false);
            $table->json('telegram_chat_ids')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'job_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('cron_report_delivery_settings');
    }
};
