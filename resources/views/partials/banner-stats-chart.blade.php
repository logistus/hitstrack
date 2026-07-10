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

            const chartData = JSON.parse(canvas.dataset.chart);
            const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600').trim() || '#2563eb';
            const secondary = getComputedStyle(document.documentElement).getPropertyValue('--color-emerald-600').trim() || '#059669';
            const isDark = document.documentElement.classList.contains('dark');
            const grid = isDark ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.12)';
            const text = isDark ? '#d4d4d8' : '#334155';

            if (canvas._trackerChart) {
                canvas._trackerChart.destroy();
            }

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
