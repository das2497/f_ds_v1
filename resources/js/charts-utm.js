/* UTM Attribution page charts — Chart.js. Reads window.utmChartData (server-rendered). */
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, ChartDataLabels);

function cssVar(name, fallback) {
  var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

document.addEventListener("DOMContentLoaded", function () {
  var data = window.utmChartData || { funnel: {}, topCampaigns: [] };
  var headingColor = cssVar('--color-heading', '#111827');
  var mutedColor = cssVar('--color-text-muted', '#64748B');

  // ---------- Conversion Funnel (ordinal blue ramp — lightest to darkest = earliest to final stage) ----------
  var funnelEl = document.getElementById("chartFunnel");
  if (funnelEl) {
    var funnelStages = Object.keys(data.funnel);
    var funnelValues = Object.values(data.funnel);
    var funnelColors = ["#BFDBFE", "#60A5FA", "#2563EB"];

    new Chart(funnelEl, {
      type: "bar",
      data: {
        labels: funnelStages,
        datasets: [{
          data: funnelValues,
          backgroundColor: funnelColors,
          borderRadius: 4,
          barThickness: 36
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
              label: function (ctx) {
                var pct = funnelValues[0] > 0 ? ((ctx.raw / funnelValues[0]) * 100).toFixed(1) : "0.0";
                return ctx.raw.toLocaleString() + " (" + pct + "% of sessions)";
              }
            }
          },
          datalabels: {
            anchor: "end",
            align: "right",
            color: headingColor,
            font: { weight: "700", size: 12 },
            formatter: function (value, ctx) {
              var pct = ctx.dataIndex === 0 || funnelValues[0] === 0 ? "" : " (" + ((value / funnelValues[0]) * 100).toFixed(1) + "%)";
              return value.toLocaleString() + pct;
            }
          }
        },
        scales: {
          x: { display: false, grace: "25%" },
          y: { grid: { display: false }, ticks: { color: headingColor, font: { weight: "600" } } }
        }
      }
    });
  }

  // ---------- Top Campaigns by Purchases (categorical — validated order: blue, orange, aqua, yellow) ----------
  var campaignEl = document.getElementById("chartCampaigns");
  if (campaignEl) {
    var palette = ["#2563EB", "#eb6834", "#1baf7a", "#eda100"];

    new Chart(campaignEl, {
      type: "bar",
      data: {
        labels: data.topCampaigns.map(function (c) { return c.campaign + "\n(" + c.source + "/" + c.medium + ")"; }),
        datasets: [{
          data: data.topCampaigns.map(function (c) { return c.purchases; }),
          backgroundColor: data.topCampaigns.map(function (_, i) { return palette[i % palette.length]; }),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: "#111827" },
          datalabels: {
            anchor: "end",
            align: "top",
            color: headingColor,
            font: { weight: "700", size: 12 },
            formatter: function (value) { return value; }
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: mutedColor, font: { size: 10 } } },
          y: { display: false, grace: "20%" }
        }
      }
    });
  }
});
