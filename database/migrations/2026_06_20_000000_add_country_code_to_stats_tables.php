<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('tracker_stats', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('browser');
            }
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('rotator_stats', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('browser');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            if (Schema::hasColumn('tracker_stats', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            if (Schema::hasColumn('rotator_stats', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });
    }
};
