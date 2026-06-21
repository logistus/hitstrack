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
        if (! Schema::hasTable('pixel_stats') || ! Schema::hasColumn('pixel_stats', 'pixel_slug')) {
            return;
        }

        Schema::table('pixel_stats', function (Blueprint $table) {
            $table->string('pixel_slug')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pixel_stats') || ! Schema::hasColumn('pixel_stats', 'pixel_slug')) {
            return;
        }

        Schema::table('pixel_stats', function (Blueprint $table) {
            $table->string('pixel_slug')->nullable(false)->change();
        });
    }
};
