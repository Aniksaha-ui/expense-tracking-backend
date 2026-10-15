<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['workplace', 'personal', 'other']);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->date('achieved_on');
            $table->string('organization', 160)->nullable();
            $table->string('reference_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
