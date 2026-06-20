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
        Schema::table('banner_stats', function (Blueprint $table) {
            $table->foreign('banner_rotator_id')
                ->references('id')
                ->on('banner_rotators')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banner_stats', function (Blueprint $table) {
            $table->dropForeign(['banner_rotator_id']);
        });
    }
};
