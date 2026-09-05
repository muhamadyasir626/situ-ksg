import Chart from 'chart.js/auto';

const loaderMinimumDuration = 280;
let loaderShownAt = performance.now();
let loaderHideTimer;
let initialPageLoaded = document.readyState === 'complete';

const loadingScreen = () => document.getElementById('app-loading-screen');

const showLoadingScreen = () => {
    clearTimeout(loaderHideTimer);
    loaderShownAt = performance.now();
    loadingScreen()?.classList.remove('is-hidden');
};

const hideLoadingScreen = () => {
    const delay = Math.max(0, loaderMinimumDuration - (performance.now() - loaderShownAt));

    clearTimeout(loaderHideTimer);
    loaderHideTimer = setTimeout(() => loadingScreen()?.classList.add('is-hidden'), delay);
};

if (initialPageLoaded) {
    hideLoadingScreen();
} else {
    window.addEventListener('load', () => {
        initialPageLoaded = true;
        hideLoadingScreen();
    }, { once: true });
}

document.addEventListener('livewire:navigating', showLoadingScreen);
document.addEventListener('livewire:navigated', () => {
    if (initialPageLoaded) {
        hideLoadingScreen();
    }
});

document.addEventListener('alpine:init', () => {
    Alpine.data('trendChart', (initial) => ({
        init() {
            // Keep the Chart.js instance out of Alpine's reactive `this` — Alpine wraps
            // reactive properties in a Proxy, which fights Chart.js's own internal data
            // Proxy and causes "Maximum call stack size exceeded" once the chart updates.
            const chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: this.toChartData(initial),
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: (value) => `${value}%` },
                        },
                    },
                },
            });

            this.$wire.on('trend-updated', (payload) => {
                chart.data = this.toChartData(payload[0] ?? payload);
                chart.update();
            });
        },

        toChartData(data) {
            return {
                labels: data.labels,
                datasets: [{
                    label: 'Capaian (%)',
                    data: data.data,
                    borderColor: '#087a72',
                    backgroundColor: 'rgba(8, 122, 114, 0.15)',
                    tension: 0.3,
                    fill: true,
                }],
            };
        },
    }));

    // Bar chart berkelompok (Fase 22) — capaian per bulan, satu seri per kelompok
    // indikator (IKU/IKP/IKK/RO). Warna dari palet SITU-KSG yang sudah ada (§13.1),
    // bukan palet baru: teal, navy, warn, muted.
    Alpine.data('monthlyBarChart', (initial) => ({
        init() {
            const chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: this.toChartData(initial),
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: (value) => `${value}%` },
                        },
                    },
                },
            });

            this.$wire.on('monthly-chart-updated', (payload) => {
                chart.data = this.toChartData(payload[0] ?? payload);
                chart.update();
            });
        },

        toChartData(data) {
            const colors = ['#087a72', '#123141', '#ad7200', '#6a7779'];

            return {
                labels: data.labels,
                datasets: data.datasets.map((dataset, index) => ({
                    label: dataset.label,
                    data: dataset.data,
                    backgroundColor: colors[index % colors.length],
                })),
            };
        },
    }));
});
