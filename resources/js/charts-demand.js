/* Demand & Forecasting page charts — Chart.js. Reads window.demandChartData (server-rendered). */
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, ChartDataLabels);

function cssVar(name, fallback) {
  var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

document.addEventListener("DOMContentLoaded", function () {
  var BRAND_BLUE = "#2563EB";
  var data = window.demandChartData || { trending: [], geo: [] };
  var headingColor = cssVar('--color-heading', '#111827');
  var positiveColor = cssVar('--color-positive', '#006300');

  // ---------- Trending Products (magnitude, sequential blue, ranked) ----------
  var trendingEl = document.getElementById("chartTrending");
  if (trendingEl && data.trending.length) {
    var trendingLabels = data.trending.map(function (t) { return t.title; });
    var trendingScores = data.trending.map(function (t) { return t.score; });
    var trendingGrowth = data.trending.map(function (t) { return (t.growth >= 0 ? "+" : "") + t.growth + "%"; });

    new Chart(trendingEl, {
      type: "bar",
      data: {
        labels: trendingLabels,
        datasets: [{
          data: trendingScores,
          backgroundColor: BRAND_BLUE,
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
          tooltip: {
            backgroundColor: "#111827",
            callbacks: {
              label: function (ctx) { return "Demand score " + ctx.raw + " · " + trendingGrowth[ctx.dataIndex] + " WoW"; }
            }
          },
          datalabels: {
            anchor: "end",
            align: "right",
            color: positiveColor,
            font: { weight: "700", size: 11 },
            formatter: function (value, ctx) { return trendingGrowth[ctx.dataIndex]; }
          }
        },
        scales: {
          x: { display: false, grace: "30%" },
          y: { grid: { display: false }, ticks: { color: headingColor, font: { size: 11 } } }
        }
      }
    });
  }

  // ---------- Demand by State (magnitude, sequential blue) ----------
  var geoEl = document.getElementById("chartGeo");
  if (geoEl && data.geo.length) {
    new Chart(geoEl, {
      type: "bar",
      data: {
        labels: data.geo.map(function (g) { return g.state; }),
        datasets: [{
          data: data.geo.map(function (g) { return g.orders; }),
          backgroundColor: BRAND_BLUE,
          borderRadius: 4,
          barThickness: 20
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
            formatter: function (value) { return value; }
          }
        },
        scales: {
          x: { display: false, grace: "15%" },
          y: { grid: { display: false }, ticks: { color: headingColor, font: { size: 11 } } }
        }
      }
    });
  }
});
