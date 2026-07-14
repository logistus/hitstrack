<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('daily_link_breakdown_stats');
        Schema::dropIfExists('daily_banner_breakdown_stats');

        Schema::table('banner_stats', function (Blueprint $table) {
            $table->dropIndex('banner_stats_banner_ip_idx');
            $table->dropIndex('banner_stats_referrer_performance_idx');
            $table->dropIndex('banner_rotator_stats_referrer_performance_idx');
            $table->dropColumn([
                'ip_address',
                'device_type',
                'operating_system',
                'browser',
                'country_code',
            ]);
        });

        foreach (['tracker_stats', 'rotator_stats'] as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) {
                $table->dropColumn([
                    'device_type',
                    'operating_system',
                    'browser',
                    'country_code',
                ]);
            });
        }

        Schema::table('daily_banner_referrer_stats', function (Blueprint $table) {
            $table->dropColumn(['daily_unique_impressions', 'daily_unique_clicks']);
        });

        Schema::table('daily_banner_rotator_banner_stats', function (Blueprint $table) {
            $table->dropColumn(['daily_unique_impressions', 'daily_unique_clicks']);
        });
    }

    public function down(): void
    {
        Schema::table('banner_stats', function (Blueprint $table) {
            $table->string('ip_address')->nullable();
            $table->string('device_type')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('browser')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->index(['banner_id', 'ip_address'], 'banner_stats_banner_ip_idx');
            $table->index(['banner_id', 'ref_url', 'event_type', 'ip_address'], 'banner_stats_referrer_performance_idx');
            $table->index(['banner_rotator_id', 'ref_url', 'event_type', 'ip_address'], 'banner_rotator_stats_referrer_performance_idx');
        });

        foreach (['tracker_stats', 'rotator_stats'] as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) {
                $table->string('device_type')->nullable();
                $table->string('operating_system')->nullable();
                $table->string('browser')->nullable();
                $table->string('country_code', 2)->nullable();
            });
        }

        Schema::table('daily_banner_referrer_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('daily_unique_impressions')->default(0);
            $table->unsignedBigInteger('daily_unique_clicks')->default(0);
        });

        Schema::table('daily_banner_rotator_banner_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('daily_unique_impressions')->default(0);
            $table->unsignedBigInteger('daily_unique_clicks')->default(0);
        });
    }
};
