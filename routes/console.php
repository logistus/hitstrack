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
    if (! Schema::hasTable('daily_link_referrer_stats') || ! Schema::hasTable('daily_link_breakdown_stats')) {
        $this->error('Aggregate tables do not exist yet. Run the daily link aggregate migration first.');

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
        DB::table('daily_link_referrer_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
        DB::table('daily_link_breakdown_stats')
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

    $this->info("Rolled up {$totalRows} aggregate rows from {$from->toDateString()} to {$to->toDateString()}.");

    if ($this->option('prune')) {
        $deletedRows = pruneRolledUpLinkStats($from, $to);

        $this->info("Pruned {$deletedRows} raw stat rows before today.");
    }

    return self::SUCCESS;
})->purpose('Roll tracker_stats and rotator_stats into daily aggregate tables');

function rollupLinkReferrers(string $sourceType, $from, $to, string $now): int
{
    $query = match ($sourceType) {
        'tracker' => DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->whereBetween('tracker_stats.created_at', [$from, $to])
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
            ->groupByRaw("DATE(tracker_stats.created_at), trackers.user_id, tracker_stats.tracker_id, tracker_stats.ref_url, SHA2(COALESCE(tracker_stats.ref_url, ''), 256)"),
        'rotator' => DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->whereBetween('rotator_stats.created_at', [$from, $to])
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
            ->groupByRaw("DATE(rotator_stats.created_at), rotators.user_id, rotator_stats.rotator_id, rotator_stats.ref_url, SHA2(COALESCE(rotator_stats.ref_url, ''), 256)"),
    };

    return upsertAggregateRows(
        $query,
        'daily_link_referrer_stats',
        ['source_type', 'source_id', 'stat_date', 'ref_url_hash'],
        ['user_id', 'tracker_id', 'rotator_id', 'ref_url', 'total_hits', 'daily_unique_hits', 'updated_at'],
        $now,
    );
}

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

function upsertAggregateRows($query, string $table, array $uniqueBy, array $updateColumns, string $now): int
{
    $count = 0;

    $query
        ->orderBy('stat_date')
        ->chunk(1000, function ($rows) use ($table, $uniqueBy, $updateColumns, $now, &$count): void {
            $payload = $rows
                ->map(fn($row) => [
                    ...((array) $row),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table($table)->upsert($payload, $uniqueBy, $updateColumns);

            $count += count($payload);
        });

    return $count;
}

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

Schedule::command('link-stats:rollup --from=yesterday --to=yesterday --fresh --prune')
    ->dailyAt('00:00');
