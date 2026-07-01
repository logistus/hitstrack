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
        if (! Schema::hasColumn('user_types', 'max_link_trackers')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_link_trackers')->nullable()->after('label');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_link_rotators')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_link_rotators')->nullable()->after('max_link_trackers');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_banner_trackers')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_banner_trackers')->nullable()->after('max_link_rotators');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_banner_rotators')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_banner_rotators')->nullable()->after('max_banner_trackers');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'max_banner_rotators',
            'max_banner_trackers',
            'max_link_rotators',
            'max_link_trackers',
        ] as $column) {
            if (Schema::hasColumn('user_types', $column)) {
                Schema::table('user_types', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
