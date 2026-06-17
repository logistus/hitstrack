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
        if (Schema::hasColumn('rotators', 'is_active')) {
            Schema::table('rotators', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn('rotator_tracker', 'is_active')) {
            Schema::table('rotator_tracker', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('rotators', 'is_active')) {
            Schema::table('rotators', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('rotation_type');
            });
        }

        if (! Schema::hasColumn('rotator_tracker', 'is_active')) {
            Schema::table('rotator_tracker', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('order_column');
            });
        }
    }
};
