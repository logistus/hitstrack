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
            if (! Schema::hasColumn('tracker_stats', 'device_type')) {
                $table->enum('device_type', ['desktop', 'tablet', 'mobile'])->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('tracker_stats', 'operating_system')) {
                $table->string('operating_system')->nullable()->after('device_type');
            }

            if (! Schema::hasColumn('tracker_stats', 'browser')) {
                $table->string('browser')->nullable()->after('operating_system');
            }
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('rotator_stats', 'device_type')) {
                $table->enum('device_type', ['desktop', 'tablet', 'mobile'])->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('rotator_stats', 'operating_system')) {
                $table->string('operating_system')->nullable()->after('device_type');
            }

            if (! Schema::hasColumn('rotator_stats', 'browser')) {
                $table->string('browser')->nullable()->after('operating_system');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            $table->dropColumn(['device_type', 'operating_system', 'browser']);
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            $table->dropColumn(['device_type', 'operating_system', 'browser']);
        });
    }
};
