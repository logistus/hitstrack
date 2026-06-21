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
        Schema::create('pixel_stats', function (Blueprint $table) {
            $table->id();
            $table->string('pixel_slug')->nullable();
            $table->text('page_url')->nullable();
            $table->string('ref_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_type')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('browser')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'pixel_stats_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pixel_stats');
    }
};
