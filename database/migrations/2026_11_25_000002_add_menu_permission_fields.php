<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'user_id')) {
            Schema::table('menu_items', fn (Blueprint $table) => $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete());
        }
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('users'));
        }
    }

    public function down(): void
    {
        // Existing installations may already own these columns and their data.
    }
};
