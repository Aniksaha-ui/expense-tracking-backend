<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some deployments received the table before this migration was added
        // to the migration history. Avoid failing those deployments while still
        // recording the migration as applied.
        if (Schema::hasTable('financial_goals')) {
            return;
        }

        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 12, 2);
            $table->decimal('current_saved_amount', 12, 2)->default(0);
            $table->date('target_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
