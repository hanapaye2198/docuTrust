import Chart from 'chart.js/auto';

const analyticsCharts = {};

function destroyChart(id) {
    if (analyticsCharts[id]) {
        analyticsCharts[id].destroy();
        delete analyticsCharts[id];
    }
}

function parsePayload(canvas) {
    if (!canvas?.dataset?.chart) {
        return null;
    }

    try {
        return JSON.parse(canvas.dataset.chart);
    } catch {
        return null;
    }
}

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function themeColors() {
    return {
        grid: isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
        tick: isDark() ? '#9CA3AF' : '#6B7280',
        bg: isDark() ? '#1F2937' : '#ffffff',
        title: isDark() ? '#F9FAFB' : '#111827',
        body: isDark() ? '#D1D5DB' : '#374151',
        border: isDark() ? '#374151' : '#E5E7EB',
    };
}

function prepareCanvas(id) {
    const canvas = document.getElementById(id);

    if (!canvas) {
        destroyChart(id);
        return null;
    }

    Chart.getChart(canvas)?.destroy();
    destroyChart(id);

    return canvas;
}

function hideFallback(id) {
    document.querySelector(`[data-chart-fallback="${id}"]`)?.classList.add('hidden');
}

function initEarningsChart() {
    const canvas = prepareCanvas('earningsChart');
    const payload = parsePayload(canvas);

    if (!canvas || !payload?.labels?.length || !payload?.values) {
        return;
    }

    const theme = themeColors();
    hideFallback('earningsChart');

    analyticsCharts.earningsChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Earnings (PHP)',
                data: payload.values,
                backgroundColor: 'rgba(139, 92, 246, 0.55)',
                borderColor: '#8B5CF6',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: theme.bg,
                    titleColor: theme.title,
                    bodyColor: theme.body,
                    borderColor: theme.border,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` PHP ${context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: theme.tick, font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    border: { display: false },
                    ticks: {
                        color: theme.tick,
                        font: { size: 11 },
                        callback: (value) => `PHP ${value >= 1000 ? `${(value / 1000).toFixed(0)}k` : value}`,
                    },
                },
            },
        },
    });
}

function initStatusDonutChart() {
    const canvas = prepareCanvas('statusDonutChart');
    const payload = parsePayload(canvas);
    const filtered = Array.isArray(payload) ? payload.filter((item) => Number(item.count) > 0) : [];

    if (!canvas || filtered.length === 0) {
        return;
    }

    const theme = themeColors();
    hideFallback('statusDonutChart');

    analyticsCharts.statusDonutChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: filtered.map((item) => item.label),
            datasets: [{
                data: filtered.map((item) => item.count),
                backgroundColor: filtered.map((item) => item.color),
                borderColor: isDark() ? '#111827' : '#ffffff',
                borderWidth: 3,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: theme.bg,
                    titleColor: theme.title,
                    bodyColor: theme.body,
                    borderColor: theme.border,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` ${context.label}: ${context.parsed} cases`,
                    },
                },
            },
        },
    });
}

function initByTypeChart() {
    const canvas = prepareCanvas('byTypeChart');
    const payload = parsePayload(canvas);

    if (!canvas || !payload?.labels?.length || !payload?.values) {
        return;
    }

    const theme = themeColors();
    const colors = ['#8B5CF6', '#6366F1', '#14B8A6', '#F59E0B', '#10B981', '#3B82F6'];
    hideFallback('byTypeChart');

    analyticsCharts.byTypeChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Cases',
                data: payload.values,
                backgroundColor: colors.map((color) => `${color}CC`),
                borderColor: colors,
                borderWidth: 0,
                borderRadius: 4,
                borderSkipped: false,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: theme.bg,
                    titleColor: theme.title,
                    bodyColor: theme.body,
                    borderColor: theme.border,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` ${context.parsed.x} case${context.parsed.x !== 1 ? 's' : ''}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    border: { display: false },
                    ticks: { color: theme.tick, font: { size: 11 }, precision: 0 },
                },
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: theme.tick,
                        font: { size: 11 },
                        callback: (_value, index) => {
                            const label = payload.labels[index] ?? '';

                            return label.length > 16 ? `${label.substring(0, 14)}...` : label;
                        },
                    },
                },
            },
        },
    });
}

function initCompletionChart() {
    const canvas = prepareCanvas('completionTimeChart');
    const payload = parsePayload(canvas);

    if (!canvas || !payload?.labels?.length || !payload?.values) {
        return;
    }

    const theme = themeColors();
    hideFallback('completionTimeChart');

    analyticsCharts.completionTimeChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Avg. days',
                data: payload.values,
                borderColor: '#14B8A6',
                backgroundColor: 'rgba(20, 184, 166, 0.12)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#14B8A6',
                pointBorderColor: isDark() ? '#111827' : '#fff',
                pointBorderWidth: 2,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: theme.bg,
                    titleColor: theme.title,
                    bodyColor: theme.body,
                    borderColor: theme.border,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` ${context.parsed.y} day${context.parsed.y !== 1 ? 's' : ''} avg`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: theme.tick, font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    border: { display: false },
                    ticks: {
                        color: theme.tick,
                        font: { size: 11 },
                        callback: (value) => `${value}d`,
                    },
                },
            },
        },
    });
}

function initAnalyticsCharts() {
    initEarningsChart();
    initStatusDonutChart();
    initByTypeChart();
    initCompletionChart();
}

function scheduleAnalyticsCharts() {
    initAnalyticsCharts();
    requestAnimationFrame(initAnalyticsCharts);
    window.setTimeout(initAnalyticsCharts, 250);
    window.setTimeout(initAnalyticsCharts, 750);
}

window.docuTrustUseDedicatedNotaryAnalytics = true;
window.docuTrustBootNotaryAnalyticsCharts = scheduleAnalyticsCharts;

scheduleAnalyticsCharts();
document.addEventListener('DOMContentLoaded', scheduleAnalyticsCharts);
document.addEventListener('livewire:navigated', scheduleAnalyticsCharts);
