<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            $table->index(
                ['tracker_id', 'rotator_id', 'ref_url', 'ip_address'],
                'tracker_stats_referrer_performance_idx',
            );
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            $table->index(
                ['rotator_id', 'ref_url', 'ip_address'],
                'rotator_stats_referrer_performance_idx',
            );
        });

        Schema::table('banner_stats', function (Blueprint $table) {
            $table->index(
                ['banner_id', 'ref_url', 'event_type', 'ip_address'],
                'banner_stats_referrer_performance_idx',
            );
            $table->index(
                ['banner_rotator_id', 'ref_url', 'event_type', 'ip_address'],
                'banner_rotator_stats_referrer_performance_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            $table->dropIndex('tracker_stats_referrer_performance_idx');
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            $table->dropIndex('rotator_stats_referrer_performance_idx');
        });

        Schema::table('banner_stats', function (Blueprint $table) {
            $table->dropIndex('banner_stats_referrer_performance_idx');
            $table->dropIndex('banner_rotator_stats_referrer_performance_idx');
        });
    }
};
