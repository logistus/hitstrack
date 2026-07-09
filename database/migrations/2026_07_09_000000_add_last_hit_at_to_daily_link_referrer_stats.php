<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_link_referrer_stats', function (Blueprint $table) {
            $table->timestamp('last_hit_at')->nullable()->after('daily_unique_hits');
        });

        DB::table('daily_link_referrer_stats')
            ->whereNull('last_hit_at')
            ->update(['last_hit_at' => DB::raw('stat_date')]);
    }

    public function down(): void
    {
        Schema::table('daily_link_referrer_stats', function (Blueprint $table) {
            $table->dropColumn('last_hit_at');
        });
    }
};
