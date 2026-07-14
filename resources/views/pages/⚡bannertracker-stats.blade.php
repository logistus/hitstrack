<?php

use App\Models\Banner;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Banner tracker stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $bannerId = 0;

    public string $sortField = 'total_events';

    public string $sortDirection = 'desc';

    public string $referrerSearch = '';

    public string $activeTab = 'overview';


    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->bannerId = Banner::query()
            ->where('user_id', Auth::id())
            ->where('banner_slug', $slug)
            ->firstOrFail()
            ->id;
    }

    public function updatedReferrerSearch(): void
    {
        $this->activeTab = 'referrers';
        $this->resetPage('referrerPage');
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'events', 'referrers'], true)) {
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
        $banner = $this->banner();
        $dailyEvents = AnalyticsCache::remember(
            'banner-tracker',
            $banner->id,
            'daily-events',
            fn (): array => $this->dailyEvents($banner),
        );

        return [
            'banner' => $banner,
            'summaryStats' => AnalyticsCache::remember(
                'banner-tracker',
                $banner->id,
                'summary',
                fn (): array => $this->summaryStats($banner),
            ),
            'chartData' => $dailyEvents['chartData'],
            'maxEvents' => $dailyEvents['maxEvents'],
            'dailyEventRecords' => $this->activeTab === 'events'
                ? $this->dailyEventRecords($banner)
                : $this->emptyPaginator('dailyEventsPage'),
            'referrerStats' => $this->activeTab === 'referrers'
                ? $this->referrerStats($banner)
                : $this->emptyPaginator('referrerPage'),
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

        return 'https://'.$refUrl;
    }

    private function banner(): Banner
    {
        return Banner::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->bannerId)
            ->firstOrFail();
    }

    private function dailyEvents(Banner $banner): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $eventsByDay = $this->dailyEventsQuery($banner)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy('event_date');

        $days = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => [
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
            'maxEvents' => max(1, $days->max(fn ($day) => max($day['impressions'], $day['clicks']))),
        ];
    }

    private function summaryStats(Banner $banner): array
    {
        $aggregate = DB::table('daily_banner_referrer_stats')
            ->where('source_type', 'banner')
            ->where('source_id', $banner->id)
            ->where('stat_date', '<', today())
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions')
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
            ->first();

        $today = DB::table('banner_stats')
            ->where('banner_id', $banner->id)
            ->where('created_at', '>=', today());

        $impressions = (int) $aggregate->impressions + (clone $today)
            ->where('banner_id', $banner->id)
            ->where('event_type', 'impression')
            ->count();
        $clicks = (int) $aggregate->clicks + (clone $today)
            ->where('event_type', 'click')
            ->count();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
        ];
    }

    private function referrerStats(Banner $banner)
    {
        $search = trim($this->referrerSearch);

        return $this->referrerStatsQuery($banner)
            ->when($search !== '', fn ($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn ($query) => $query->orderBy('ref_url'))
            ->simplePaginate(25, pageName: 'referrerPage');
    }

    private function dailyEventRecords(Banner $banner)
    {
        return $this->dailyEventsQuery($banner)
            ->orderByDesc('event_date')
            ->simplePaginate(25, pageName: 'dailyEventsPage');
    }

    private function dailyEventsQuery(Banner $banner)
    {
        $aggregate = DB::table('daily_banner_referrer_stats')
            ->selectRaw('stat_date as event_date')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->where('source_type', 'banner')
            ->where('source_id', $banner->id)
            ->where('stat_date', '<', today())
            ->groupBy('stat_date');

        $today = DB::table('banner_stats')
            ->selectRaw('DATE(created_at) as event_date')
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_id', $banner->id)
            ->where('created_at', '>=', today())
            ->groupByRaw('DATE(created_at)');

        return DB::query()
            ->fromSub($aggregate->unionAll($today), 'daily_events')
            ->selectRaw('event_date')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->groupBy('event_date');
    }

    private function referrerStatsQuery(Banner $banner)
    {
        $aggregate = DB::table('daily_banner_referrer_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions + clicks) as total_events')
            ->where('source_type', 'banner')
            ->where('source_id', $banner->id)
            ->where('stat_date', '<', today())
            ->groupByRaw("COALESCE(ref_url, '')");

        $today = DB::table('banner_stats')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw('COUNT(*) as total_events')
            ->where('banner_id', $banner->id)
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

    private function emptyPaginator(string $pageName): Paginator
    {
        return new Paginator([], 25, 1, ['pageName' => $pageName]);
    }
};
?>

@php
    $imageUrl = route('bannertrackers.image', $banner->banner_slug);
    $clickUrl = route('bannertrackers.click', $banner->banner_slug);
@endphp

<section class="container mx-auto space-y-8" x-data="{ activeTab: $wire.entangle('activeTab') }" data-tracker-stats-root>
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Banner tracker stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Banner tracker stats') }}</flux:heading>
            <flux:subheading>{{ $banner->name }}</flux:subheading>
            <div class="flex min-w-0 flex-col gap-1 text-sm">
                <flux:link href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="block max-w-xl truncate">{{ $imageUrl }}</flux:link>
                <flux:link href="{{ $clickUrl }}" target="_blank" rel="noreferrer" class="block max-w-xl truncate">{{ $clickUrl }}</flux:link>
            </div>
        </div>

        <flux:button variant="filled" :href="route('bannertrackers')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card><div class="space-y-2"><flux:text>{{ __('Impressions') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['impressions']) }}</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('Clicks') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['clicks']) }}</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('CTR') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['ctr'], 2) }}%</flux:heading></div></flux:card>
    </div>

    <div class="space-y-6">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button type="button" wire:click="showTab('overview')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Overview') }}</button>
            <button type="button" wire:click="showTab('events')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'events' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Daily events') }}</button>
            <button type="button" wire:click="showTab('referrers')" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'">{{ __('Referrers') }}</button>
        </div>

        <section class="space-y-8" x-show="activeTab === 'overview'">
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div><flux:heading>{{ __('Daily events') }}</flux:heading><flux:subheading>{{ __('Last 30 days') }}</flux:subheading></div>
                    <flux:text>{{ __('Peak: :count', ['count' => $maxEvents]) }}</flux:text>
                </div>
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:ignore>
                    <div class="h-80">
                        <canvas data-tracker-chart data-chart='@json($chartData)' data-total-hits-label="{{ __('Impressions') }}" data-unique-hits-label="{{ __('Clicks') }}"></canvas>
                    </div>
                </div>
            </section>



        </section>

        <section class="space-y-4" x-show="activeTab === 'events'">
            <div><flux:heading>{{ __('Daily events') }}</flux:heading><flux:subheading>{{ __('All banner events grouped by day') }}</flux:subheading></div>
            <flux:table :paginate="$dailyEventRecords">
                <flux:table.columns><flux:table.column>{{ __('Date') }}</flux:table.column><flux:table.column>{{ __('Impressions') }}</flux:table.column><flux:table.column>{{ __('Clicks') }}</flux:table.column><flux:table.column>{{ __('CTR') }}</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @forelse ($dailyEventRecords as $stat)
                    @php
                        $ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0;
                    @endphp
                    <flux:table.row wire:key="banner-daily-event-{{ $stat->event_date }}"><flux:table.cell>{{ \Carbon\Carbon::parse($stat->event_date)->format('Y-m-d') }}</flux:table.cell><flux:table.cell>{{ number_format($stat->impressions) }}</flux:table.cell><flux:table.cell>{{ number_format($stat->clicks) }}</flux:table.cell><flux:table.cell>{{ number_format($ctr, 2) }}%</flux:table.cell></flux:table.row>
                    @empty
                    <flux:table.row><flux:table.cell colspan="4" align="center">{{ __('No events yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <section class="space-y-4" x-show="activeTab === 'referrers'">
            <div><flux:heading>{{ __('Referrers') }}</flux:heading><flux:subheading>{{ __('All banner events grouped by referrer') }}</flux:subheading></div>
            <flux:input wire:model.live.debounce.300ms="referrerSearch" :label="__('Filter by URL')" type="search" autocomplete="off" placeholder="example.com" class="max-w-md" />
            <flux:table :paginate="$referrerStats">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'ref_url'" :direction="$sortDirection" wire:click="sortBy('ref_url')" class="cursor-pointer">{{ __('Ref URL') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'impressions'" :direction="$sortDirection" wire:click="sortBy('impressions')" class="cursor-pointer">{{ __('Impressions') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'clicks'" :direction="$sortDirection" wire:click="sortBy('clicks')" class="cursor-pointer">{{ __('Clicks') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($referrerStats as $stat)
                    <flux:table.row wire:key="banner-referrer-{{ md5($stat->ref_url ?: 'direct') }}">
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
                    <flux:table.row><flux:table.cell colspan="3" align="center">{{ __('No events yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>
    </div>
</section>

@include('partials.banner-stats-chart')
