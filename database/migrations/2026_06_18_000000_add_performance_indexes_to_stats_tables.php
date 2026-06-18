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
            $table->index(['tracker_id', 'created_at'], 'tracker_stats_tracker_created_idx');
            $table->index(['tracker_id', 'ref_url'], 'tracker_stats_tracker_ref_idx');
            $table->index(['tracker_id', 'ip_address'], 'tracker_stats_tracker_ip_idx');
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            $table->index(['rotator_id', 'created_at'], 'rotator_stats_rotator_created_idx');
            $table->index(['rotator_id', 'ref_url'], 'rotator_stats_rotator_ref_idx');
            $table->index(['rotator_id', 'ip_address'], 'rotator_stats_rotator_ip_idx');
            $table->index(['rotator_id', 'tracker_id'], 'rotator_stats_rotator_tracker_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracker_stats', function (Blueprint $table) {
            $table->dropIndex('tracker_stats_tracker_created_idx');
            $table->dropIndex('tracker_stats_tracker_ref_idx');
            $table->dropIndex('tracker_stats_tracker_ip_idx');
        });

        Schema::table('rotator_stats', function (Blueprint $table) {
            $table->dropIndex('rotator_stats_rotator_created_idx');
            $table->dropIndex('rotator_stats_rotator_ref_idx');
            $table->dropIndex('rotator_stats_rotator_ip_idx');
            $table->dropIndex('rotator_stats_rotator_tracker_idx');
        });
    }
};
