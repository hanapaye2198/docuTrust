let docuTrustStatusChart = null;
let docuTrustActivityChart = null;
let docuTrustSignerTrendChart = null;
let docuTrustSigningMethodChart = null;
const docuTrustAnalyticsCharts = {};
let chartModulePromise = null;
let idleSessionModulePromise = null;
let templatePrepareModulePromise = null;
let signViewModulePromise = null;
let notaryStatusPollModulePromise = null;
let signRequestNotificationsModulePromise = null;

function destroyDocuTrustChart() {
    if (docuTrustStatusChart) {
        docuTrustStatusChart.destroy();
        docuTrustStatusChart = null;
    }
    if (docuTrustActivityChart) {
        docuTrustActivityChart.destroy();
        docuTrustActivityChart = null;
    }
    if (docuTrustSignerTrendChart) {
        docuTrustSignerTrendChart.destroy();
        docuTrustSignerTrendChart = null;
    }
    if (docuTrustSigningMethodChart) {
        docuTrustSigningMethodChart.destroy();
        docuTrustSigningMethodChart = null;
    }
}

function loadChartModule() {
    chartModulePromise ??= import('chart.js/auto');

    return chartModulePromise;
}

window.docuTrustLoadChartModule = loadChartModule;

function loadIdleSessionModule() {
    idleSessionModulePromise ??= import('./idle-session');

    return idleSessionModulePromise;
}

function loadTemplatePrepareModule() {
    templatePrepareModulePromise ??= import('./template-prepare');

    return templatePrepareModulePromise;
}

function loadSignViewModule() {
    signViewModulePromise ??= import('./sign-view');

    return signViewModulePromise;
}

function loadNotaryStatusPollModule() {
    notaryStatusPollModulePromise ??= import('./notary-status-poll');

    return notaryStatusPollModulePromise;
}

function loadSignRequestNotificationsModule() {
    signRequestNotificationsModulePromise ??= import('./sign-request-notifications');

    return signRequestNotificationsModulePromise;
}

function hideDocuTrustChartFallback(id) {
    document.querySelectorAll(`[data-chart-fallback="${id}"]`).forEach((element) => {
        element.classList.add('hidden');
    });
}

