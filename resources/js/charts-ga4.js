/* Google Analytics (GA4) dashboard charts — Chart.js. Reads window.ga4ChartData (server-rendered). */
import { Chart, LineController, LineElement, PointElement, BarController, BarElement, DoughnutController, ArcElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(LineController, LineElement, PointElement, BarController, BarElement, DoughnutController, ArcElement, LinearScale, CategoryScale, Tooltip, Legend, ChartDataLabels);

function cssVar(name, fallback) {
  var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

document.addEventListener("DOMContentLoaded", function () {
  var BRAND_BLUE = "#2563EB";
  var palette = ["#2563EB", "#eb6834", "#1baf7a", "#eda100", "#8b5cf6", "#ec4899"];
  var data = window.ga4ChartData || { dailyTrend: [], trafficByChannel: [], byDevice: [] };
  var headingColor = cssVar('--color-heading', '#111827');
  var mutedColor = cssVar('--color-text-muted', '#64748B');

  // ---------- Users & Sessions Over Time (line) ----------
  var trendEl = document.getElementById("chartTrend");
  if (trendEl && data.dailyTrend.length) {
    new Chart(trendEl, {
      type: "line",
      data: {
        labels: data.dailyTrend.map(function (d) { return d.date; }),
        datasets: [
          {
            label: "Users",
            data: data.dailyTrend.map(function (d) { return d.totalUsers; }),
            borderColor: BRAND_BLUE,
            backgroundColor: BRAND_BLUE,
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 2
          },
          {
            label: "Sessions",
            data: data.dailyTrend.map(function (d) { return d.sessions; }),
            borderColor: "#eb6834",
            backgroundColor: "#eb6834",
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: "top", labels: { color: headingColor, boxWidth: 10 } },
          tooltip: { backgroundColor: "#111827" },
          datalabels: { display: false }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: mutedColor, font: { size: 10 }, maxRotation: 0, autoSkip: true } },
          y: { grid: { color: "rgba(0,0,0,0.05)" }, ticks: { color: mutedColor } }
        }
      }
    });
  }

  // ---------- Sessions by Device (doughnut) ----------
  var deviceEl = document.getElementById("chartDevice");
  if (deviceEl && data.byDevice.length) {
    new Chart(deviceEl, {
      type: "doughnut",
      data: {
        labels: data.byDevice.map(function (d) { return d.device; }),
        datasets: [{
          data: data.byDevice.map(function (d) { return d.sessions; }),
          backgroundColor: data.byDevice.map(function (_, i) { return palette[i % palette.length]; }),
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: "bottom", labels: { color: headingColor, boxWidth: 10 } },
          tooltip: { backgroundColor: "#111827" },
          datalabels: {
            color: "#fff",
            font: { weight: "700", size: 11 },
            formatter: function (value, ctx) {
              var total = ctx.chart.data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
              return total > 0 ? Math.round((value / total) * 100) + "%" : "";
            }
          }
        }
      }
    });
  }

  // ---------- Traffic by Channel (horizontal bar) ----------
  var channelEl = document.getElementById("chartChannel");
  if (channelEl && data.trafficByChannel.length) {
    new Chart(channelEl, {
      type: "bar",
      data: {
        labels: data.trafficByChannel.map(function (c) { return c.channel; }),
        datasets: [{
          data: data.trafficByChannel.map(function (c) { return c.sessions; }),
          backgroundColor: data.trafficByChannel.map(function (_, i) { return palette[i % palette.length]; }),
          borderRadius: 4,
          barThickness: 22
        }]
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: "#111827" },
          datalabels: {
            anchor: "end",
            align: "right",
            color: headingColor,
            font: { weight: "700", size: 11 },
            formatter: function (value) { return value.toLocaleString(); }
          }
        },
        scales: {
          x: { display: false, grace: "25%" },
          y: { grid: { display: false }, ticks: { color: headingColor, font: { size: 11 } } }
        }
      }
    });
  }

  // ---------- Live Now (polls the realtime endpoint, no processing lag) ----------
  var liveCountEl = document.getElementById("ga4-live-count");
  var liveWrapEl = document.getElementById("ga4-live-now");
  if (liveCountEl && window.ga4RealtimeUrl) {
    var pollLiveNow = function () {
      fetch(window.ga4RealtimeUrl, { headers: { "Accept": "application/json" } })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
          if (!payload.configured || payload.error) {
            return;
          }
          liveCountEl.textContent = payload.activeUsers;
          if (liveWrapEl && payload.topPages && payload.topPages.length) {
            liveWrapEl.title = "Active on: " + payload.topPages.map(function (p) { return p.page + " (" + p.activeUsers + ")"; }).join(", ");
          }
        })
        .catch(function () { /* transient network/API hiccup — keep the last known count */ });
    };

    pollLiveNow();
    setInterval(pollLiveNow, 20000);
  }
});
