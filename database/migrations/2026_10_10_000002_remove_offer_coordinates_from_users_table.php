<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'offers_longitude')) {
                $table->dropColumn('offers_longitude');
            }

            if (Schema::hasColumn('users', 'offers_latitude')) {
                $table->dropColumn('offers_latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'offers_latitude')) {
                $table->decimal('offers_latitude', 10, 7)->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'offers_longitude')) {
                $table->decimal('offers_longitude', 10, 7)->nullable()->after('offers_latitude');
            }
        });
    }
};
