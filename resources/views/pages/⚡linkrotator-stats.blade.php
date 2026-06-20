<?php

use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
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

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->rotatorId = LinkRotator::query()
            ->where('user_id', Auth::id())
            ->where('rotator_slug', $slug)
            ->firstOrFail()
            ->id;
    }

    public function refreshStats(): void
    {
        $this->dispatch('rotator-chart-updated', chartData: $this->freshChartData());
    }

    public function updatedReferrerSearch(): void
    {
        $this->resetPage('referrerPage');
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
        $dailyHits = $this->dailyHits($rotator);

        return [
            'rotator' => $rotator,
            'summaryStats' => $this->summaryStats($rotator),
            'breakdownStats' => $this->breakdownStats($rotator),
            'chartData' => $dailyHits['chartData'],
            'maxHits' => $dailyHits['maxHits'],
            'dailyHitRecords' => $this->dailyHitRecords($rotator),
            'referrerStats' => $this->referrerStats($rotator),
            'trackerPerformanceStats' => $this->trackerPerformanceStats($rotator),
        ];
    }

    public function freshChartData(): array
    {
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

        $hitsByDay = LinkRotatorStat::query()
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
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
        return [
            'total_hits' => LinkRotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->count(),
            'unique_hits' => LinkRotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    private function referrerStats(LinkRotator $rotator)
    {
        $search = trim($this->referrerSearch);

        return LinkRotatorStat::query()
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->when($search !== '', fn ($query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->groupByRaw("COALESCE(ref_url, '')")
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn ($query) => $query->orderBy('ref_url'))
            ->paginate(25, pageName: 'referrerPage');
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
            default => throw new \InvalidArgumentException('Invalid field'),
        };

        return LinkRotatorStat::query()
            ->selectRaw("COALESCE({$field}, ?) as label, COUNT(*) as total", [__('Unknown')])
            ->where('rotator_id', $rotator->id)
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
    }

    private function trackerPerformanceStats(LinkRotator $rotator)
    {
        return LinkRotatorStat::query()
            ->join('trackers', 'trackers.id', '=', 'rotator_stats.tracker_id')
            ->select('trackers.id', 'trackers.target_url', 'trackers.tracker_slug')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT rotator_stats.ip_address) as unique_hits')
            ->where('rotator_stats.rotator_id', $rotator->id)
            ->groupBy('trackers.id', 'trackers.target_url', 'trackers.tracker_slug')
            ->orderByDesc('total_hits')
            ->get();
    }

    private function dailyHitRecords(LinkRotator $rotator)
    {
        return LinkRotatorStat::query()
            ->selectRaw('DATE(created_at) as hit_date')
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('rotator_id', $rotator->id)
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('hit_date')
            ->simplePaginate(25, pageName: 'dailyHitsPage');
    }
};
?>

<section
    class="container mx-auto space-y-8"
    x-data="{ activeTab: 'overview' }">
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
                @click="activeTab = 'overview'; $nextTick(() => document.dispatchEvent(new CustomEvent('rotator-chart-resize')))">
                {{ __('Overview') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'hits' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                @click="activeTab = 'hits'">
                {{ __('Daily hits') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'referrers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
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
                                $percent = $summaryStats['total_hits'] > 0 ? min(100, round(($stat->total / $summaryStats['total_hits']) * 100)) : 0;
                            @endphp
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $label === __('Device Type') ? str($stat->label)->title() : $stat->label }}</span>
                                    <span class="font-medium">{{ number_format($stat->total) }}</span>
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

            <section class="space-y-4">
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
                                    <flux:link
                                        href="{{ $stat->target_url }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="block truncate text-zinc-500 dark:text-zinc-400">
                                        {{ $stat->target_url }}
                                    </flux:link>
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
        </section>

        <section class="space-y-4" x-show="activeTab === 'hits'">
            <div>
                <flux:heading>{{ __('Daily hits') }}</flux:heading>
                <flux:subheading>{{ __('All hits grouped by day') }}</flux:subheading>
            </div>

            <flux:pagination :paginator="$dailyHitRecords" class="border-t-0 border-b pb-3 pt-0" />

            <flux:table :paginate="$dailyHitRecords">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Total hits') }}</flux:table.column>
                    <flux:table.column>{{ __('Unique hits') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($dailyHitRecords as $stat)
                    <flux:table.row wire:key="rotator-daily-hit-{{ $stat->hit_date }}">
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

                <flux:pagination :paginator="$referrerStats" class="border-t-0 border-b pb-3 pt-0" />

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
