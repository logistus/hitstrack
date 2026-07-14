<?php

use App\Models\BannerRotator;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Banner rotator stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $rotatorId = 0;

    public string $sortField = 'total_events';

    public string $sortDirection = 'desc';

    public string $referrerSearch = '';

    public string $activeTab = 'overview';

    private ?bool $hasStatsColumn = null;

    private ?bool $hasSizeColumns = null;

    private BannerRotator $rotator;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->rotator = BannerRotator::query()
            ->where('user_id', Auth::id())
            ->where('rotator_slug', $slug)
            ->firstOrFail();
        $this->rotatorId = $this->rotator->id;
    }

    public function updatedReferrerSearch(): void
    {
        $this->activeTab = 'referrers';
        $this->resetPage('referrerPage');
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'events', 'banners', 'referrers'], true)) {
            $this->activeTab = $tab;

            if ($tab === 'overview') {
                $this->dispatch('tracker-chart-resize');
            }
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['ref_url', 'impressions', 'clicks', 'total_events'], true)) {
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
        $dailyEvents = AnalyticsCache::remember(
            'banner-rotator',
            $rotator->id,
            'daily-events',
            fn(): array => $this->dailyEvents($rotator),
        );

        return [
            'rotator' => $rotator,
            'summaryStats' => AnalyticsCache::remember(
                'banner-rotator',
                $rotator->id,
                'summary',
                fn(): array => $this->summaryStats($rotator),
            ),
            'chartData' => $dailyEvents['chartData'],
            'maxEvents' => $dailyEvents['maxEvents'],
            'dailyEventRecords' => $this->activeTab === 'events'
                ? $this->dailyEventRecords($rotator)
                : $this->emptyPaginator('dailyEventsPage'),
            'referrerStats' => $this->activeTab === 'referrers'
                ? $this->referrerStats($rotator)
                : $this->emptyPaginator('referrerPage'),
            'bannerPerformanceStats' => $this->activeTab === 'banners'
                ? $this->bannerPerformanceStats($rotator)
                : collect(),
        ];
    }

    public function referrerHref(?string $refUrl): ?string
    {
        if (! $refUrl) {
            return null;
        }

        if (str_starts_with($refUrl, 'http://') || str_starts_with($refUrl, 'https://')) {
            return $refUrl;
        }

        return 'https://' . $refUrl;
    }

    private function rotator(): BannerRotator
    {
        return BannerRotator::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->rotatorId)
            ->firstOrFail();
    }

    private function dailyEvents(BannerRotator $rotator): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        if (! $this->hasRotatorStatsColumn()) {
            $days = collect(CarbonPeriod::create($start, $end))
                ->map(fn(Carbon $date) => [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M j'),
                    'impressions' => 0,
                    'clicks' => 0,
                ])
                ->values();

            return [
                'chartData' => [
                    'labels' => $days->pluck('label')->all(),
                    'dates' => $days->pluck('date')->all(),
                    'totals' => $days->pluck('impressions')->all(),
                    'uniques' => $days->pluck('clicks')->all(),
                ],
                'maxEvents' => 1,
            ];
        }

        $eventsByDay = $this->dailyEventsQuery($rotator)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy('event_date');

        $days = collect(CarbonPeriod::create($start, $end))
            ->map(fn(Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'impressions' => (int) ($eventsByDay[$date->toDateString()]?->impressions ?? 0),
                'clicks' => (int) ($eventsByDay[$date->toDateString()]?->clicks ?? 0),
            ])
            ->values();

        return [
            'chartData' => [
                'labels' => $days->pluck('label')->all(),
                'dates' => $days->pluck('date')->all(),
                'totals' => $days->pluck('impressions')->all(),
                'uniques' => $days->pluck('clicks')->all(),
            ],
            'maxEvents' => max(1, $days->max(fn($day) => max($day['impressions'], $day['clicks']))),
        ];
    }

    private function summaryStats(BannerRotator $rotator): array
    {
        if (! $this->hasRotatorStatsColumn()) {
            return [
                'impressions' => 0,
                'clicks' => 0,
            'ctr'                => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
        ];
    }

    private function referrerStats(BannerRotator $rotator)
    {
        $search = trim($this->referrerSearch);

        if (! $this->hasRotatorStatsColumn()) {
            return $this->emptyPaginator('referrerPage');
        }

        return $this->referrerStatsQuery($rotator)
            ->when($search !== '', fn($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn($query) => $query->orderBy('ref_url'))
            ->simplePaginate(25, pageName: 'referrerPage');
    }

    private function bannerPerformanceStats(BannerRotator $rotator)
    {
        if (! $this->hasRotatorStatsColumn()) {
            return collect();
        }

        $hasBannerSizeColumns = $this->hasBannerSizeColumns();

        $aggregate = DB::table('daily_banner_rotator_banner_stats')
            ->select('banner_id')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->where('banner_rotator_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupBy('banner_id');

        $today = DB::table('banner_stats')
            ->select('banner_id')
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_rotator_id', $rotator->id)
            ->where('created_at', '>=', today())
            ->groupBy('banner_id');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'banner_performance')
            ->join('banners', 'banners.id', '=', 'banner_performance.banner_id')
            ->select('banners.id', 'banners.name', 'banners.image_url', 'banners.banner_slug')
            ->when(
                $hasBannerSizeColumns,
                fn($query) => $query->addSelect('banners.width', 'banners.height'),
                fn($query) => $query->selectRaw('NULL as width, NULL as height'),
            )
            ->selectRaw('SUM(banner_performance.impressions) as impressions')
            ->selectRaw('SUM(banner_performance.clicks) as clicks')
            ->groupBy('banners.id', 'banners.name', 'banners.image_url', 'banners.banner_slug')
            ->when($hasBannerSizeColumns, fn($query) => $query->groupBy('banners.width', 'banners.height'))
            ->orderByDesc('impressions')
            ->get();
    }

    private function dailyEventRecords(BannerRotator $rotator)
    {
        if (! $this->hasRotatorStatsColumn()) {
            return $this->emptyPaginator('dailyEventsPage');
        }

        return $this->dailyEventsQuery($rotator)
            ->orderByDesc('event_date')
            ->simplePaginate(25, pageName: 'dailyEventsPage');
    }

    private function dailyEventsQuery(BannerRotator $rotator)
    {
        $aggregate = DB::table('daily_banner_referrer_stats')
            ->selectRaw('stat_date as event_date')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupBy('stat_date');

        $today = DB::table('banner_stats')
            ->selectRaw('DATE(created_at) as event_date')
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_rotator_id', $rotator->id)
            ->where('created_at', '>=', today())
            ->groupByRaw('DATE(created_at)');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'daily_events')
            ->selectRaw('event_date')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->groupBy('event_date');
    }

    private function referrerStatsQuery(BannerRotator $rotator)
    {
        $aggregate = DB::table('daily_banner_referrer_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions + clicks) as total_events')
            ->where('source_type', 'rotator')
            ->where('source_id', $rotator->id)
            ->where('stat_date', '<', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        $today = DB::table('banner_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw('COUNT(*) as total_events')
            ->where('banner_rotator_id', $rotator->id)
            ->where('created_at', '>=', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'referrer_stats')
            ->selectRaw('ref_url')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(total_events) as total_events')
            ->groupBy('ref_url');
    }

    private function hasRotatorStatsColumn(): bool
    {
        return $this->hasStatsColumn ??= Schema::hasColumn('banner_stats', 'banner_rotator_id');
    }

    private function hasBannerSizeColumns(): bool
    {
        return $this->hasSizeColumns ??= Schema::hasColumn('banners', 'width')
            && Schema::hasColumn('banners', 'height');
    }

    private function emptyPaginator(string $pageName): Paginator
    {
        return new Paginator([], 25, 1, ['pageName' => $pageName]);
    }
};
?>

@php
$imageUrl = route('bannerrotators.image', $rotator->rotator_slug);
$clickUrl = route('bannerrotators.click', $rotator->rotator_slug);
@endphp

<section class="container mx-auto space-y-8" x-data="{ activeTab: $wire.entangle('activeTab') }" data-tracker-stats-root>
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Banner rotator stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Banner rotator stats') }}</flux:heading>
            <div class="flex min-w-0 flex-col gap-1 text-sm">
                <flux:link href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="block max-w-xl truncate">{{ $imageUrl }}</flux:link>
                <flux:link href="{{ $clickUrl }}" target="_blank" rel="noreferrer" class="block max-w-xl truncate">{{ $clickUrl }}</flux:link>
            </div>
        </div>

        <flux:button variant="filled" :href="route('bannerrotators')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Impressions') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['impressions']) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Clicks') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['clicks']) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('CTR') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['ctr'], 2) }}%</flux:heading>
            </div>
        </flux:card>
    </div>

    <div class="space-y-6">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button type="button" wire:click="showTab('overview')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Overview') }}</button>
            <button type="button" wire:click="showTab('events')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'events' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Daily events') }}</button>
            <button type="button" wire:click="showTab('banners')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'banners' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Banners') }}</button>
            <button type="button" wire:click="showTab('referrers')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Referrers') }}</button>
        </div>

        <section class="space-y-8" x-show="activeTab === 'overview'">
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading>{{ __('Daily events') }}</flux:heading>
                        <flux:subheading>{{ __('Last 30 days') }}</flux:subheading>
                    </div>
                    <flux:text>{{ __('Peak: :count', ['count' => $maxEvents]) }}</flux:text>
                </div>
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:ignore>
                    <div class="h-80">
                        <canvas data-tracker-chart data-chart='@json($chartData)' data-total-hits-label="{{ __('Impressions') }}" data-unique-hits-label="{{ __('Clicks') }}"></canvas>
                    </div>
                </div>
            </section>

        </section>

        <section class="space-y-4" x-show="activeTab === 'banners'">
            <div>
                <flux:heading>{{ __('Banner performance') }}</flux:heading>
                <flux:subheading>{{ __('Events grouped by attached banner') }}</flux:subheading>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Banner') }}</flux:table.column>
                    <flux:table.column>{{ __('Impressions') }}</flux:table.column>
                    <flux:table.column>{{ __('Clicks') }}</flux:table.column>
                    <flux:table.column>{{ __('CTR') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($bannerPerformanceStats as $stat)
                    @php
                    $ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0;
                    $previewWidth = $stat->width ? max(1, (int) round($stat->width / 2)) : 160;
                    $previewHeight = $stat->height ? max(1, (int) round($stat->height / 2)) : 80;
                    @endphp
                    <flux:table.row wire:key="banner-rotator-performance-{{ $stat->id }}">
                        <flux:table.cell>
                            <div class="flex max-w-md flex-col items-start gap-2">
                                <img
                                    src="{{ $stat->image_url }}"
                                    alt="{{ $stat->name }}"
                                    class="rounded object-cover"
                                    style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px;">
                                <span class="max-w-full truncate">{{ $stat->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->impressions) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->clicks) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($ctr, 2) }}%</flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" align="center">{{ __('No banner events yet.') }}</flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <section class="space-y-4" x-show="activeTab === 'events'">
            <div>
                <flux:heading>{{ __('Daily events') }}</flux:heading>
                <flux:subheading>{{ __('All rotator banner events grouped by day') }}</flux:subheading>
            </div>
            <flux:table :paginate="$dailyEventRecords">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Impressions') }}</flux:table.column>
                    <flux:table.column>{{ __('Clicks') }}</flux:table.column>
                    <flux:table.column>{{ __('CTR') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($dailyEventRecords as $stat)
                    @php
                    $ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0;
                    @endphp
                    <flux:table.row wire:key="banner-rotator-daily-event-{{ $stat->event_date }}">
                        <flux:table.cell>{{ \Carbon\Carbon::parse($stat->event_date)->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->impressions) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->clicks) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($ctr, 2) }}%</flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" align="center">{{ __('No events yet.') }}</flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <section class="space-y-4" x-show="activeTab === 'referrers'">
            <div>
                <flux:heading>{{ __('Referrers') }}</flux:heading>
                <flux:subheading>{{ __('All rotator events grouped by referrer') }}</flux:subheading>
            </div>
            <flux:input wire:model.live.debounce.300ms="referrerSearch" :label="__('Filter by URL')" type="search" autocomplete="off" placeholder="example.com" class="max-w-md" />
            <flux:table :paginate="$referrerStats">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'ref_url'" :direction="$sortDirection" wire:click="sortBy('ref_url')" class="cursor-pointer">{{ __('Ref URL') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'impressions'" :direction="$sortDirection" wire:click="sortBy('impressions')" class="cursor-pointer">{{ __('Impressions') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'clicks'" :direction="$sortDirection" wire:click="sortBy('clicks')" class="cursor-pointer">{{ __('Clicks') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($referrerStats as $stat)
                    <flux:table.row wire:key="banner-rotator-referrer-{{ md5($stat->ref_url ?: 'direct') }}">
                        <flux:table.cell>
                            @if ($href = $this->referrerHref($stat->ref_url))
                            <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block max-w-md truncate">{{ $stat->ref_url }}</flux:link>
                            @else
                            {{ __('Direct / unknown') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->impressions) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat->clicks) }}</flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" align="center">{{ __('No events yet.') }}</flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>
    </div>
</section>

@include('partials.banner-stats-chart')
