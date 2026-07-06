<?php

use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Link rotator stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $rotatorId = 0;

    public string $sortField = 'total_hits';

    public string $sortDirection = 'desc';

    public string $referrerSearch = '';

    public string $activeTab = 'overview';

    public string $calendarMonth = '';

    public string $dailyHitSource = 'all';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->rotatorId = LinkRotator::query()
            ->where('user_id', Auth::id())
            ->where('rotator_slug', $slug)
            ->firstOrFail()
            ->id;
        $this->calendarMonth = now()->format('Y-m');
    }

    public function refreshStats(): void
    {
        $this->dispatch('rotator-chart-updated', chartData: $this->freshChartData());
    }

    public function updatedReferrerSearch(): void
    {
        $this->activeTab = 'referrers';
        $this->resetPage('referrerPage');
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'hits', 'trackers', 'referrers'], true)) {
            $this->activeTab = $tab;

            if ($tab === 'overview') {
                $this->dispatch('rotator-chart-resize');
            }
        }
    }

    public function previousCalendarMonth(): void
    {
        $this->calendarMonth = Carbon::createFromFormat('Y-m', $this->calendarMonth)
            ->subMonthNoOverflow()
            ->format('Y-m');
    }

    public function nextCalendarMonth(): void
    {
        $this->calendarMonth = Carbon::createFromFormat('Y-m', $this->calendarMonth)
            ->addMonthNoOverflow()
            ->format('Y-m');
    }

    public function viewDailyHits(): void
    {
        $this->activeTab = 'hits';
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
        $rotator = $this->rotator();
        $dailyHits = AnalyticsCache::remember(
            'link-rotator',
            $rotator->id,
            'daily-hits',
            fn (): array => $this->dailyHits($rotator),
        );

        return [
            'rotator' => $rotator,
            'summaryStats' => AnalyticsCache::remember(
                'link-rotator',
                $rotator->id,
                'summary',
                fn (): array => $this->summaryStats($rotator),
            ),
            'breakdownStats' => AnalyticsCache::remember(
                'link-rotator',
                $rotator->id,
                'breakdowns',
                fn (): array => $this->breakdownStats($rotator),
            ),
            'chartData' => $dailyHits['chartData'],
            'maxHits' => $dailyHits['maxHits'],
            'dailyHitCalendar' => $this->activeTab === 'hits'
                ? $this->dailyHitCalendar($rotator)
                : [],
            'dailyHitSourceOptions' => $this->activeTab === 'hits'
                ? $this->dailyHitSourceOptions($rotator)
                : [['value' => 'all', 'label' => __('All Sources')]],
            'referrerStats' => $this->activeTab === 'referrers'
                ? $this->referrerStats($rotator)
                : $this->emptyPaginator('referrerPage'),
            'trackerPerformanceStats' => $this->activeTab === 'trackers'
                ? $this->trackerPerformanceStats($rotator)
                : collect(),
        ];
    }

    public function freshChartData(): array
    {
        AnalyticsCache::forget('link-rotator', $this->rotatorId, 'daily-hits');

        return $this->dailyHits($this->rotator())['chartData'];
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

    private function rotator(): LinkRotator
    {
        return LinkRotator::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->rotatorId)
            ->firstOrFail();
    }

    private function dailyHits(LinkRotator $rotator): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $hitsByDay = $this->dailyHitsQuery($rotator)
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

    private function summaryStats(LinkRotator $rotator): array
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->selectRaw('COALESCE(SUM(total_hits), 0) as total_hits')
            ->selectRaw('COALESCE(SUM(daily_unique_hits), 0) as unique_hits')
            ->first();

        return [
            'total_hits' => (int) $aggregate->total_hits + LinkRotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->where('created_at', '>=', today())
                ->count(),
            'unique_hits' => (int) $aggregate->unique_hits + LinkRotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->where('created_at', '>=', today())
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    private function referrerStats(LinkRotator $rotator)
    {
        $search = trim($this->referrerSearch);

        return $this->referrerStatsQuery($rotator)
            ->when($search !== '', fn ($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn ($query) => $query->orderBy('ref_url'))
            ->simplePaginate(25, pageName: 'referrerPage');
    }

    private function breakdownStats(LinkRotator $rotator): array
    {
        return [
            'device_types' => $this->groupedStatCounts($rotator, 'device_type'),
            'operating_systems' => $this->groupedStatCounts($rotator, 'operating_system'),
            'browsers' => $this->groupedStatCounts($rotator, 'browser'),
        ];
    }

    private function groupedStatCounts(LinkRotator $rotator, string $field)
    {
        $field = match ($field) {
            'device_type', 'operating_system', 'browser' => $field,
            default => throw new InvalidArgumentException('Invalid field'),
        };

        $aggregate = DB::table('daily_link_breakdown_stats')
            ->select('label')
            ->selectRaw('SUM(total_hits) as total')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('breakdown_type', $field)
            ->where('stat_date', '<', today())
            ->groupBy('label');

        $today = DB::table('rotator_stats')
            ->select("{$field} as label")
            ->selectRaw('COUNT(*) as total')
            ->where('rotator_id', $rotator->id)
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

    private function trackerPerformanceStats(LinkRotator $rotator)
    {
        $aggregate = DB::table('daily_link_rotator_tracker_stats')
            ->select('tracker_id')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupBy('tracker_id');

        $today = DB::table('rotator_stats')
            ->select('tracker_id')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->where('created_at', '>=', today())
            ->groupBy('tracker_id');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'tracker_performance')
            ->join('trackers', 'trackers.id', '=', 'tracker_performance.tracker_id')
            ->select('trackers.id', 'trackers.target_url', 'trackers.tracker_slug')
            ->selectRaw('SUM(tracker_performance.total_hits) as total_hits')
            ->selectRaw('SUM(tracker_performance.unique_hits) as unique_hits')
            ->groupBy('trackers.id', 'trackers.target_url', 'trackers.tracker_slug')
            ->orderByDesc('total_hits')
            ->get();
    }

    private function dailyHitCalendar(LinkRotator $rotator): array
    {
        $month = Carbon::createFromFormat('Y-m', $this->calendarMonth)->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $referrer = $this->selectedDailyHitReferrer();

        $hitsByDay = ($referrer === null
            ? $this->dailyHitsQuery($rotator)
            : $this->dailyHitsByReferrerQuery($rotator, $referrer))
            ->whereBetween('hit_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy('hit_date');

        return [
            'title' => $month->format('F Y'),
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'weeks' => collect(CarbonPeriod::create($start, $end))
                ->map(fn (Carbon $date): array => [
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'isCurrentMonth' => $date->isSameMonth($month),
                    'total_hits' => (int) ($hitsByDay[$date->toDateString()]?->total_hits ?? 0),
                    'unique_hits' => (int) ($hitsByDay[$date->toDateString()]?->unique_hits ?? 0),
                ])
                ->chunk(7)
                ->map(fn ($week) => $week->values()->all())
                ->values()
                ->all(),
        ];
    }

    private function dailyHitSourceOptions(LinkRotator $rotator): array
    {
        return collect([['value' => 'all', 'label' => __('All Sources')]])
            ->merge(
                $this->referrerStatsQuery($rotator)
                    ->orderByDesc('total_hits')
                    ->orderBy('ref_url')
                    ->get()
                    ->map(fn ($stat): array => [
                        'value' => 'ref:'.base64_encode($stat->ref_url),
                        'label' => $stat->ref_url ?: __('Direct / unknown'),
                    ])
            )
            ->values()
            ->all();
    }

    private function selectedDailyHitReferrer(): ?string
    {
        if (! str_starts_with($this->dailyHitSource, 'ref:')) {
            return null;
        }

        return base64_decode(substr($this->dailyHitSource, 4), true) ?: '';
    }

    private function dailyHitsQuery(LinkRotator $rotator)
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->selectRaw('stat_date as hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupBy('stat_date');

        $today = DB::table('rotator_stats')
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->where('created_at', '>=', today())
            ->groupByRaw('DATE(created_at)');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'daily_hits')
            ->selectRaw('hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(unique_hits) as unique_hits')
            ->groupBy('hit_date');
    }

    private function dailyHitsByReferrerQuery(LinkRotator $rotator, string $referrer)
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->selectRaw('stat_date as hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('rotator_id', $rotator->id)
            ->whereRaw("COALESCE(ref_url, '') = ?", [$referrer])
            ->where('stat_date', '<', today())
            ->groupBy('stat_date');

        $today = DB::table('rotator_stats')
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->whereRaw("COALESCE(ref_url, '') = ?", [$referrer])
            ->where('created_at', '>=', today())
            ->groupByRaw('DATE(created_at)');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'daily_hits')
            ->selectRaw('hit_date')
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(unique_hits) as unique_hits')
            ->groupBy('hit_date');
    }

    private function referrerStatsQuery(LinkRotator $rotator)
    {
        $aggregate = DB::table('daily_link_referrer_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('SUM(total_hits) as total_hits')
            ->selectRaw('SUM(daily_unique_hits) as unique_hits')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        $today = DB::table('rotator_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
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
    x-data="{ activeTab: $wire.entangle('activeTab') }">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Link rotator stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Link rotator stats') }}</flux:heading>
            <flux:subheading>{{ route('linkrotators.redirect', $rotator->rotator_slug) }}</flux:subheading>
        </div>

        <flux:button variant="filled" :href="route('linkrotators')" wire:navigate>
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
                wire:click="showTab('overview')">
                {{ __('Overview') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'hits' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('hits')">
                {{ __('Daily hits') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'trackers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('trackers')">
                {{ __('Trackers') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                wire:click="showTab('referrers')">
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
                            data-rotator-chart
                            data-chart='@json($chartData)'
                            data-total-hits-label="{{ __('Total hits') }}"
                            data-unique-hits-label="{{ __('Unique hits') }}"></canvas>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                @foreach ([
                __('Device Type') => $breakdownStats['device_types'],
                __('Operating System') => $breakdownStats['operating_systems'],
                __('Browser') => $breakdownStats['browsers'],
                ] as $label => $stats)
                <flux:card>
                    <div class="space-y-3">
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</div>
                        <div class="space-y-3">
                            @forelse ($stats as $stat)
                            @php
                            $percent = $summaryStats['total_hits'] > 0 ? min(100, round(($stat['total'] / $summaryStats['total_hits']) * 100)) : 0;
                            @endphp
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $label === __('Device Type') ? str($stat['label'])->title() : $stat['label'] }}</span>
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
        </section>

        <section class="space-y-4" x-show="activeTab === 'trackers'">
                <div>
                    <flux:heading>{{ __('Tracker performance') }}</flux:heading>
                    <flux:subheading>{{ __('Hits grouped by attached tracker') }}</flux:subheading>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Tracker') }}</flux:table.column>
                        <flux:table.column>{{ __('Total hits') }}</flux:table.column>
                        <flux:table.column>{{ __('Unique hits') }}</flux:table.column>
                        <flux:table.column>{{ __('Share') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($trackerPerformanceStats as $stat)
                        @php
                        $share = $summaryStats['total_hits'] > 0 ? ($stat->total_hits / $summaryStats['total_hits']) * 100 : 0;
                        @endphp
                        <flux:table.row wire:key="link-rotator-tracker-performance-{{ $stat->id }}">
                            <flux:table.cell>
                                <div class="max-w-xl space-y-1">
                                    <flux:link
                                        href="{{ route('linktrackers.redirect', $stat->tracker_slug) }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="block truncate">
                                        {{ route('linktrackers.redirect', $stat->tracker_slug) }}
                                    </flux:link>
                                    <br />
                                    (<flux:link
                                        href="{{ $stat->target_url }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="block truncate text-zinc-500 dark:text-zinc-400">
                                        {{ $stat->target_url }}
                                    </flux:link>)
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ number_format($stat->total_hits) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($stat->unique_hits) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="space-y-1.5">
                                    <div class="text-sm">{{ number_format($share, 2) }}%</div>
                                    <div class="h-1.5 w-32 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-blue-600" @style(["width: " . min(100, round($share)) . "%"])></div>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                        @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" align="center">
                                {{ __('No tracker hits yet.') }}
                            </flux:table.cell>
                        </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
        </section>

        <section class="space-y-5" x-show="activeTab === 'hits'">
            <div class="mx-auto max-w-3xl space-y-5">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <select
                        wire:model="dailyHitSource"
                        class="h-9 min-w-40 rounded border border-zinc-300 bg-white px-2 text-sm text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                        @foreach ($dailyHitSourceOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <button
                        type="button"
                        wire:click="viewDailyHits"
                        class="h-9 rounded border border-zinc-400 bg-white px-3 text-sm font-medium text-zinc-950 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:hover:bg-zinc-800">
                        {{ __('View') }}
                    </button>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <button type="button" wire:click="previousCalendarMonth" class="text-blue-600 hover:underline dark:text-blue-400">
                        &lt;&lt; {{ __('Previous Month') }}
                    </button>
                    <button type="button" wire:click="nextCalendarMonth" class="text-blue-600 hover:underline dark:text-blue-400">
                        {{ __('Next Month') }}&gt;&gt;
                    </button>
                </div>

                <div class="text-center text-xl font-medium text-zinc-950 dark:text-white">{{ $dailyHitCalendar['title'] ?? '' }}</div>

                <div class="grid grid-cols-7 text-center text-sm font-bold text-zinc-950 dark:text-white">
                    @foreach (($dailyHitCalendar['weekdays'] ?? []) as $weekday)
                        <div>{{ __($weekday) }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 border-b border-r border-zinc-950 dark:border-zinc-200">
                    @foreach (($dailyHitCalendar['weeks'] ?? []) as $week)
                        @foreach ($week as $day)
                            @php($hasHits = $day['total_hits'] > 0 || $day['unique_hits'] > 0)
                            <div
                                wire:key="rotator-calendar-day-{{ $day['date'] }}"
                                @class([
                                    'min-h-20 border-l border-t border-zinc-950 p-1 text-xs font-bold dark:border-zinc-200 sm:min-h-24',
                                    'bg-blue-900 text-white' => $hasHits,
                                    'bg-zinc-300 text-zinc-950 dark:bg-zinc-700 dark:text-white' => ! $hasHits && $day['isCurrentMonth'],
                                    'bg-zinc-100 text-zinc-400 dark:bg-zinc-900 dark:text-zinc-500' => ! $hasHits && ! $day['isCurrentMonth'],
                                ])>
                                <div>{{ $day['day'] }}</div>
                                @if ($hasHits)
                                    <div class="mt-1 leading-tight">
                                        <div>{{ __('Hits') }}: {{ number_format($day['total_hits']) }}</div>
                                        <div>{{ __('Unq') }}: {{ number_format($day['unique_hits']) }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
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
                        <flux:table.row wire:key="rotator-referrer-{{ md5($stat->ref_url ?: 'direct') }}">
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

            const canvas = $wire.$el.querySelector('[data-rotator-chart]');

            if (!canvas) {
                return;
            }

            let chartData = JSON.parse(canvas.dataset.chart);
            const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600').trim() || '#2563eb';
            const secondary = getComputedStyle(document.documentElement).getPropertyValue('--color-emerald-600').trim() || '#059669';
            const grid = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255,255,255,.10)' : 'rgba(39,39,42,.10)';
            const text = window.matchMedia('(prefers-color-scheme: dark)').matches ? '#d4d4d8' : '#52525b';

            if (canvas._rotatorChart) {
                canvas._rotatorChart.destroy();
            }

            const applyChartData = (freshChartData) => {
                chartData = freshChartData;
                canvas.dataset.chart = JSON.stringify(freshChartData);
                canvas._rotatorChart.data.labels = chartData.labels;
                canvas._rotatorChart.data.datasets[0].data = chartData.totals;
                canvas._rotatorChart.data.datasets[1].data = chartData.uniques;
                canvas._rotatorChart.update();
            };

            canvas._rotatorChart = new Chart(canvas, {
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

            if (canvas._rotatorChartUpdateListener) {
                document.removeEventListener('rotator-chart-updated', canvas._rotatorChartUpdateListener);
            }

            canvas._rotatorChartUpdateListener = (event) => {
                const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
                const freshChartData = detail?.chartData;

                if (!freshChartData) {
                    return;
                }

                applyChartData(freshChartData);
            };

            document.addEventListener('rotator-chart-updated', canvas._rotatorChartUpdateListener);

            if (canvas._rotatorChartResizeListener) {
                document.removeEventListener('rotator-chart-resize', canvas._rotatorChartResizeListener);
            }

            canvas._rotatorChartResizeListener = () => {
                canvas._rotatorChart.resize();
            };

            document.addEventListener('rotator-chart-resize', canvas._rotatorChartResizeListener);
        };

        boot();
    })();
</script>
@endscript
