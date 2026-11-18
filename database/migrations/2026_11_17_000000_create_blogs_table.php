<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->text('content')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->text('short_description')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('category', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->default('react');
            $table->string('fa_icon', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->integer('view_count')->default(0);
            $table->string('author_name', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('published_date', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->boolean('isPublished')->default(true);
            $table->string('image', 255)->charset('utf8mb4')->collation('utf8mb4_0900_ai_ci')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