async function initDocuTrustDashboardChart() {
    const canvas = document.getElementById('docutrust-status-pie');
    if (!canvas?.dataset?.chart) {
        destroyDocuTrustChart();
        return;
    }

    let payload;
    try {
        payload = JSON.parse(canvas.dataset.chart);
    } catch {
        return;
    }

    if (!payload?.labels?.length || !payload?.values) {
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    destroyDocuTrustChart();

    const ctx = canvas.getContext('2d');
    docuTrustStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: payload.labels,
            datasets: [
                {
                    data: payload.values,
                    backgroundColor: payload.colors,
                    borderColor: document.documentElement.classList.contains('dark')
                        ? 'rgba(24, 24, 27, 0.8)'
                        : 'rgba(255, 255, 255, 0.95)',
                    borderWidth: 2,
                    hoverOffset: 8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '62%',
            animation: {
                animateScale: true,
                duration: 700,
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    titleColor: document.documentElement.classList.contains('dark') ? '#f4f4f5' : '#18181b',
                    bodyColor: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#27272a',
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw ?? 0);
                            const values = payload.values.map((item) => Number(item ?? 0));
                            const total = values.reduce((sum, item) => sum + item, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;

                            return `${context.label}: ${value} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

async function initDocuTrustActivityChart() {
    const canvas = document.getElementById('docutrust-activity-chart');
    if (!canvas?.dataset?.chart) {
        if (docuTrustActivityChart) {
            docuTrustActivityChart.destroy();
            docuTrustActivityChart = null;
        }
        return;
    }

    let payload;
    try {
        payload = JSON.parse(canvas.dataset.chart);
    } catch {
        return;
    }

    if (!payload?.labels?.length || !payload?.weeklyValues || !payload?.monthlyValues) {
        return;
    }

    const activityTotal = [...payload.weeklyValues, ...payload.monthlyValues]
        .map((item) => Number(item ?? 0))
        .reduce((sum, item) => sum + item, 0);
    if (activityTotal <= 0) {
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    if (docuTrustActivityChart) {
        docuTrustActivityChart.destroy();
        docuTrustActivityChart = null;
    }

    hideDocuTrustChartFallback('docutrust-activity-chart');

    const ctx = canvas.getContext('2d');
    docuTrustActivityChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Created / month',
                    data: payload.monthlyValues,
                    backgroundColor: 'rgba(20, 184, 166, 0.35)',
                    borderColor: 'rgba(13, 148, 136, 0.9)',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    type: 'line',
                    label: 'Avg / week',
                    data: payload.weeklyValues,
                    borderColor: 'rgba(99, 102, 241, 0.95)',
                    backgroundColor: 'rgba(99, 102, 241, 0.25)',
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 4,
                    fill: false,
                    yAxisID: 'y',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#27272a',
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#a1a1aa' : '#52525b',
                    },
                    grid: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: document.documentElement.classList.contains('dark') ? '#a1a1aa' : '#52525b',
                    },
                    grid: {
                        color: document.documentElement.classList.contains('dark')
                            ? 'rgba(63, 63, 70, 0.45)'
                            : 'rgba(228, 228, 231, 0.8)',
                    },
                },
            },
        },
    });
}

async function initDocuTrustSignerTrendChart() {
    const canvas = document.getElementById('docutrust-signer-trend-chart');
    if (!canvas?.dataset?.chart) {
        if (docuTrustSignerTrendChart) {
            docuTrustSignerTrendChart.destroy();
            docuTrustSignerTrendChart = null;
        }
        return;
    }

    let payload;
    try {
        payload = JSON.parse(canvas.dataset.chart);
    } catch {
        return;
    }

    if (!payload?.labels?.length || !payload?.completedValues || !payload?.pendingValues) {
        return;
    }

    const trendTotal = [...payload.completedValues, ...payload.pendingValues]
        .map((item) => Number(item ?? 0))
        .reduce((sum, item) => sum + item, 0);
    if (trendTotal <= 0) {
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    if (docuTrustSignerTrendChart) {
        docuTrustSignerTrendChart.destroy();
        docuTrustSignerTrendChart = null;
    }

    hideDocuTrustChartFallback('docutrust-signer-trend-chart');

    const isDark = document.documentElement.classList.contains('dark');
    docuTrustSignerTrendChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: payload.labels,
            datasets: [
                {
                    label: 'Completed',
                    data: payload.completedValues,
                    borderColor: 'rgba(16, 185, 129, 0.95)',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                },
                {
                    label: 'New pending',
                    data: payload.pendingValues,
                    borderColor: 'rgba(245, 158, 11, 0.95)',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: isDark ? '#e4e4e7' : '#27272a',
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: isDark ? '#a1a1aa' : '#52525b' },
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: isDark ? '#a1a1aa' : '#52525b',
                    },
                    grid: {
                        color: isDark ? 'rgba(63, 63, 70, 0.45)' : 'rgba(228, 228, 231, 0.8)',
                    },
                },
            },
        },
    });
}

async function initDocuTrustSigningMethodChart() {
    const canvas = document.getElementById('docutrust-signing-method-chart');
    if (!canvas?.dataset?.chart) {
        if (docuTrustSigningMethodChart) {
            docuTrustSigningMethodChart.destroy();
            docuTrustSigningMethodChart = null;
        }
        return;
    }

    let payload;
    try {
        payload = JSON.parse(canvas.dataset.chart);
    } catch {
        return;
    }

    if (!payload?.labels?.length || !payload?.values) {
        return;
    }

    const methodTotal = payload.values
        .map((item) => Number(item ?? 0))
        .reduce((sum, item) => sum + item, 0);
    if (methodTotal <= 0) {
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    if (docuTrustSigningMethodChart) {
        docuTrustSigningMethodChart.destroy();
        docuTrustSigningMethodChart = null;
    }

    hideDocuTrustChartFallback('docutrust-signing-method-chart');

    const isDark = document.documentElement.classList.contains('dark');
    docuTrustSigningMethodChart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: payload.labels,
            datasets: [
                {
                    data: payload.values,
                    backgroundColor: payload.colors,
                    borderColor: isDark ? 'rgba(24, 24, 27, 0.8)' : 'rgba(255, 255, 255, 0.95)',
                    borderWidth: 2,
                    hoverOffset: 8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '64%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw ?? 0);
                            const values = payload.values.map((item) => Number(item ?? 0));
                            const total = values.reduce((sum, item) => sum + item, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;

                            return `${context.label}: ${value} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

function destroyDocuTrustAnalyticsChart(id) {
    if (docuTrustAnalyticsCharts[id]) {
        docuTrustAnalyticsCharts[id].destroy();
        delete docuTrustAnalyticsCharts[id];
    }
}

function parseDocuTrustChartPayload(canvas) {
    if (!canvas?.dataset?.chart) {
        return null;
    }

    try {
        return JSON.parse(canvas.dataset.chart);
    } catch {
        return null;
    }
}

function docuTrustChartIsDark() {
    return document.documentElement.classList.contains('dark');
}

function docuTrustChartThemeColors() {
    return {
        grid: docuTrustChartIsDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
        tick: docuTrustChartIsDark() ? '#6B7280' : '#9CA3AF',
        bg: docuTrustChartIsDark() ? '#1F2937' : '#ffffff',
        title: docuTrustChartIsDark() ? '#F9FAFB' : '#111827',
        body: docuTrustChartIsDark() ? '#D1D5DB' : '#374151',
        border: docuTrustChartIsDark() ? '#374151' : '#E5E7EB',
    };
}

async function initDocuTrustEarningsChart() {
    const canvas = document.getElementById('earningsChart');
    const payload = parseDocuTrustChartPayload(canvas);
    if (!payload?.labels?.length || !payload?.values) {
        destroyDocuTrustAnalyticsChart('earnings');
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    destroyDocuTrustAnalyticsChart('earnings');
    const theme = docuTrustChartThemeColors();

    docuTrustAnalyticsCharts.earnings = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Earnings (₱)',
                data: payload.values,
                backgroundColor(context) {
                    const gradient = context.chart.ctx.createLinearGradient(0, 0, 0, 200);
                    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.85)');
                    gradient.addColorStop(1, 'rgba(139, 92, 246, 0.2)');

                    return gradient;
                },
                borderColor: '#8B5CF6',
                borderWidth: 0,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
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
                        label: (context) => ` ₱${context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`,
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
                        callback: (value) => `₱${value >= 1000 ? `${(value / 1000).toFixed(0)}k` : value}`,
                    },
                },
            },
        },
    });
}

