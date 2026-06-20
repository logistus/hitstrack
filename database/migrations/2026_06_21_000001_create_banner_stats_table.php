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
        if (Schema::hasTable('banner_stats')) {
            return;
        }

        Schema::create('banner_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('banner_rotator_id')->nullable();
            $table->enum('event_type', ['impression', 'click']);
            $table->string('page_url')->nullable();
            $table->string('ref_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_type')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('browser')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['banner_id', 'event_type'], 'banner_stats_banner_event_idx');
            $table->index(['banner_rotator_id', 'event_type'], 'banner_stats_rotator_event_idx');
            $table->index(['banner_id', 'created_at'], 'banner_stats_banner_created_idx');
            $table->index(['banner_id', 'ip_address'], 'banner_stats_banner_ip_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_stats');
    }
};
