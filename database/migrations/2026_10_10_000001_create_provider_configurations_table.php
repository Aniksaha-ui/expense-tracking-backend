<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('provider_configurations')) {
            return;
        }

        Schema::create('provider_configurations', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->boolean('is_active')->default(false)->index(); $table->string('ocr_driver')->default('google_vision'); $table->boolean('ocr_enabled')->default(true); $table->text('ocr_openai_api_key')->nullable(); $table->string('google_cloud_project')->nullable(); $table->text('google_application_credentials')->nullable(); $table->boolean('savings_offers_enabled')->default(false); $table->text('serper_api_key')->nullable(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('provider_configurations'); }
};
