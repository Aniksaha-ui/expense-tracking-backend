<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portfolio_profiles')) {
            return;
        }

        Schema::create('portfolio_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('headline', 191)->nullable();
            $table->string('tagline', 255)->nullable();
            $table->longText('bio')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->string('resume_url', 500)->nullable();
            $table->string('availability_status', 30)->default('available');
            $table->string('availability_text', 191)->nullable();
            $table->unsignedInteger('years_of_experience')->default(0);
            $table->json('socials')->nullable();
            $table->json('skills')->nullable();
            $table->json('stats')->nullable();
            $table->string('seo_title', 191)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_profiles');
    }
};
