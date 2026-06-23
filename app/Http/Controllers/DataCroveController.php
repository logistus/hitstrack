<?php

namespace App\Http\Controllers;

use App\Models\PixelStat;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DataCroveController extends Controller
{
    public function __invoke(Request $request): View
    {
        $baseQuery = PixelStat::query();

        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $hitsByDay = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('hit_date');

        $dailyStats = collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'total' => (int) ($hitsByDay[$date->toDateString()]?->total_hits ?? 0),
                'unique' => (int) ($hitsByDay[$date->toDateString()]?->unique_hits ?? 0),
            ]);
        $pixelUrl = url('pixel');

        return view('datacrove', [
            'activeTab' => match (true) {
                $request->has('allHitsPage') => 'all-hits',
                $request->has('dailyHitsPage') => 'hits',
                $request->has('referrerPage') => 'referrers',
                default => 'overview',
            },
            'pixelUrlExample' => $pixelUrl,
            'pixelSnippet' => <<<HTML
<script>
(function () {
    var img = new Image(1, 1);
    img.src = '{$pixelUrl}?page_url=' + encodeURIComponent(window.location.href) + '&ref_url=' + encodeURIComponent(document.referrer || '');
})();
</script>
<noscript><img src="{$pixelUrl}" width="1" height="1" style="display:none" alt=""></noscript>
HTML,
            'summaryStats' => [
                'total_hits' => (clone $baseQuery)->count(),
                'unique_hits' => (clone $baseQuery)->distinct('ip_address')->count('ip_address'),
                'today_hits' => (clone $baseQuery)->whereDate('created_at', today())->count(),
                'last_hit_at' => (clone $baseQuery)->latest('created_at')->value('created_at'),
            ],
            'chartData' => [
                'labels' => $dailyStats->pluck('label')->all(),
                'dates' => $dailyStats->pluck('date')->all(),
                'totals' => $dailyStats->pluck('total')->all(),
                'uniques' => $dailyStats->pluck('unique')->all(),
            ],
            'maxHits' => max(1, $dailyStats->max('total')),
            'breakdownStats' => [
                'device_types' => $this->groupedStatCounts(clone $baseQuery, 'device_type'),
                'operating_systems' => $this->groupedStatCounts(clone $baseQuery, 'operating_system'),
                'browsers' => $this->groupedStatCounts(clone $baseQuery, 'browser'),
            ],
            'dailyHitRecords' => (clone $baseQuery)
                ->selectRaw('DATE(created_at) as hit_date')
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
                ->groupByRaw('DATE(created_at)')
                ->orderByDesc('hit_date')
                ->simplePaginate(25, pageName: 'dailyHitsPage')
                ->withQueryString(),
            'allHitRecords' => (clone $baseQuery)
                ->latest('created_at')
                ->simplePaginate(25, pageName: 'allHitsPage')
                ->withQueryString(),
            'referrerStats' => (clone $baseQuery)
                ->selectRaw("COALESCE(ref_url, '') as ref_url")
                ->selectRaw('COUNT(*) as total_hits')
                ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
                ->groupByRaw("COALESCE(ref_url, '')")
                ->orderByDesc('total_hits')
                ->simplePaginate(25, pageName: 'referrerPage')
                ->withQueryString(),
        ]);
    }

    private function groupedStatCounts($query, string $field)
    {
        $field = match ($field) {
            'device_type', 'operating_system', 'browser' => $field,
            default => throw new \InvalidArgumentException('Invalid field'),
        };

        return $query
            ->selectRaw("COALESCE({$field}, ?) as label, COUNT(*) as total", [__('Unknown')])
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }
}
