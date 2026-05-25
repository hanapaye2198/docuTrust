let docuTrustStatusChart = null;
let docuTrustActivityChart = null;
let chartModulePromise = null;
let idleSessionModulePromise = null;
let templatePrepareModulePromise = null;
let signViewModulePromise = null;
let notaryStatusPollModulePromise = null;

function destroyDocuTrustChart() {
    if (docuTrustStatusChart) {
        docuTrustStatusChart.destroy();
        docuTrustStatusChart = null;
    }
    if (docuTrustActivityChart) {
        docuTrustActivityChart.destroy();
        docuTrustActivityChart = null;
    }
}

function loadChartModule() {
    chartModulePromise ??= import('chart.js/auto');

    return chartModulePromise;
}

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

    const { default: Chart } = await loadChartModule();
    if (!canvas.isConnected) {
        return;
    }

    if (docuTrustActivityChart) {
        docuTrustActivityChart.destroy();
        docuTrustActivityChart = null;
    }

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

function bootDocuTrustUi() {
    void initDocuTrustDashboardChart();
    void initDocuTrustActivityChart();
    void initTemplatePreparePage();
    void initIdleSession();
    void initSignView();
    void initNotaryStatusPoll();
}

document.addEventListener('DOMContentLoaded', bootDocuTrustUi);
document.addEventListener('livewire:navigated', bootDocuTrustUi);
