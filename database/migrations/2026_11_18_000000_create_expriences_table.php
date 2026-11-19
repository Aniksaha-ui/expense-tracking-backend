<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expriences', function (Blueprint $table) {
            $table->id();
            $table->string('job_title', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('company', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('location', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->longText('description')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('start_date', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('end_date', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('is_current', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('image_url', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expriences');
    }
};
