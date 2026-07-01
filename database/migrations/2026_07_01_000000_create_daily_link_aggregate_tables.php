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
        Schema::create('daily_link_referrer_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rotator_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source_type', ['tracker', 'rotator']);
            $table->unsignedBigInteger('source_id');
            $table->string('ref_url')->nullable();
            $table->char('ref_url_hash', 64);
            $table->unsignedBigInteger('total_hits')->default(0);
            $table->unsignedBigInteger('daily_unique_hits')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'stat_date', 'ref_url_hash'],
                'daily_link_referrer_unique_idx',
            );
            $table->index(['user_id', 'stat_date'], 'daily_link_referrer_user_date_idx');
            $table->index(['user_id', 'ref_url_hash'], 'daily_link_referrer_user_ref_idx');
            $table->index(['tracker_id', 'stat_date'], 'daily_link_referrer_tracker_date_idx');
            $table->index(['rotator_id', 'stat_date'], 'daily_link_referrer_rotator_date_idx');
        });

        Schema::create('daily_link_breakdown_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rotator_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source_type', ['tracker', 'rotator']);
            $table->unsignedBigInteger('source_id');
            $table->enum('breakdown_type', ['device_type', 'operating_system', 'browser', 'country_code']);
            $table->string('label')->nullable();
            $table->char('label_hash', 64);
            $table->unsignedBigInteger('total_hits')->default(0);
            $table->unsignedBigInteger('daily_unique_hits')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'stat_date', 'breakdown_type', 'label_hash'],
                'daily_link_breakdown_unique_idx',
            );
            $table->index(['user_id', 'stat_date'], 'daily_link_breakdown_user_date_idx');
            $table->index(['user_id', 'breakdown_type', 'label_hash'], 'daily_link_breakdown_user_label_idx');
            $table->index(['tracker_id', 'stat_date'], 'daily_link_breakdown_tracker_date_idx');
            $table->index(['rotator_id', 'stat_date'], 'daily_link_breakdown_rotator_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_link_breakdown_stats');
        Schema::dropIfExists('daily_link_referrer_stats');
    }
};
