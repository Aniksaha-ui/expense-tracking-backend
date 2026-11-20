<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('abstract', 191);
            $table->string('authors', 191);
            $table->string('publication_date', 191);
            $table->string('publisher', 191);
            $table->string('doi', 191);
            $table->string('url', 191);
            $table->string('pdf_url', 191);
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
