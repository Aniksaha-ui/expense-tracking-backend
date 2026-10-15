<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->enum('period_type', ['weekly', 'monthly']);
            $table->decimal('amount', 12, 2);
            $table->boolean('carry_forward')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'category_id', 'period_type']);
        });
    }

    public function down(): void { Schema::dropIfExists('budgets'); }
};
