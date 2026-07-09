<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_banner_referrer_stats', function (Blueprint $table) {
            $table->timestamp('last_impression_at')->nullable()->after('daily_unique_clicks');
        });

        DB::table('daily_banner_referrer_stats')
            ->whereNull('last_impression_at')
            ->where('impressions', '>', 0)
            ->update(['last_impression_at' => DB::raw('stat_date')]);
    }

    public function down(): void
    {
        Schema::table('daily_banner_referrer_stats', function (Blueprint $table) {
            $table->dropColumn('last_impression_at');
        });
    }
};
