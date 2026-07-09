<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('link-stats:rollup {--from=} {--to=} {--fresh} {--prune}', function () {
    if (
        ! Schema::hasTable('daily_link_referrer_stats')
        || ! Schema::hasTable('daily_link_breakdown_stats')
        || ! Schema::hasTable('daily_link_rotator_tracker_stats')
    ) {
        $this->error('Aggregate tables do not exist yet. Run the daily link aggregate migration first.');

        return self::FAILURE;
    }

    $oldestStatDate = collect([
        DB::table('tracker_stats')->min('created_at'),
        DB::table('rotator_stats')->min('created_at'),
    ])->filter()->min();

    $from = $this->option('from')
        ? now()->parse($this->option('from'))->startOfDay()
        : ($oldestStatDate ? now()->parse($oldestStatDate)->startOfDay() : now()->startOfDay());
    $to = $this->option('to')
        ? now()->parse($this->option('to'))->endOfDay()
        : now()->endOfDay();

    if ($from->greaterThan($to)) {
        $this->error('The --from date must be before or equal to the --to date.');

        return self::FAILURE;
    }

    if ($this->option('fresh')) {
        DB::table('daily_link_referrer_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
        DB::table('daily_link_breakdown_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
        DB::table('daily_link_rotator_tracker_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
    }

    $now = now()->toDateTimeString();
    $totalRows = 0;

    foreach (['tracker', 'rotator'] as $sourceType) {
        $totalRows += rollupLinkReferrers($sourceType, $from, $to, $now);

        foreach (['device_type', 'operating_system', 'browser', 'country_code'] as $breakdownType) {
            $totalRows += rollupLinkBreakdowns($sourceType, $breakdownType, $from, $to, $now);
        }
    }

    $totalRows += rollupLinkRotatorTrackers($from, $to, $now);

    $this->info("Rolled up {$totalRows} aggregate rows from {$from->toDateString()} to {$to->toDateString()}.");

    if ($this->option('prune')) {
        $deletedRows = pruneRolledUpLinkStats($from, $to);

        $this->info("Pruned {$deletedRows} raw stat rows before today.");
    }

    return self::SUCCESS;
})->purpose('Roll tracker_stats and rotator_stats into daily aggregate tables');

if (! function_exists('rollupLinkReferrers')) {
    function rollupLinkReferrers(string $sourceType, $from, $to, string $now): int
    {
        $query = match ($sourceType) {
            'tracker' => DB::table('tracker_stats')
                ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
                ->whereBetween('tracker_stats.created_at', [$from, $to])
                ->whereNotNull('tracker_stats.tracker_id')
                ->selectRaw('DATE(tracker_stats.created_at) as stat_date')
                ->selectRaw('trackers.user_id as user_id')
                ->selectRaw('tracker_stats.tracker_id as tracker_id')
                ->selectRaw('NULL as rotator_id')
                ->selectRaw("'tracker' as source_type")
                ->selectRaw('tracker_stats.tracker_id as source_id')
                ->selectRaw('tracker_stats.ref_url as ref_url')
                ->selectRaw("SHA2(COALESCE(tracker_stats.ref_url, ''), 256) as ref_url_hash")
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT tracker_stats.ip_address) as daily_unique_hits')
                ->selectRaw('MAX(tracker_stats.created_at) as last_hit_at')
                ->groupByRaw("DATE(tracker_stats.created_at), trackers.user_id, tracker_stats.tracker_id, tracker_stats.ref_url, SHA2(COALESCE(tracker_stats.ref_url, ''), 256)"),
            'rotator' => DB::table('rotator_stats')
                ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
                ->whereBetween('rotator_stats.created_at', [$from, $to])
                ->whereNotNull('rotator_stats.rotator_id')
                ->selectRaw('DATE(rotator_stats.created_at) as stat_date')
                ->selectRaw('rotators.user_id as user_id')
                ->selectRaw('NULL as tracker_id')
                ->selectRaw('rotator_stats.rotator_id as rotator_id')
                ->selectRaw("'rotator' as source_type")
                ->selectRaw('rotator_stats.rotator_id as source_id')
                ->selectRaw('rotator_stats.ref_url as ref_url')
                ->selectRaw("SHA2(COALESCE(rotator_stats.ref_url, ''), 256) as ref_url_hash")
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT rotator_stats.ip_address) as daily_unique_hits')
                ->selectRaw('MAX(rotator_stats.created_at) as last_hit_at')
                ->groupByRaw("DATE(rotator_stats.created_at), rotators.user_id, rotator_stats.rotator_id, rotator_stats.ref_url, SHA2(COALESCE(rotator_stats.ref_url, ''), 256)"),
        };

        return upsertAggregateRows(
            $query,
            'daily_link_referrer_stats',
            ['source_type', 'source_id', 'stat_date', 'ref_url_hash'],
            ['user_id', 'tracker_id', 'rotator_id', 'ref_url', 'total_hits', 'daily_unique_hits', 'last_hit_at', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('rollupLinkBreakdowns')) {
    function rollupLinkBreakdowns(string $sourceType, string $breakdownType, $from, $to, string $now): int
    {
        $column = match ($breakdownType) {
            'device_type', 'operating_system', 'browser', 'country_code' => $breakdownType,
            default => throw new InvalidArgumentException('Invalid breakdown type.'),
        };

        $query = match ($sourceType) {
            'tracker' => DB::table('tracker_stats')
                ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
                ->whereBetween('tracker_stats.created_at', [$from, $to])
                ->whereNotNull('tracker_stats.tracker_id')
                ->selectRaw('DATE(tracker_stats.created_at) as stat_date')
                ->selectRaw('trackers.user_id as user_id')
                ->selectRaw('tracker_stats.tracker_id as tracker_id')
                ->selectRaw('NULL as rotator_id')
                ->selectRaw("'tracker' as source_type")
                ->selectRaw('tracker_stats.tracker_id as source_id')
                ->selectRaw('? as breakdown_type', [$breakdownType])
                ->selectRaw("tracker_stats.{$column} as label")
                ->selectRaw("SHA2(COALESCE(tracker_stats.{$column}, ''), 256) as label_hash")
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT tracker_stats.ip_address) as daily_unique_hits')
                ->groupByRaw("DATE(tracker_stats.created_at), trackers.user_id, tracker_stats.tracker_id, tracker_stats.{$column}, SHA2(COALESCE(tracker_stats.{$column}, ''), 256)"),
            'rotator' => DB::table('rotator_stats')
                ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
                ->whereBetween('rotator_stats.created_at', [$from, $to])
                ->whereNotNull('rotator_stats.rotator_id')
                ->selectRaw('DATE(rotator_stats.created_at) as stat_date')
                ->selectRaw('rotators.user_id as user_id')
                ->selectRaw('NULL as tracker_id')
                ->selectRaw('rotator_stats.rotator_id as rotator_id')
                ->selectRaw("'rotator' as source_type")
                ->selectRaw('rotator_stats.rotator_id as source_id')
                ->selectRaw('? as breakdown_type', [$breakdownType])
                ->selectRaw("rotator_stats.{$column} as label")
                ->selectRaw("SHA2(COALESCE(rotator_stats.{$column}, ''), 256) as label_hash")
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT rotator_stats.ip_address) as daily_unique_hits')
                ->groupByRaw("DATE(rotator_stats.created_at), rotators.user_id, rotator_stats.rotator_id, rotator_stats.{$column}, SHA2(COALESCE(rotator_stats.{$column}, ''), 256)"),
        };

        return upsertAggregateRows(
            $query,
            'daily_link_breakdown_stats',
            ['source_type', 'source_id', 'stat_date', 'breakdown_type', 'label_hash'],
            ['user_id', 'tracker_id', 'rotator_id', 'label', 'total_hits', 'daily_unique_hits', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('rollupLinkRotatorTrackers')) {
    function rollupLinkRotatorTrackers($from, $to, string $now): int
    {
        $query = DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->whereBetween('rotator_stats.created_at', [$from, $to])
            ->whereNotNull('rotator_stats.rotator_id')
            ->whereNotNull('rotator_stats.tracker_id')
            ->selectRaw('DATE(rotator_stats.created_at) as stat_date')
            ->selectRaw('rotators.user_id as user_id')
            ->selectRaw('rotator_stats.rotator_id as rotator_id')
            ->selectRaw('rotator_stats.tracker_id as tracker_id')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT rotator_stats.ip_address) as daily_unique_hits')
            ->groupByRaw('DATE(rotator_stats.created_at), rotators.user_id, rotator_stats.rotator_id, rotator_stats.tracker_id');

        return upsertAggregateRows(
            $query,
            'daily_link_rotator_tracker_stats',
            ['rotator_id', 'tracker_id', 'stat_date'],
            ['user_id', 'total_hits', 'daily_unique_hits', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('upsertAggregateRows')) {
    function upsertAggregateRows($query, string $table, array $uniqueBy, array $updateColumns, string $now): int
    {
        $rows = $query->get();

        $payload = $rows
            ->map(fn($row) => [
                ...((array) $row),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($payload !== []) {
            DB::table($table)->upsert($payload, $uniqueBy, $updateColumns);
        }

        return count($payload);
    }
}

if (! function_exists('pruneRolledUpLinkStats')) {
    function pruneRolledUpLinkStats($from, $to): int
    {
        $deleteTo = $to->copy()->min(now()->startOfDay()->subSecond());

        if ($from->greaterThan($deleteTo)) {
            return 0;
        }

        $trackerRows = DB::table('tracker_stats')
            ->whereBetween('created_at', [$from, $deleteTo])
            ->delete();

        $rotatorRows = DB::table('rotator_stats')
            ->whereBetween('created_at', [$from, $deleteTo])
            ->delete();

        return $trackerRows + $rotatorRows;
    }
}

Artisan::command('banner-stats:rollup {--from=} {--to=} {--fresh} {--prune}', function () {
    if (
        ! Schema::hasTable('daily_banner_referrer_stats')
        || ! Schema::hasTable('daily_banner_breakdown_stats')
        || ! Schema::hasTable('daily_banner_rotator_banner_stats')
    ) {
        $this->error('Aggregate tables do not exist yet. Run the daily banner aggregate migration first.');

        return self::FAILURE;
    }

    $from = $this->option('from')
        ? now()->parse($this->option('from'))->startOfDay()
        : now()->subDays(59)->startOfDay();
    $to = $this->option('to')
        ? now()->parse($this->option('to'))->endOfDay()
        : now()->endOfDay();

    if ($from->greaterThan($to)) {
        $this->error('The --from date must be before or equal to the --to date.');

        return self::FAILURE;
    }

    if ($this->option('fresh')) {
        DB::table('daily_banner_referrer_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
        DB::table('daily_banner_breakdown_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
        DB::table('daily_banner_rotator_banner_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
    }

    $now = now()->toDateTimeString();
    $totalRows = 0;

    foreach (['banner', 'rotator'] as $sourceType) {
        $totalRows += rollupBannerReferrers($sourceType, $from, $to, $now);

        foreach (['device_type', 'operating_system', 'browser', 'country_code'] as $breakdownType) {
            $totalRows += rollupBannerBreakdowns($sourceType, $breakdownType, $from, $to, $now);
        }
    }

    $totalRows += rollupBannerRotatorBanners($from, $to, $now);

    $this->info("Rolled up {$totalRows} banner aggregate rows from {$from->toDateString()} to {$to->toDateString()}.");

    if ($this->option('prune')) {
        $deletedRows = pruneRolledUpBannerStats($from, $to);

        $this->info("Pruned {$deletedRows} raw banner stat rows before today.");
    }

    return self::SUCCESS;
})->purpose('Roll banner_stats into daily aggregate tables');

if (! function_exists('rollupBannerReferrers')) {
    function rollupBannerReferrers(string $sourceType, $from, $to, string $now): int
    {
        $query = match ($sourceType) {
            'banner' => DB::table('banner_stats')
                ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
                ->whereBetween('banner_stats.created_at', [$from, $to])
                ->selectRaw('DATE(banner_stats.created_at) as stat_date')
                ->selectRaw('banners.user_id as user_id')
                ->selectRaw('banner_stats.banner_id as banner_id')
                ->selectRaw('NULL as banner_rotator_id')
                ->selectRaw("'banner' as source_type")
                ->selectRaw('banner_stats.banner_id as source_id')
                ->selectRaw('banner_stats.ref_url as ref_url')
                ->selectRaw("SHA2(COALESCE(banner_stats.ref_url, ''), 256) as ref_url_hash")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'click' THEN 1 ELSE 0 END) as clicks")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'impression' THEN banner_stats.ip_address END) as daily_unique_impressions")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'click' THEN banner_stats.ip_address END) as daily_unique_clicks")
                ->groupByRaw("DATE(banner_stats.created_at), banners.user_id, banner_stats.banner_id, banner_stats.ref_url, SHA2(COALESCE(banner_stats.ref_url, ''), 256)"),
            'rotator' => DB::table('banner_stats')
                ->join('banner_rotators', 'banner_rotators.id', '=', 'banner_stats.banner_rotator_id')
                ->whereBetween('banner_stats.created_at', [$from, $to])
                ->whereNotNull('banner_stats.banner_rotator_id')
                ->selectRaw('DATE(banner_stats.created_at) as stat_date')
                ->selectRaw('banner_rotators.user_id as user_id')
                ->selectRaw('NULL as banner_id')
                ->selectRaw('banner_stats.banner_rotator_id as banner_rotator_id')
                ->selectRaw("'rotator' as source_type")
                ->selectRaw('banner_stats.banner_rotator_id as source_id')
                ->selectRaw('banner_stats.ref_url as ref_url')
                ->selectRaw("SHA2(COALESCE(banner_stats.ref_url, ''), 256) as ref_url_hash")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'click' THEN 1 ELSE 0 END) as clicks")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'impression' THEN banner_stats.ip_address END) as daily_unique_impressions")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'click' THEN banner_stats.ip_address END) as daily_unique_clicks")
                ->groupByRaw("DATE(banner_stats.created_at), banner_rotators.user_id, banner_stats.banner_rotator_id, banner_stats.ref_url, SHA2(COALESCE(banner_stats.ref_url, ''), 256)"),
        };

        return upsertAggregateRows(
            $query,
            'daily_banner_referrer_stats',
            ['source_type', 'source_id', 'stat_date', 'ref_url_hash'],
            ['user_id', 'banner_id', 'banner_rotator_id', 'ref_url', 'impressions', 'clicks', 'daily_unique_impressions', 'daily_unique_clicks', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('rollupBannerBreakdowns')) {
    function rollupBannerBreakdowns(string $sourceType, string $breakdownType, $from, $to, string $now): int
    {
        $column = match ($breakdownType) {
            'device_type', 'operating_system', 'browser', 'country_code' => $breakdownType,
            default => throw new InvalidArgumentException('Invalid breakdown type.'),
        };

        $query = match ($sourceType) {
            'banner' => DB::table('banner_stats')
                ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
                ->whereBetween('banner_stats.created_at', [$from, $to])
                ->selectRaw('DATE(banner_stats.created_at) as stat_date')
                ->selectRaw('banners.user_id as user_id')
                ->selectRaw('banner_stats.banner_id as banner_id')
                ->selectRaw('NULL as banner_rotator_id')
                ->selectRaw("'banner' as source_type")
                ->selectRaw('banner_stats.banner_id as source_id')
                ->selectRaw('? as breakdown_type', [$breakdownType])
                ->selectRaw("banner_stats.{$column} as label")
                ->selectRaw("SHA2(COALESCE(banner_stats.{$column}, ''), 256) as label_hash")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'click' THEN 1 ELSE 0 END) as clicks")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'impression' THEN banner_stats.ip_address END) as daily_unique_impressions")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'click' THEN banner_stats.ip_address END) as daily_unique_clicks")
                ->groupByRaw("DATE(banner_stats.created_at), banners.user_id, banner_stats.banner_id, banner_stats.{$column}, SHA2(COALESCE(banner_stats.{$column}, ''), 256)"),
            'rotator' => DB::table('banner_stats')
                ->join('banner_rotators', 'banner_rotators.id', '=', 'banner_stats.banner_rotator_id')
                ->whereBetween('banner_stats.created_at', [$from, $to])
                ->whereNotNull('banner_stats.banner_rotator_id')
                ->selectRaw('DATE(banner_stats.created_at) as stat_date')
                ->selectRaw('banner_rotators.user_id as user_id')
                ->selectRaw('NULL as banner_id')
                ->selectRaw('banner_stats.banner_rotator_id as banner_rotator_id')
                ->selectRaw("'rotator' as source_type")
                ->selectRaw('banner_stats.banner_rotator_id as source_id')
                ->selectRaw('? as breakdown_type', [$breakdownType])
                ->selectRaw("banner_stats.{$column} as label")
                ->selectRaw("SHA2(COALESCE(banner_stats.{$column}, ''), 256) as label_hash")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
                ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'click' THEN 1 ELSE 0 END) as clicks")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'impression' THEN banner_stats.ip_address END) as daily_unique_impressions")
                ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'click' THEN banner_stats.ip_address END) as daily_unique_clicks")
                ->groupByRaw("DATE(banner_stats.created_at), banner_rotators.user_id, banner_stats.banner_rotator_id, banner_stats.{$column}, SHA2(COALESCE(banner_stats.{$column}, ''), 256)"),
        };

        return upsertAggregateRows(
            $query,
            'daily_banner_breakdown_stats',
            ['source_type', 'source_id', 'stat_date', 'breakdown_type', 'label_hash'],
            ['user_id', 'banner_id', 'banner_rotator_id', 'label', 'impressions', 'clicks', 'daily_unique_impressions', 'daily_unique_clicks', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('rollupBannerRotatorBanners')) {
    function rollupBannerRotatorBanners($from, $to, string $now): int
    {
        $query = DB::table('banner_stats')
            ->join('banner_rotators', 'banner_rotators.id', '=', 'banner_stats.banner_rotator_id')
            ->whereBetween('banner_stats.created_at', [$from, $to])
            ->whereNotNull('banner_stats.banner_rotator_id')
            ->selectRaw('DATE(banner_stats.created_at) as stat_date')
            ->selectRaw('banner_rotators.user_id as user_id')
            ->selectRaw('banner_stats.banner_rotator_id as banner_rotator_id')
            ->selectRaw('banner_stats.banner_id as banner_id')
            ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN banner_stats.event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'impression' THEN banner_stats.ip_address END) as daily_unique_impressions")
            ->selectRaw("COUNT(DISTINCT CASE WHEN banner_stats.event_type = 'click' THEN banner_stats.ip_address END) as daily_unique_clicks")
            ->groupByRaw('DATE(banner_stats.created_at), banner_rotators.user_id, banner_stats.banner_rotator_id, banner_stats.banner_id');

        return upsertAggregateRows(
            $query,
            'daily_banner_rotator_banner_stats',
            ['banner_rotator_id', 'banner_id', 'stat_date'],
            ['user_id', 'impressions', 'clicks', 'daily_unique_impressions', 'daily_unique_clicks', 'updated_at'],
            $now,
        );
    }
}

if (! function_exists('pruneRolledUpBannerStats')) {
    function pruneRolledUpBannerStats($from, $to): int
    {
        $deleteTo = $to->copy()->min(now()->startOfDay()->subSecond());

        if ($from->greaterThan($deleteTo)) {
            return 0;
        }

        return DB::table('banner_stats')
            ->whereBetween('created_at', [$from, $deleteTo])
            ->delete();
    }
}

Schedule::command('link-stats:rollup --from=yesterday --to=yesterday --fresh --prune')
    ->dailyAt('00:00');

Schedule::command('banner-stats:rollup --from=yesterday --to=yesterday --fresh --prune')
    ->dailyAt('00:05');
