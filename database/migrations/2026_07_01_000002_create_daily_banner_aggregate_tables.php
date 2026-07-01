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
        Schema::create('daily_banner_referrer_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('banner_rotator_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source_type', ['banner', 'rotator']);
            $table->unsignedBigInteger('source_id');
            $table->string('ref_url')->nullable();
            $table->char('ref_url_hash', 64);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('daily_unique_impressions')->default(0);
            $table->unsignedBigInteger('daily_unique_clicks')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'stat_date', 'ref_url_hash'],
                'daily_banner_referrer_unique_idx',
            );
            $table->index(['user_id', 'stat_date'], 'daily_banner_referrer_user_date_idx');
            $table->index(['banner_id', 'stat_date'], 'daily_banner_referrer_banner_date_idx');
            $table->index(['banner_rotator_id', 'stat_date'], 'daily_banner_referrer_rotator_date_idx');
        });

        Schema::create('daily_banner_breakdown_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('banner_rotator_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source_type', ['banner', 'rotator']);
            $table->unsignedBigInteger('source_id');
            $table->enum('breakdown_type', ['device_type', 'operating_system', 'browser', 'country_code']);
            $table->string('label')->nullable();
            $table->char('label_hash', 64);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('daily_unique_impressions')->default(0);
            $table->unsignedBigInteger('daily_unique_clicks')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'stat_date', 'breakdown_type', 'label_hash'],
                'daily_banner_breakdown_unique_idx',
            );
            $table->index(['user_id', 'stat_date'], 'daily_banner_breakdown_user_date_idx');
            $table->index(['banner_id', 'stat_date'], 'daily_banner_breakdown_banner_date_idx');
            $table->index(['banner_rotator_id', 'stat_date'], 'daily_banner_breakdown_rotator_date_idx');
        });

        Schema::create('daily_banner_rotator_banner_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_rotator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('daily_unique_impressions')->default(0);
            $table->unsignedBigInteger('daily_unique_clicks')->default(0);
            $table->timestamps();

            $table->unique(['banner_rotator_id', 'banner_id', 'stat_date'], 'daily_banner_rotator_banner_unique_idx');
            $table->index(['user_id', 'stat_date'], 'daily_banner_rotator_banner_user_date_idx');
            $table->index(['banner_id', 'stat_date'], 'daily_banner_rotator_banner_banner_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_banner_rotator_banner_stats');
        Schema::dropIfExists('daily_banner_breakdown_stats');
        Schema::dropIfExists('daily_banner_referrer_stats');
    }
};
