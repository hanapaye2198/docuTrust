import Chart from 'chart.js/auto';
import { initIdleSession } from './idle-session';
import { initTemplatePreparePage } from './template-prepare';

let docuTrustStatusChart = null;
let docuTrustActivityChart = null;

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

function initDocuTrustDashboardChart() {
    const canvas = document.getElementById('docutrust-status-pie');
    if (!canvas?.dataset?.chart) {
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

function initDocuTrustActivityChart() {
    const canvas = document.getElementById('docutrust-activity-chart');
    if (!canvas?.dataset?.chart) {
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

document.addEventListener('DOMContentLoaded', initDocuTrustDashboardChart);
document.addEventListener('livewire:navigated', initDocuTrustDashboardChart);
document.addEventListener('DOMContentLoaded', initDocuTrustActivityChart);
document.addEventListener('livewire:navigated', initDocuTrustActivityChart);

document.addEventListener('DOMContentLoaded', initTemplatePreparePage);
document.addEventListener('livewire:navigated', initTemplatePreparePage);

document.addEventListener('DOMContentLoaded', initIdleSession);
document.addEventListener('livewire:navigated', initIdleSession);
