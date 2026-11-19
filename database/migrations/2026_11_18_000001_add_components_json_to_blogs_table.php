<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('blogs', 'components_json')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->json('components_json')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('blogs', 'components_json')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('components_json');
            });
        }
    }
};
