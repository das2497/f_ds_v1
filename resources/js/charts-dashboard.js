import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

document.addEventListener('DOMContentLoaded', function () {
    var BRAND_BLUE = '#2563EB';
    var BRAND_BLUE_FILL = 'rgba(37, 99, 235, 0.08)';

    function sparkline(canvasId, labels, data, color, fill) {
        var el = document.getElementById(canvasId);
        if (!el) return;
        new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    borderColor: color,
                    backgroundColor: fill,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: color,
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 11 },
                        displayColors: false,
                    },
                },
                scales: {
                    x: { display: false },
                    y: { display: false },
                },
            },
        });
    }

    var trend = window.dashboardTrend || { labels: [], sales: [], orders: [], sessions: [] };

    sparkline('chartSales', trend.labels, trend.sales, BRAND_BLUE, BRAND_BLUE_FILL);
    sparkline('chartOrders', trend.labels, trend.orders, BRAND_BLUE, BRAND_BLUE_FILL);
    sparkline('chartSessions', trend.labels, trend.sessions, '#64748B', 'rgba(100,116,139,0.08)');
});
