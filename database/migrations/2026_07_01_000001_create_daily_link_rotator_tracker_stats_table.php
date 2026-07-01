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
        Schema::create('daily_link_rotator_tracker_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rotator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_hits')->default(0);
            $table->unsignedBigInteger('daily_unique_hits')->default(0);
            $table->timestamps();

            $table->unique(['rotator_id', 'tracker_id', 'stat_date'], 'daily_link_rotator_tracker_unique_idx');
            $table->index(['user_id', 'stat_date'], 'daily_link_rotator_tracker_user_date_idx');
            $table->index(['tracker_id', 'stat_date'], 'daily_link_rotator_tracker_tracker_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_link_rotator_tracker_stats');
    }
};
