import { Chart, BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const instances = [];

function palette() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    return {
        grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)',
        text: dark ? '#9ca3af' : '#6b7280',
        series: dark ? ['#818cf8', '#38bdf8', '#34d399', '#fbbf24'] : ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b'],
    };
}

function build(el) {
    // Data comes from a JSON <script> next to the canvas (no inline JS; CSP-safe).
    const source = document.getElementById(el.getAttribute('data-chart'));
    if (!source) return;
    const data = JSON.parse(source.textContent);
    const p = palette();
    const type = el.getAttribute('data-chart-type') || 'bar';

    const chart = new Chart(el, {
        type,
        data: {
            labels: data.labels,
            datasets: data.datasets.map((ds, i) => ({
                ...ds,
                backgroundColor: type === 'line' ? p.series[i % 4] + '22' : p.series[i % 4],
                borderColor: p.series[i % 4],
                borderWidth: type === 'line' ? 2 : 0,
                borderRadius: 6,
                fill: type === 'line',
                tension: 0.35,
                pointRadius: 0,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: data.datasets.length > 1, labels: { color: p.text, boxWidth: 12 } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue} ${data.unit || ''}` } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: p.text, maxRotation: 0, autoSkip: true } },
                y: { beginAtZero: true, grid: { color: p.grid }, ticks: { color: p.text } },
            },
        },
    });
    instances.push({ chart, el });
}

export function renderCharts(elements) {
    elements.forEach(build);
    document.addEventListener('theme:changed', () => {
        instances.splice(0).forEach(({ chart, el }) => { chart.destroy(); build(el); });
    });
}
