<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_manager_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('file_manager_files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['file_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_manager_file_versions');
    }
};