async function initDocuTrustStatusDonutChart() {
    const canvas = document.getElementById('statusDonutChart');
    const payload = parseDocuTrustChartPayload(canvas);
    const filtered = Array.isArray(payload) ? payload.filter((item) => Number(item.count) > 0) : [];
    if (filtered.length === 0) {
        destroyDocuTrustAnalyticsChart('donut');
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    destroyDocuTrustAnalyticsChart('donut');
    const theme = docuTrustChartThemeColors();

    docuTrustAnalyticsCharts.donut = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: filtered.map((item) => item.label),
            datasets: [{
                data: filtered.map((item) => item.count),
                backgroundColor: filtered.map((item) => item.color),
                borderColor: docuTrustChartIsDark() ? '#1F2937' : '#ffffff',
                borderWidth: 3,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
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

async function initDocuTrustByTypeChart() {
    const canvas = document.getElementById('byTypeChart');
    const payload = parseDocuTrustChartPayload(canvas);
    if (!payload?.labels?.length || !payload?.values) {
        destroyDocuTrustAnalyticsChart('byType');
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    destroyDocuTrustAnalyticsChart('byType');
    const theme = docuTrustChartThemeColors();
    const colors = ['#8B5CF6', '#6366F1', '#14B8A6', '#F59E0B', '#10B981', '#3B82F6'];

    docuTrustAnalyticsCharts.byType = new Chart(canvas.getContext('2d'), {
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
            maintainAspectRatio: true,
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

async function initDocuTrustCompletionChart() {
    const canvas = document.getElementById('completionTimeChart');
    const payload = parseDocuTrustChartPayload(canvas);
    if (!payload?.labels?.length || !payload?.values) {
        destroyDocuTrustAnalyticsChart('completion');
        return;
    }

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    destroyDocuTrustAnalyticsChart('completion');
    const theme = docuTrustChartThemeColors();

    docuTrustAnalyticsCharts.completion = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Avg. days',
                data: payload.values,
                borderColor: '#14B8A6',
                backgroundColor: 'rgba(20, 184, 166, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#14B8A6',
                pointBorderColor: docuTrustChartIsDark() ? '#1F2937' : '#fff',
                pointBorderWidth: 2,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
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

function initDocuTrustAnalyticsCharts() {
    void initDocuTrustEarningsChart();
    void initDocuTrustStatusDonutChart();
    void initDocuTrustByTypeChart();
    void initDocuTrustCompletionChart();
}

function scheduleDocuTrustAnalyticsCharts() {
    if (window.docuTrustUseDedicatedNotaryAnalytics) {
        return;
    }

    initDocuTrustAnalyticsCharts();
    requestAnimationFrame(initDocuTrustAnalyticsCharts);
    window.setTimeout(initDocuTrustAnalyticsCharts, 250);
}

window.docuTrustBootAnalyticsCharts = scheduleDocuTrustAnalyticsCharts;

async function initTemplatePreparePage() {
    const hasPrepareConfig = Boolean(document.getElementById('template-prepare-config'));

    if (!hasPrepareConfig && !templatePrepareModulePromise) {
        return;
    }

    const { initTemplatePreparePage: initPage } = await loadTemplatePrepareModule();
    initPage();
}

async function initIdleSession() {
    if (
        typeof window.APP_IDLE_CONFIG === 'undefined'
        && !document.getElementById('idle-timeout-overlay')
        && !idleSessionModulePromise
    ) {
        return;
    }

    const { initIdleSession: initSession } = await loadIdleSessionModule();
    initSession();
}

async function initSignView() {
    const hasSignViewConfig = Boolean(document.getElementById('sign-view-config'));

    if (!hasSignViewConfig && !signViewModulePromise) {
        return;
    }

    const { initSignView: initView } = await loadSignViewModule();
    initView();
}

async function initNotaryStatusPoll() {
    const hasNotaryStatusConfig = Boolean(document.getElementById('notary-status-config'));

    if (!hasNotaryStatusConfig && !notaryStatusPollModulePromise) {
        return;
    }

    const { initNotaryStatusPoll: initPoll } = await loadNotaryStatusPollModule();
    initPoll();
}

async function initSignRequestNotifications() {
    const hasSignRequestNotificationsConfig = Boolean(document.getElementById('sign-request-notifications-config'));

    if (!hasSignRequestNotificationsConfig && !signRequestNotificationsModulePromise) {
        return;
    }

    const { initSignRequestNotifications: initNotifications } = await loadSignRequestNotificationsModule();
    initNotifications();
}

function bootDocuTrustUi() {
    void initDocuTrustDashboardChart();
    void initDocuTrustActivityChart();
    void initDocuTrustSignerTrendChart();
    void initDocuTrustSigningMethodChart();
    scheduleDocuTrustAnalyticsCharts();
    void initTemplatePreparePage();
    void initIdleSession();
    void initSignView();
    void initNotaryStatusPoll();
    void initSignRequestNotifications();
}

document.addEventListener('DOMContentLoaded', bootDocuTrustUi);
document.addEventListener('livewire:navigated', bootDocuTrustUi);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
