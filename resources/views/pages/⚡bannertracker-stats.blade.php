<?php

use App\Models\Banner;
use App\Models\BannerStat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
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
        $this->resetPage('referrerPage');
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
        $dailyEvents = $this->dailyEvents($banner);

        return [
            'banner' => $banner,
            'summaryStats' => $this->summaryStats($banner),
            'breakdownStats' => $this->breakdownStats($banner),
            'chartData' => $dailyEvents['chartData'],
            'maxEvents' => $dailyEvents['maxEvents'],
            'dailyEventRecords' => $this->dailyEventRecords($banner),
            'referrerStats' => $this->referrerStats($banner),
            'pageStats' => $this->pageStats($banner),
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

        $eventsByDay = BannerStat::query()
            ->selectRaw('DATE(created_at) as event_date')
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_id', $banner->id)
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
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

    private function summaryStats(Banner $banner): array
    {
        $impressions = BannerStat::query()
            ->where('banner_id', $banner->id)
            ->where('event_type', 'impression')
            ->count();

        $clicks = BannerStat::query()
            ->where('banner_id', $banner->id)
            ->where('event_type', 'click')
            ->count();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'unique_impressions' => BannerStat::query()
                ->where('banner_id', $banner->id)
                ->where('event_type', 'impression')
                ->distinct('ip_address')
                ->count('ip_address'),
            'unique_clicks' => BannerStat::query()
                ->where('banner_id', $banner->id)
                ->where('event_type', 'click')
                ->distinct('ip_address')
                ->count('ip_address'),
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
        ];
    }

    private function breakdownStats(Banner $banner): array
    {
        return [
            'device_types' => $this->groupedStatCounts($banner, 'device_type'),
            'operating_systems' => $this->groupedStatCounts($banner, 'operating_system'),
            'browsers' => $this->groupedStatCounts($banner, 'browser'),
            'countries' => $this->groupedStatCounts($banner, 'country_code'),
        ];
    }

    private function groupedStatCounts(Banner $banner, string $field)
    {
        $field = match ($field) {
            'device_type', 'operating_system', 'browser', 'country_code' => $field,
            default => throw new \InvalidArgumentException('Invalid field'),
        };

        return BannerStat::query()
            ->selectRaw("COALESCE({$field}, ?) as label, COUNT(*) as total", [__('Unknown')])
            ->where('banner_id', $banner->id)
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
    }

    private function referrerStats(Banner $banner)
    {
        $search = trim($this->referrerSearch);

        return BannerStat::query()
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw('COUNT(*) as total_events')
            ->where('banner_id', $banner->id)
            ->when($search !== '', fn($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->groupByRaw("COALESCE(ref_url, '')")
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn($query) => $query->orderBy('ref_url'))
            ->paginate(25, pageName: 'referrerPage');
    }

    private function pageStats(Banner $banner)
    {
        return BannerStat::query()
            ->selectRaw("COALESCE(page_url, '') as page_url")
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_id', $banner->id)
            ->groupByRaw("COALESCE(page_url, '')")
            ->orderByDesc('impressions')
            ->limit(10)
            ->get();
    }

    private function dailyEventRecords(Banner $banner)
    {
        return BannerStat::query()
            ->selectRaw('DATE(created_at) as event_date')
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->where('banner_id', $banner->id)
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('event_date')
            ->simplePaginate(25, pageName: 'dailyEventsPage');
    }
};
?>

@php
    $imageUrl = route('bannertrackers.image', $banner->banner_slug);
    $clickUrl = route('bannertrackers.click', $banner->banner_slug);
@endphp

<section class="container mx-auto space-y-8" x-data="{ activeTab: 'overview' }" data-tracker-stats-root>
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <flux:card><div class="space-y-2"><flux:text>{{ __('Impressions') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['impressions']) }}</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('Clicks') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['clicks']) }}</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('CTR') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['ctr'], 2) }}%</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('Unique Impressions') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['unique_impressions']) }}</flux:heading></div></flux:card>
        <flux:card><div class="space-y-2"><flux:text>{{ __('Unique Clicks') }}</flux:text><flux:heading size="xl">{{ number_format($summaryStats['unique_clicks']) }}</flux:heading></div></flux:card>
    </div>

    <div class="space-y-6">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'" @click="activeTab = 'overview'; $nextTick(() => document.dispatchEvent(new CustomEvent('tracker-chart-resize')))">{{ __('Overview') }}</button>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'events' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'" @click="activeTab = 'events'">{{ __('Daily events') }}</button>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'" @click="activeTab = 'referrers'">{{ __('Referrers') }}</button>
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

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([__('Device Type') => $breakdownStats['device_types'], __('Operating System') => $breakdownStats['operating_systems'], __('Browser') => $breakdownStats['browsers'], __('Country') => $breakdownStats['countries']] as $label => $stats)
                <flux:card>
                    <div class="space-y-3">
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</div>
                        <div class="space-y-3">
                            @forelse ($stats as $stat)
                            @php($percent = ($summaryStats['impressions'] + $summaryStats['clicks']) > 0 ? min(100, round(($stat->total / ($summaryStats['impressions'] + $summaryStats['clicks'])) * 100)) : 0)
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $label === __('Device Type') ? str($stat->label)->title() : $stat->label }}</span>
                                    <span class="font-medium">{{ number_format($stat->total) }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-full rounded-full bg-blue-600" @style(["width: {$percent}%"])></div></div>
                            </div>
                            @empty
                            <flux:text>{{ __('No data') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                </flux:card>
                @endforeach
            </section>

            <section class="space-y-4">
                <div><flux:heading>{{ __('Top pages') }}</flux:heading><flux:subheading>{{ __('Top page URL values sent with banner requests') }}</flux:subheading></div>
                <flux:table>
                    <flux:table.columns><flux:table.column>{{ __('Page URL') }}</flux:table.column><flux:table.column>{{ __('Impressions') }}</flux:table.column><flux:table.column>{{ __('Clicks') }}</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($pageStats as $stat)
                        <flux:table.row wire:key="banner-page-{{ md5($stat->page_url ?: 'unknown') }}"><flux:table.cell>{{ $stat->page_url ?: __('Unknown') }}</flux:table.cell><flux:table.cell>{{ number_format($stat->impressions) }}</flux:table.cell><flux:table.cell>{{ number_format($stat->clicks) }}</flux:table.cell></flux:table.row>
                        @empty
                        <flux:table.row><flux:table.cell colspan="3" align="center">{{ __('No page data yet.') }}</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </section>
        </section>

        <section class="space-y-4" x-show="activeTab === 'events'">
            <div><flux:heading>{{ __('Daily events') }}</flux:heading><flux:subheading>{{ __('All banner events grouped by day') }}</flux:subheading></div>
            <flux:pagination :paginator="$dailyEventRecords" class="border-t-0 border-b pb-3 pt-0" />
            <flux:table :paginate="$dailyEventRecords">
                <flux:table.columns><flux:table.column>{{ __('Date') }}</flux:table.column><flux:table.column>{{ __('Impressions') }}</flux:table.column><flux:table.column>{{ __('Clicks') }}</flux:table.column><flux:table.column>{{ __('CTR') }}</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @forelse ($dailyEventRecords as $stat)
                    @php($ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0)
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
            <flux:pagination :paginator="$referrerStats" class="border-t-0 border-b pb-3 pt-0" />
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
