<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trackers', function (Blueprint $table) {
            $table->string('tracker_name')->nullable()->after('tracker_slug');
        });
    }

    public function down(): void
    {
        Schema::table('trackers', function (Blueprint $table) {
            $table->dropColumn('tracker_name');
        });
    }
};
