<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('offers_latitude', 10, 7)->nullable()->after('password');
            $table->decimal('offers_longitude', 10, 7)->nullable()->after('offers_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['offers_latitude', 'offers_longitude']);
        });
    }
};
