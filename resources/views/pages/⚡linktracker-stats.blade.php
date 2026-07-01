<?php

use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Flux\Flux;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Link tracker stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $trackerId = 0;

    public string $sortField = 'total_hits';

    public string $sortDirection = 'desc';

    public string $referrerSearch = '';

    public string $activeTab = 'overview';

    public string $selectedDeviceType = '';

    public array $deviceReferrers = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->trackerId = LinkTracker::query()
            ->where('user_id', Auth::id())
            ->where('tracker_slug', $slug)
            ->firstOrFail()
            ->id;
    }

    public function refreshStats(): void
    {
        $this->dispatch('tracker-chart-updated', chartData: $this->freshChartData());
    }

    public function updatedReferrerSearch(): void
    {
        $this->activeTab = 'referrers';
        $this->resetPage('referrerPage');
    }

    public function showTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'hits', 'referrers'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['ref_url', 'total_hits', 'unique_hits'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            $this->resetPage('referrerPage');

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'desc';
        $this->resetPage('referrerPage');
    }

    public function with(): array
    {
        $tracker = $this->tracker();
        $dailyHits = AnalyticsCache::remember(
            'link-tracker',
            $tracker->id,
            'daily-hits',
            fn (): array => $this->dailyHits($tracker),
        );

        return [
            'tracker' => $tracker,
            'summaryStats' => AnalyticsCache::remember(
                'link-tracker',
                $tracker->id,
                'summary',
                fn (): array => $this->summaryStats($tracker),
            ),
            'breakdownStats' => AnalyticsCache::remember(
                'link-tracker',
                $tracker->id,
                'breakdowns',
                fn (): array => $this->breakdownStats($tracker),
            ),
            'chartData' => $dailyHits['chartData'],
            'maxHits' => $dailyHits['maxHits'],
            'dailyHitRecords' => $this->activeTab === 'hits'
                ? $this->dailyHitRecords($tracker)
                : $this->emptyPaginator('dailyHitsPage'),
            'referrerStats' => $this->activeTab === 'referrers'
                ? $this->referrerStats($tracker)
                : $this->emptyPaginator('referrerPage'),
        ];
    }

    public function freshChartData(): array
    {
        AnalyticsCache::forget('link-tracker', $this->trackerId, 'daily-hits');

        return $this->dailyHits($this->tracker())['chartData'];
    }

    public function referrerHref(?string $refUrl): ?string
    {
        if (! $refUrl) {
            return null;
        }

        if (str_starts_with($refUrl, 'http://') || str_starts_with($refUrl, 'https://')) {
            return $refUrl;
        }

        return 'https://'.$refUrl;
    }

    public function showDeviceReferrers(string $deviceType): void
    {
        $this->selectedDeviceType = $deviceType;
        $this->deviceReferrers = $this->topDeviceReferrers($this->tracker(), $deviceType);

        Flux::modal('device-referrers')->show();
    }

    private function tracker(): LinkTracker
    {
        return LinkTracker::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->trackerId)
            ->firstOrFail();
    }

    private function dailyHits(LinkTracker $tracker): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $hitsByDay = $this->dailyHitsQuery($tracker)
            ->whereBetween('hit_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy('hit_date');

        $days = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'total' => (int) ($hitsByDay[$date->toDateString()]?->total_hits ?? 0),
                'unique' => (int) ($hitsByDay[$date->toDateString()]?->unique_hits ?? 0),
            ])
            ->values();

        return [
            'chartData' => [
                'labels' => $days->pluck('label')->all(),
                'dates' => $days->pluck('date')->all(),
                'totals' => $days->pluck('total')->all(),
                'uniques' => $days->pluck('unique')->all(),
            ],
            'maxHits' => max(1, $days->max('total')),
        ];
    }

    private function summaryStats(LinkTracker $tracker): array
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->where('source_type', 'tracker')
            ->where('source_id', $tracker->id)
            ->where('stat_date', '<', today())
            ->selectRaw('COALESCE(SUM(total_hits), 0) as total_hits')
            ->selectRaw('COALESCE(SUM(daily_unique_hits), 0) as unique_hits')
            ->first();

        return [
            'total_hits' => (int) $aggregate->total_hits + LinkTrackerStat::query()
                ->where('tracker_id', $tracker->id)
                ->whereNull('rotator_id')
                ->where('created_at', '>=', today())
                ->count(),
            'unique_hits' => (int) $aggregate->unique_hits + LinkTrackerStat::query()
                ->where('tracker_id', $tracker->id)
                ->whereNull('rotator_id')
                ->where('created_at', '>=', today())
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    private function breakdownStats(LinkTracker $tracker): array
    {
        return [
            'device_types' => $this->groupedStatCounts($tracker, 'device_type'),
            'operating_systems' => $this->groupedStatCounts($tracker, 'operating_system'),
            'browsers' => $this->groupedStatCounts($tracker, 'browser'),
            'countries' => $this->groupedStatCounts($tracker, 'country_code'),
        ];
    }

    private function groupedStatCounts(LinkTracker $tracker, string $field)
    {
        $field = match ($field) {
            'device_type', 'operating_system', 'browser', 'country_code' => $field,
            default => throw new InvalidArgumentException('Invalid field'),
        };

        $aggregate = DB::table('daily_link_breakdown_stats')
            ->select('label')
            ->selectRaw('SUM(total_hits) as total')
            ->where('source_type', 'tracker')
            ->where('source_id', $tracker->id)
            ->where('breakdown_type', $field)
            ->where('stat_date', '<', today())
            ->groupBy('label');

        $today = DB::table('tracker_stats')
            ->select("{$field} as label")
            ->selectRaw('COUNT(*) as total')
            ->where('tracker_id', $tracker->id)
            ->whereNull('rotator_id')
            ->where('created_at', '>=', today())
            ->groupBy($field);

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'breakdown_stats')
            ->selectRaw("COALESCE(label, ?) as label", [__('Unknown')])
            ->selectRaw('SUM(total) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($stat): array => [
                'label' => $stat->label,
                'total' => (int) $stat->total,
            ])
            ->all();
    }

    private function referrerStats(LinkTracker $tracker)
    {
        $search = trim($this->referrerSearch);

        return $this->referrerStatsQuery($tracker)
            ->when($search !== '', fn ($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn ($query) => $query->orderBy('ref_url'))
            ->simplePaginate(25, pageName: 'referrerPage');
    }

    private function topDeviceReferrers(LinkTracker $tracker, string $deviceType): array
    {
        return DB::table('tracker_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->where('tracker_id', $tracker->id)
            ->whereNull('rotator_id')
            ->where('device_type', $deviceType)
            ->where('created_at', '>=', today())
            ->groupByRaw("COALESCE(ref_url, '')")
            ->orderByDesc('total_hits')
            ->limit(5)
            ->get()
            ->map(fn ($stat): array => [
                'ref_url' => $stat->ref_url,
                'total' => (int) $stat->total_hits,
            ])
            ->all();
    }

    private function dailyHitRecords(LinkTracker $tracker)
    {
        return $this->dailyHitsQuery($tracker)
            ->orderByDesc('hit_date')
            ->simplePaginate(25, pageName: 'dailyHitsPage');
    }

    private function dailyHitsQuery(LinkTracker $tracker)
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->selectRaw('stat_date as hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('source_type', 'tracker')
            ->where('source_id', $tracker->id)
            ->where('stat_date', '<', today())
            ->groupBy('stat_date');

        $today = DB::table('tracker_stats')
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('tracker_id', $tracker->id)
            ->whereNull('rotator_id')
            ->where('created_at', '>=', today())
            ->groupByRaw('DATE(created_at)');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'daily_hits')
            ->selectRaw('hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(unique_hits) as unique_hits')
            ->groupBy('hit_date');
    }

    private function referrerStatsQuery(LinkTracker $tracker)
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('source_type', 'tracker')
            ->where('source_id', $tracker->id)
            ->where('stat_date', '<', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        $today = DB::table('tracker_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('tracker_id', $tracker->id)
            ->whereNull('rotator_id')
            ->where('created_at', '>=', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'referrer_stats')
            ->selectRaw('ref_url')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(unique_hits) as unique_hits')
            ->groupBy('ref_url');
    }

    private function emptyPaginator(string $pageName): Paginator
    {
        return new Paginator([], 25, 1, ['pageName' => $pageName]);
    }
};
?>

<section
    class="container mx-auto space-y-8"
    x-data="{ activeTab: $wire.entangle('activeTab') }"
    data-tracker-stats-root>
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Link tracker stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Link tracker stats') }}</flux:heading>
            <flux:subheading>{{ route('linktrackers.redirect', $tracker->tracker_slug) }}</flux:subheading>
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Target URL') }}:</span>
                <flux:link
                    href="{{ $tracker->target_url }}"
                    target="_blank"
                    rel="noreferrer"
                    class="block min-w-0 max-w-xl truncate">
                    {{ $tracker->target_url }}
                </flux:link>
            </div>
        </div>

        <flux:button variant="filled" :href="route('linktrackers')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Total Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['total_hits']) }}</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Unique Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['unique_hits']) }}</flux:heading>
            </div>
        </flux:card>
    </div>

    <div class="space-y-6">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'overview' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('overview')"
                @click="activeTab = 'overview'; $nextTick(() => document.dispatchEvent(new CustomEvent('tracker-chart-resize')))">
                {{ __('Overview') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'hits' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('hits')"
                @click="activeTab = 'hits'">
                {{ __('Daily hits') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('referrers')"
                @click="activeTab = 'referrers'">
                {{ __('Referrers') }}
            </button>
        </div>

        <section class="space-y-8" x-show="activeTab === 'overview'">
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading>{{ __('Daily hits') }}</flux:heading>
                        <flux:subheading>{{ __('Last 30 days') }}</flux:subheading>
                    </div>
                    <flux:text>{{ __('Peak: :count', ['count' => $maxHits]) }}</flux:text>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:ignore>
                    <div class="h-80">
                        <canvas
                            data-tracker-chart
                            data-chart='@json($chartData)'
                            data-total-hits-label="{{ __('Total hits') }}"
                            data-unique-hits-label="{{ __('Unique hits') }}"></canvas>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                __('Device Type') => $breakdownStats['device_types'],
                __('Operating System') => $breakdownStats['operating_systems'],
                __('Browser') => $breakdownStats['browsers'],
                __('Country') => $breakdownStats['countries'],
                ] as $label => $stats)
                <flux:card>
                    <div class="space-y-3">
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</div>
                        <div class="space-y-3">
                            @forelse ($stats as $stat)
                            @php($percent = $summaryStats['total_hits'] > 0 ? min(100, round(($stat['total'] / $summaryStats['total_hits']) * 100)) : 0)
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    @if ($label === __('Device Type'))
                                        <button
                                            type="button"
                                            class="min-w-0 truncate text-left text-zinc-600 underline-offset-2 hover:text-zinc-950 hover:underline dark:text-zinc-400 dark:hover:text-white"
                                            wire:click="showDeviceReferrers('{{ addslashes($stat['label']) }}')">
                                            {{ str($stat['label'])->title() }}
                                        </button>
                                    @else
                                        <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $stat['label'] }}</span>
                                    @endif
                                    <span class="font-medium">{{ number_format($stat['total']) }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-blue-600" @style(["width: {$percent}%"])></div>
                                </div>
                            </div>
                            @empty
                            <flux:text>{{ __('No data') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                </flux:card>
                @endforeach
            </section>

            <flux:modal name="device-referrers" class="max-w-xl md:min-w-xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Top Referrers') }}</flux:heading>
                        <flux:text>{{ __('Device type: :device', ['device' => str($selectedDeviceType)->title()]) }}</flux:text>
                    </div>

                    <div class="space-y-3">
                        @forelse ($deviceReferrers as $referrer)
                            <div class="flex items-center justify-between gap-4 rounded-md border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                <div class="min-w-0">
                                    @if ($href = $this->referrerHref($referrer['ref_url']))
                                        <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block truncate">
                                            {{ $referrer['ref_url'] }}
                                        </flux:link>
                                    @else
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Direct / unknown') }}</span>
                                    @endif
                                </div>
                                <span class="shrink-0 font-medium">{{ number_format($referrer['total']) }}</span>
                            </div>
                        @empty
                            <flux:text>{{ __('No referrer data yet.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            </flux:modal>
        </section>

        <section class="space-y-4" x-show="activeTab === 'hits'">
            <div>
                <flux:heading>{{ __('Daily hits') }}</flux:heading>
                <flux:subheading>{{ __('All hits grouped by day') }}</flux:subheading>
            </div>

            <flux:table :paginate="$dailyHitRecords">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Total hits') }}</flux:table.column>
                    <flux:table.column>{{ __('Unique hits') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($dailyHitRecords as $stat)
                    <flux:table.row wire:key="tracker-daily-hit-{{ $stat->hit_date }}">
                        <flux:table.cell>{{ \Carbon\Carbon::parse($stat->hit_date)->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->total_hits) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->unique_hits) }}</flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" align="center">
                            {{ __('No hits yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <div x-show="activeTab === 'referrers'" class="space-y-8">
            <section class="space-y-4">
                <div>
                    <flux:heading>{{ __('Referrers') }}</flux:heading>
                    <flux:subheading>{{ __('All hits grouped by referrer') }}</flux:subheading>
                </div>

                <flux:input
                    wire:model.live.debounce.300ms="referrerSearch"
                    :label="__('Filter by URL')"
                    type="search"
                    autocomplete="off"
                    placeholder="example.com"
                    class="max-w-md" />

                <flux:table :paginate="$referrerStats">
                    <flux:table.columns>
                        <flux:table.column
                            sortable
                            :sorted="$sortField === 'ref_url'"
                            :direction="$sortDirection"
                            wire:click="sortBy('ref_url')"
                            class="cursor-pointer">
                            {{ __('Ref URL') }}
                        </flux:table.column>
                        <flux:table.column
                            sortable
                            :sorted="$sortField === 'total_hits'"
                            :direction="$sortDirection"
                            wire:click="sortBy('total_hits')"
                            class="cursor-pointer">
                            {{ __('Total hits') }}
                        </flux:table.column>
                        <flux:table.column
                            sortable
                            :sorted="$sortField === 'unique_hits'"
                            :direction="$sortDirection"
                            wire:click="sortBy('unique_hits')"
                            class="cursor-pointer">
                            {{ __('Unique hits') }}
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($referrerStats as $stat)
                        <flux:table.row wire:key="tracker-referrer-{{ md5($stat->ref_url ?: 'direct') }}">
                            <flux:table.cell>
                                @if ($href = $this->referrerHref($stat->ref_url))
                                <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block max-w-md truncate">
                                    {{ $stat->ref_url }}
                                </flux:link>
                                @else
                                {{ __('Direct / unknown') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ number_format($stat->total_hits) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($stat->unique_hits) }}</flux:table.cell>
                        </flux:table.row>
                        @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" align="center">
                                {{ __('No hits yet.') }}
                            </flux:table.cell>
                        </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </section>
        </div>
    </div>
</section>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endassets

@script
<script>
    (() => {
        const boot = () => {
            if (!window.Chart) {
                window.setTimeout(boot, 50);
                return;
            }

            const canvas = $wire.$el.querySelector('[data-tracker-chart]');

            if (!canvas) {
                return;
            }

            let chartData = JSON.parse(canvas.dataset.chart);
            const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600').trim() || '#2563eb';
            const secondary = getComputedStyle(document.documentElement).getPropertyValue('--color-emerald-600').trim() || '#059669';
            const grid = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255,255,255,.10)' : 'rgba(39,39,42,.10)';
            const text = window.matchMedia('(prefers-color-scheme: dark)').matches ? '#d4d4d8' : '#52525b';

            if (canvas._trackerChart) {
                canvas._trackerChart.destroy();
            }

            const applyChartData = (freshChartData) => {
                chartData = freshChartData;
                canvas.dataset.chart = JSON.stringify(freshChartData);
                canvas._trackerChart.data.labels = chartData.labels;
                canvas._trackerChart.data.datasets[0].data = chartData.totals;
                canvas._trackerChart.data.datasets[1].data = chartData.uniques;
                canvas._trackerChart.update();
            };

            canvas._trackerChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                            label: canvas.dataset.totalHitsLabel,
                            data: chartData.totals,
                            borderColor: accent,
                            backgroundColor: 'rgba(37, 99, 235, .12)',
                            fill: true,
                            tension: .35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: accent,
                            pointBorderWidth: 2,
                        },
                        {
                            label: canvas.dataset.uniqueHitsLabel,
                            data: chartData.uniques,
                            borderColor: secondary,
                            backgroundColor: 'rgba(5, 150, 105, .10)',
                            fill: false,
                            tension: .35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: secondary,
                            pointBorderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: text,
                                usePointStyle: true,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => chartData.dates[items[0].dataIndex],
                                label: (item) => `${item.dataset.label}: ${item.formattedValue}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: text,
                                maxRotation: 0,
                                autoSkipPadding: 24,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: grid,
                            },
                            ticks: {
                                color: text,
                                precision: 0,
                            },
                        },
                    },
                },
            });

            if (canvas._trackerChartUpdateListener) {
                document.removeEventListener('tracker-chart-updated', canvas._trackerChartUpdateListener);
            }

            canvas._trackerChartUpdateListener = (event) => {
                const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
                const freshChartData = detail?.chartData;

                if (!freshChartData) {
                    return;
                }

                applyChartData(freshChartData);
            };

            document.addEventListener('tracker-chart-updated', canvas._trackerChartUpdateListener);

            if (canvas._trackerChartResizeListener) {
                document.removeEventListener('tracker-chart-resize', canvas._trackerChartResizeListener);
            }

            canvas._trackerChartResizeListener = () => {
                canvas._trackerChart.resize();
            };

            document.addEventListener('tracker-chart-resize', canvas._trackerChartResizeListener);
        };

        boot();
    })();
</script>
@endscript
