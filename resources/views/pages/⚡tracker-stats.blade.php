<?php

use App\Models\Tracker;
use App\Models\TrackerStat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tracker stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $trackerId = 0;

    public string $selectedDate = '';

    public string $sortField = 'total_hits';

    public string $sortDirection = 'desc';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->trackerId = Tracker::query()
            ->where('user_id', Auth::id())
            ->where('tracker_slug', $slug)
            ->firstOrFail()
            ->id;
        $this->selectedDate = now()->toDateString();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:tracker-stats.{$this->trackerId},.tracker.stats.updated" => 'refreshStats',
        ];
    }

    public function refreshStats(): void
    {
        $this->dispatch('tracker-chart-updated', chartData: $this->freshChartData());
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = Carbon::parse($date)->toDateString();
        $this->resetPage('dailyHitsCursor');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['total_hits', 'unique_hits'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            $this->resetPage('dailyHitsCursor');

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'desc';
        $this->resetPage('dailyHitsCursor');
    }

    public function with(): array
    {
        $tracker = $this->tracker();
        $dailyHits = $this->dailyHits($tracker);

        return [
            'tracker' => $tracker,
            'summaryStats' => $this->summaryStats($tracker),
            'breakdownStats' => $this->breakdownStats($tracker),
            'chartData' => $dailyHits['chartData'],
            'maxHits' => $dailyHits['maxHits'],
            'totalHitRecords' => $this->totalHitRecords($tracker),
            'referrerStats' => $this->referrerStats($tracker),
        ];
    }

    public function freshChartData(): array
    {
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

    private function tracker(): Tracker
    {
        return Tracker::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->trackerId)
            ->firstOrFail();
    }

    private function dailyHits(Tracker $tracker): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $hitsByDay = TrackerStat::query()
            ->selectRaw('DATE(created_at) as hit_date, COUNT(*) as total')
            ->where('tracker_id', $tracker->id)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('hit_date')
            ->pluck('total', 'hit_date');

        $days = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'total' => (int) ($hitsByDay[$date->toDateString()] ?? 0),
            ])
            ->values();

        return [
            'chartData' => [
                'labels' => $days->pluck('label')->all(),
                'dates' => $days->pluck('date')->all(),
                'totals' => $days->pluck('total')->all(),
                'selectedDate' => $this->selectedDate,
            ],
            'maxHits' => max(1, $days->max('total')),
        ];
    }

    private function summaryStats(Tracker $tracker): array
    {
        return [
            'total_hits' => TrackerStat::query()
                ->where('tracker_id', $tracker->id)
                ->count(),
            'unique_hits' => TrackerStat::query()
                ->where('tracker_id', $tracker->id)
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    private function breakdownStats(Tracker $tracker): array
    {
        return [
            'device_types' => $this->groupedStatCounts($tracker, 'device_type'),
            'operating_systems' => $this->groupedStatCounts($tracker, 'operating_system'),
            'browsers' => $this->groupedStatCounts($tracker, 'browser'),
        ];
    }

    private function groupedStatCounts(Tracker $tracker, string $field)
    {
        return TrackerStat::query()
            ->selectRaw("COALESCE({$field}, ?) as label, COUNT(*) as total", [__('Unknown')])
            ->where('tracker_id', $tracker->id)
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
    }

    private function referrerStats(Tracker $tracker)
    {
        return TrackerStat::query()
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->where('tracker_id', $tracker->id)
            ->whereDate('created_at', $this->selectedDate)
            ->groupByRaw("COALESCE(ref_url, '')")
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderBy('ref_url')
            ->cursorPaginate(25, cursorName: 'dailyHitsCursor');
    }

    private function totalHitRecords(Tracker $tracker)
    {
        return TrackerStat::query()
            ->select([
                'id',
                'created_at',
                'ref_url',
                'device_type',
                'operating_system',
                'browser',
            ])
            ->where('tracker_id', $tracker->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25, cursorName: 'totalHitsCursor');
    }
};
?>

<section
    class="container mx-auto space-y-8"
    x-data="{ activeTab: 'total' }"
    data-tracker-stats-root>
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Tracker stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Tracker stats') }}</flux:heading>
            <flux:subheading>{{ route('trackers.redirect', $tracker->tracker_slug) }}</flux:subheading>
        </div>

        <flux:button variant="filled" :href="route('trackers')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap gap-4">
        <flux:card class="w-fit min-w-40">
            <div class="space-y-2">
                <flux:text>{{ __('Total Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['total_hits']) }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="w-fit min-w-40">
            <div class="space-y-2">
                <flux:text>{{ __('Unique Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['unique_hits']) }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="w-fit min-w-44">
            <div class="space-y-3">
                <flux:text>{{ __('Device Type') }}</flux:text>
                <div class="space-y-1">
                    @forelse ($breakdownStats['device_types'] as $stat)
                    <div class="flex items-center justify-between gap-6 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ str($stat->label)->title() }}</span>
                        <span class="font-medium">{{ number_format($stat->total) }}</span>
                    </div>
                    @empty
                    <flux:text>{{ __('No data') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:card>

        <flux:card class="w-fit min-w-44">
            <div class="space-y-3">
                <flux:text>{{ __('Operating System') }}</flux:text>
                <div class="space-y-1">
                    @forelse ($breakdownStats['operating_systems'] as $stat)
                    <div class="flex items-center justify-between gap-6 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ $stat->label }}</span>
                        <span class="font-medium">{{ number_format($stat->total) }}</span>
                    </div>
                    @empty
                    <flux:text>{{ __('No data') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:card>

        <flux:card class="w-fit min-w-44">
            <div class="space-y-3">
                <flux:text>{{ __('Browser') }}</flux:text>
                <div class="space-y-1">
                    @forelse ($breakdownStats['browsers'] as $stat)
                    <div class="flex items-center justify-between gap-6 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ $stat->label }}</span>
                        <span class="font-medium">{{ number_format($stat->total) }}</span>
                    </div>
                    @empty
                    <flux:text>{{ __('No data') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:card>
    </div>

    <div class="space-y-6">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'total' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                @click="activeTab = 'total'">
                {{ __('Total hits') }}
            </button>

            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'daily' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'"
                @click="activeTab = 'daily'; $nextTick(() => document.dispatchEvent(new CustomEvent('tracker-chart-resize')))">
                {{ __('Daily hits') }}
            </button>
        </div>

        <section class="space-y-4" x-show="activeTab === 'total'">
            <div>
                <flux:heading>{{ __('Total hits') }}</flux:heading>
                <flux:subheading>{{ __('Newest hits first') }}</flux:subheading>
            </div>

            <flux:pagination :paginator="$totalHitRecords" class="border-t-0 border-b pb-3 pt-0" />

            <flux:table :paginate="$totalHitRecords">
                <flux:table.columns>
                    <flux:table.column>{{ __('Created at') }}</flux:table.column>
                    <flux:table.column>{{ __('Ref URL') }}</flux:table.column>
                    <flux:table.column>{{ __('Device') }}</flux:table.column>
                    <flux:table.column>{{ __('Operating system') }}</flux:table.column>
                    <flux:table.column>{{ __('Browser') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($totalHitRecords as $hit)
                    <flux:table.row wire:key="tracker-hit-{{ $hit->id }}">
                        <flux:table.cell>{{ $hit->created_at?->format('Y-m-d H:i:s') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($href = $this->referrerHref($hit->ref_url))
                            <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block max-w-md truncate">
                                {{ $hit->ref_url }}
                            </flux:link>
                            @else
                            {{ __('Direct / unknown') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $hit->device_type ? str($hit->device_type)->title() : __('Unknown') }}</flux:table.cell>
                        <flux:table.cell>{{ $hit->operating_system ?: __('Unknown') }}</flux:table.cell>
                        <flux:table.cell>{{ $hit->browser ?: __('Unknown') }}</flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" align="center">
                            {{ __('No hits yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <div x-show="activeTab === 'daily'" class="space-y-8">
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
                        <canvas data-tracker-chart data-chart='@json($chartData)'></canvas>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div>
                    <flux:heading>{{ __('Referrer stats') }}</flux:heading>
                    <flux:subheading>{{ Carbon::parse($selectedDate)->format('F j, Y') }}</flux:subheading>
                </div>

                <flux:pagination :paginator="$referrerStats" class="border-t-0 border-b pb-3 pt-0" />

                <flux:table :paginate="$referrerStats">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Ref URL') }}</flux:table.column>
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
                            <flux:table.cell>{{ $stat->total_hits }}</flux:table.cell>
                            <flux:table.cell>{{ $stat->unique_hits }}</flux:table.cell>
                        </flux:table.row>
                        @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" align="center">
                                {{ __('No hits for this day.') }}
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
            const grid = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255,255,255,.10)' : 'rgba(39,39,42,.10)';
            const text = window.matchMedia('(prefers-color-scheme: dark)').matches ? '#d4d4d8' : '#52525b';

            if (canvas._trackerChart) {
                canvas._trackerChart.destroy();
            }

            const selectedPointRadius = () => chartData.dates.map((date) => date === chartData.selectedDate ? 6 : 3);
            const selectedPointBackground = () => chartData.dates.map((date) => date === chartData.selectedDate ? accent : '#fff');
            const applyChartData = (freshChartData) => {
                chartData = freshChartData;
                canvas.dataset.chart = JSON.stringify(freshChartData);
                canvas._trackerChart.data.labels = chartData.labels;
                canvas._trackerChart.data.datasets[0].data = chartData.totals;
                canvas._trackerChart.data.datasets[0].pointRadius = selectedPointRadius();
                canvas._trackerChart.data.datasets[0].pointBackgroundColor = selectedPointBackground();
                canvas._trackerChart.update();
            };
            const updateSelection = (date) => {
                chartData.selectedDate = date;
                canvas._trackerChart.data.datasets[0].pointRadius = selectedPointRadius();
                canvas._trackerChart.data.datasets[0].pointBackgroundColor = selectedPointBackground();
                canvas._trackerChart.update();
            };

            canvas._trackerChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.totals,
                        borderColor: accent,
                        backgroundColor: 'rgba(37, 99, 235, .12)',
                        fill: true,
                        tension: .35,
                        pointRadius: selectedPointRadius(),
                        pointHoverRadius: 7,
                        pointBackgroundColor: selectedPointBackground(),
                        pointBorderColor: accent,
                        pointBorderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    onClick: (_event, elements) => {
                        if (!elements.length) {
                            return;
                        }

                        updateSelection(chartData.dates[elements[0].index]);
                        $wire.selectDate(chartData.dates[elements[0].index]);
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => chartData.dates[items[0].dataIndex],
                                label: (item) => `${item.formattedValue} hits`,
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
