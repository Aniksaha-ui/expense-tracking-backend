<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('provider', 191);
            $table->string('description', 191);
            $table->string('start_date', 191);
            $table->string('end_date', 191);
            $table->string('certificate_url', 191);
            $table->string('image_url', 191);
            $table->integer('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
