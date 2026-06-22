<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => 'DataCrove'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="border-b border-white/10 bg-zinc-900/80">
        <header class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-6 lg:px-8">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                    <x-app-logo-icon class="size-8" />
                    <span class="text-lg font-semibold">DataCrove</span>
                </a>
                <p class="mt-2 text-sm text-zinc-400">Public pixel tracking overview.</p>
            </div>
        </header>
    </div>

    <main class="mx-auto max-w-7xl space-y-8 px-6 py-8 lg:px-8" x-data="{ activeTab: @js($activeTab) }">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-white/10 bg-white/[.04] p-5">
                <p class="text-sm text-zinc-400">Total hits</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($summaryStats['total_hits']) }}</p>
            </div>
            <div class="rounded-md border border-white/10 bg-white/[.04] p-5">
                <p class="text-sm text-zinc-400">Unique hits</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($summaryStats['unique_hits']) }}</p>
            </div>
        </section>

        <section class="rounded-md border border-white/10 bg-white/[.04] p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Pixel code</h2>
                    <p class="mt-1 text-sm text-zinc-400">Use this snippet to capture both the current page and its incoming referrer.</p>
                </div>
                <a href="{{ $pixelUrlExample }}" target="_blank" rel="noreferrer" class="text-sm text-blue-300 hover:text-blue-200">{{ $pixelUrlExample }}</a>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-md border border-white/10 bg-zinc-950 p-4 text-xs leading-5 text-zinc-300"><code>{{ $pixelSnippet }}</code></pre>
        </section>

        <div class="space-y-6">
            <div class="inline-flex rounded-lg border border-white/10 bg-zinc-900 p-1">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-white text-zinc-950' : 'text-zinc-400 hover:text-white'" @click="activeTab = 'overview'; $nextTick(() => window.dispatchEvent(new CustomEvent('datacrove-chart-resize')))">Overview</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'hits' ? 'bg-white text-zinc-950' : 'text-zinc-400 hover:text-white'" @click="activeTab = 'hits'">Daily hits</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'referrers' ? 'bg-white text-zinc-950' : 'text-zinc-400 hover:text-white'" @click="activeTab = 'referrers'">Referrers</button>
            </div>

            <section class="space-y-8" x-show="activeTab === 'overview'">
                <section class="rounded-md border border-white/10 bg-white/[.04] p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">Last 30 days</h2>
                            <p class="mt-1 text-sm text-zinc-400">Daily total and unique hits</p>
                        </div>
                        <p class="text-sm text-zinc-400">Peak: {{ number_format($maxHits) }}</p>
                    </div>
                    <div class="mt-6 h-80">
                        <canvas data-datacrove-chart data-chart='@json($chartData)' data-total-hits-label="Total hits" data-unique-hits-label="Unique hits"></canvas>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-3">
                    @foreach ([['Device Type', $breakdownStats['device_types']], ['Operating System', $breakdownStats['operating_systems']], ['Browser', $breakdownStats['browsers']]] as [$label, $stats])
                    <div class="rounded-md border border-white/10 bg-white/[.04] p-5">
                        <h2 class="text-lg font-semibold">{{ $label }}</h2>
                        <div class="mt-4 space-y-3">
                            @forelse ($stats as $stat)
                            @php($percent = $summaryStats['total_hits'] > 0 ? min(100, round(($stat->total / $summaryStats['total_hits']) * 100)) : 0)
                            <div>
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="truncate text-zinc-300">{{ $stat->label }}</span>
                                    <span class="font-medium">{{ number_format($stat->total) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-zinc-800">
                                    <div class="h-1.5 rounded-full bg-emerald-400" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                            @empty
                            <p class="text-sm text-zinc-500">No data yet.</p>
                            @endforelse
                        </div>
                    </div>
                    @endforeach
                </section>
            </section>

            <section class="rounded-md border border-white/10 bg-white/[.04] p-5" x-show="activeTab === 'hits'">
                <h2 class="text-lg font-semibold">Daily hits</h2>
                <p class="mt-1 text-sm text-zinc-400">All pixel hits grouped by day</p>
                <div class="mt-4">{{ $dailyHitRecords->links() }}</div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="pb-3">Date</th>
                                <th class="pb-3 text-right">Total hits</th>
                                <th class="pb-3 text-right">Unique hits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($dailyHitRecords as $stat)
                            <tr>
                                <td class="whitespace-nowrap py-3 text-zinc-400">{{ \Carbon\Carbon::parse($stat->hit_date)->format('Y-m-d') }}</td>
                                <td class="py-3 text-right text-zinc-300">{{ number_format($stat->total_hits) }}</td>
                                <td class="py-3 text-right text-zinc-300">{{ number_format($stat->unique_hits) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="py-4 text-zinc-500">No pixel hits recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-md border border-white/10 bg-white/[.04] p-5" x-show="activeTab === 'referrers'">
                <h2 class="text-lg font-semibold">Referrers</h2>
                <p class="mt-1 text-sm text-zinc-400">All pixel hits grouped by referrer</p>
                <div class="mt-4">{{ $referrerStats->links() }}</div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="pb-3">Referrer</th>
                                <th class="pb-3 text-right">Total hits</th>
                                <th class="pb-3 text-right">Unique hits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($referrerStats as $stat)
                            <tr>
                                <td class="max-w-md truncate py-3 text-zinc-300">{{ $stat->ref_url ?: 'Direct / unknown' }}</td>
                                <td class="py-3 text-right text-zinc-300">{{ number_format($stat->total_hits) }}</td>
                                <td class="py-3 text-right text-zinc-300">{{ number_format($stat->unique_hits) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="py-4 text-zinc-500">No referrer data yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    @fluxScripts
    <script>
        (() => {
            const boot = () => {
                if (!window.Chart) {
                    window.setTimeout(boot, 50);
                    return;
                }

                const canvas = document.querySelector('[data-datacrove-chart]');

                if (!canvas) {
                    return;
                }

                const chartData = JSON.parse(canvas.dataset.chart);
                const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600').trim() || '#2563eb';
                const secondary = getComputedStyle(document.documentElement).getPropertyValue('--color-emerald-600').trim() || '#059669';
                const grid = 'rgba(255,255,255,.10)';
                const text = '#d4d4d8';

                if (canvas._datacroveChart) {
                    canvas._datacroveChart.destroy();
                }

                canvas._datacroveChart = new Chart(canvas, {
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
                        }, {
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
                        }],
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

                window.addEventListener('datacrove-chart-resize', () => {
                    canvas._datacroveChart.resize();
                });
            };

            boot();
        })();
    </script>
</body>

</html>
