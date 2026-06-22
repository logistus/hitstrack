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
        if (! Schema::hasTable('banner_rotators') || Schema::hasColumn('banner_rotators', 'name')) {
            return;
        }

        Schema::table('banner_rotators', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('banner_rotators') || ! Schema::hasColumn('banner_rotators', 'name')) {
            return;
        }

        Schema::table('banner_rotators', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
