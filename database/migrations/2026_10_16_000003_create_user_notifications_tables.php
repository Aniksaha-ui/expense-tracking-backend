<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80); $table->string('title', 200); $table->text('message');
            $table->json('data')->nullable(); $table->string('action_path', 255)->nullable();
            $table->string('dedupe_key', 191); $table->timestamp('read_at')->nullable(); $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']); $table->index(['user_id', 'read_at', 'created_at']); $table->index(['user_id', 'type']);
        });
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80); $table->boolean('in_app_enabled')->default(true); $table->timestamps();
            $table->unique(['user_id', 'type']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_notification_preferences'); Schema::dropIfExists('user_notifications'); }
};
