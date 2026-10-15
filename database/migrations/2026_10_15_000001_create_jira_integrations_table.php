<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('jira_integrations', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $table->string('base_url', 2048); $table->string('email', 255); $table->text('api_token'); $table->text('jql')->nullable(); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('jira_integrations'); }
};
