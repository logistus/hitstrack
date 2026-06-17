<?php

use App\Models\Rotator;
use App\Models\RotatorStat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rotator stats')] class extends Component
{
    use WithPagination;

    public string $slug = '';

    public int $rotatorId = 0;

    public string $selectedDate = '';

    public string $sortField = 'total_hits';

    public string $sortDirection = 'desc';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->rotatorId = Rotator::query()
            ->where('user_id', Auth::id())
            ->where('rotator_slug', $slug)
            ->firstOrFail()
            ->id;
        $this->selectedDate = now()->toDateString();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:rotator-stats.{$this->rotatorId},.rotator.stats.updated" => 'refreshStats',
        ];
    }

    public function refreshStats(): void
    {
        $this->dispatch('rotator-chart-updated', chartData: $this->freshChartData());
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = Carbon::parse($date)->toDateString();
        $this->resetPage('dailyHitsPage');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['total_hits', 'unique_hits'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            $this->resetPage('dailyHitsPage');

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'desc';
        $this->resetPage('dailyHitsPage');
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
            'totalHitRecords' => $this->totalHitRecords($rotator),
            'referrerStats' => $this->referrerStats($rotator),
        ];
    }

    public function freshChartData(): array
    {
        return $this->dailyHits($this->rotator())['chartData'];
    }

    private function rotator(): Rotator
    {
        return Rotator::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->rotatorId)
            ->firstOrFail();
    }

    private function dailyHits(Rotator $rotator): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $hitsByDay = RotatorStat::query()
            ->selectRaw('DATE(created_at) as hit_date, COUNT(*) as total')
            ->where('rotator_id', $rotator->id)
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

    private function summaryStats(Rotator $rotator): array
    {
        return [
            'total_hits' => RotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->count(),
            'unique_hits' => RotatorStat::query()
                ->where('rotator_id', $rotator->id)
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    private function referrerStats(Rotator $rotator)
    {
        return RotatorStat::query()
            ->select([
                'ref_url',
                DB::raw('COUNT(*) as total_hits'),
                DB::raw('COUNT(DISTINCT ip_address) as unique_hits'),
            ])
            ->where('rotator_id', $rotator->id)
            ->whereDate('created_at', $this->selectedDate)
            ->groupBy('ref_url')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25, pageName: 'dailyHitsPage');
    }

    private function breakdownStats(Rotator $rotator): array
    {
        return [
            'device_types' => $this->groupedStatCounts($rotator, 'device_type'),
            'operating_systems' => $this->groupedStatCounts($rotator, 'operating_system'),
            'browsers' => $this->groupedStatCounts($rotator, 'browser'),
        ];
    }

    private function groupedStatCounts(Rotator $rotator, string $field)
    {
        return RotatorStat::query()
            ->selectRaw("COALESCE({$field}, ?) as label, COUNT(*) as total", [__('Unknown')])
            ->where('rotator_id', $rotator->id)
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
    }

    private function totalHitRecords(Rotator $rotator)
    {
        return RotatorStat::query()
            ->select([
                'id',
                'created_at',
                'ref_url',
                'device_type',
                'operating_system',
                'browser',
            ])
            ->where('rotator_id', $rotator->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25, pageName: 'totalHitsPage');
    }
};
?>

<section
    class="container mx-auto space-y-8"
    x-data="{ activeTab: 'total' }">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Rotator stats') }}</flux:heading>
            <flux:heading size="xl">{{ __('Rotator stats') }}</flux:heading>
            <flux:subheading>{{ route('rotators.redirect', $rotator->rotator_slug) }}</flux:subheading>
        </div>

        <flux:button variant="filled" :href="route('rotators')" wire:navigate>
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
                @click="activeTab = 'daily'; $nextTick(() => document.dispatchEvent(new CustomEvent('rotator-chart-resize')))">
                {{ __('Daily hits') }}
            </button>
        </div>

        <section class="space-y-4" x-show="activeTab === 'total'">
            <div>
                <flux:heading>{{ __('Total hits') }}</flux:heading>
                <flux:subheading>{{ __('Newest hits first') }}</flux:subheading>
            </div>

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
                    <flux:table.row wire:key="rotator-hit-{{ $hit->id }}">
                        <flux:table.cell>{{ $hit->created_at?->format('Y-m-d H:i:s') }}</flux:table.cell>
                        <flux:table.cell>{{ $hit->ref_url ?: __('Direct / unknown') }}</flux:table.cell>
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
                        <canvas data-rotator-chart data-chart='@json($chartData)'></canvas>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div>
                    <flux:heading>{{ __('Referrer stats') }}</flux:heading>
                    <flux:subheading>{{ Carbon::parse($selectedDate)->format('F j, Y') }}</flux:subheading>
                </div>

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
                        <flux:table.row wire:key="rotator-referrer-{{ md5($stat->ref_url ?: 'direct') }}">
                            <flux:table.cell>{{ $stat->ref_url ?: __('Direct / unknown') }}</flux:table.cell>
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

            const canvas = $wire.$el.querySelector('[data-rotator-chart]');

            if (!canvas) {
                return;
            }

            let chartData = JSON.parse(canvas.dataset.chart);
            const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600').trim() || '#2563eb';
            const grid = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255,255,255,.10)' : 'rgba(39,39,42,.10)';
            const text = window.matchMedia('(prefers-color-scheme: dark)').matches ? '#d4d4d8' : '#52525b';

            if (canvas._rotatorChart) {
                canvas._rotatorChart.destroy();
            }

            const selectedPointRadius = () => chartData.dates.map((date) => date === chartData.selectedDate ? 6 : 3);
            const selectedPointBackground = () => chartData.dates.map((date) => date === chartData.selectedDate ? accent : '#fff');
            const applyChartData = (freshChartData) => {
                chartData = freshChartData;
                canvas.dataset.chart = JSON.stringify(freshChartData);
                canvas._rotatorChart.data.labels = chartData.labels;
                canvas._rotatorChart.data.datasets[0].data = chartData.totals;
                canvas._rotatorChart.data.datasets[0].pointRadius = selectedPointRadius();
                canvas._rotatorChart.data.datasets[0].pointBackgroundColor = selectedPointBackground();
                canvas._rotatorChart.update();
            };
            const updateSelection = (date) => {
                chartData.selectedDate = date;
                canvas._rotatorChart.data.datasets[0].pointRadius = selectedPointRadius();
                canvas._rotatorChart.data.datasets[0].pointBackgroundColor = selectedPointBackground();
                canvas._rotatorChart.update();
            };

            canvas._rotatorChart = new Chart(canvas, {
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
