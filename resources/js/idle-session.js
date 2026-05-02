/**
 * DocuTrust idle session tracker (depends on window.APP_IDLE_CONFIG from partials/idle-session.blade.php).
 */
const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'];
const TICK_MS = 1000;

let lastActivityAt = Date.now();
let tickTimer = null;
let logoutInProgress = false;
let warningVisible = false;
let teardown = null;

function resolveContextFromPath() {
    const pathname = window.location.pathname || '/';
    const segments = pathname.replace(/^\/+/, '');
    if (
        segments.startsWith('onboarding')
        || segments.startsWith('verify-email')
        || segments.includes('mfa-setup')
    ) {
        return 'onboarding';
    }

    return 'app';
}

function syncContext() {
    const ctx = resolveContextFromPath();
    window.APP_CONTEXT = ctx;

    return ctx;
}

function getLimits() {
    const ctx = syncContext();
    const cfg = window.APP_IDLE_CONFIG?.[ctx];
    if (! cfg) {
        return { idleMs: 20 * 60 * 1000, warningMs: 19 * 60 * 1000 };
    }

    return { idleMs: cfg.idleMs, warningMs: cfg.warningMs };
}

function getOverlay() {
    return document.getElementById('idle-timeout-overlay');
}

function getCountdownEl() {
    return document.getElementById('idle-timeout-countdown');
}

function showWarning(secondsRemaining) {
    const overlay = getOverlay();
    if (! overlay) {
        return;
    }

    warningVisible = true;
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');

    const n = Math.max(0, Math.ceil(secondsRemaining));
    const el = getCountdownEl();
    if (el) {
        el.textContent = String(n);
    }
}

function hideWarning() {
    const overlay = getOverlay();
    if (! overlay) {
        return;
    }

    warningVisible = false;
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

async function performLogout() {
    if (logoutInProgress) {
        return;
    }
    logoutInProgress = true;

    if (tickTimer !== null) {
        window.clearInterval(tickTimer);
        tickTimer = null;
    }

    hideWarning();

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    try {
        const body = new URLSearchParams();
        body.append('_token', token);

        await fetch('/logout', {
            method: 'POST',
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
            credentials: 'same-origin',
        });
    } catch {
        /* redirect regardless */
    }

    window.location.assign('/login');
}

function resetIdleTimer() {
    lastActivityAt = Date.now();
    hideWarning();
}

function onActivity() {
    resetIdleTimer();
}

function tick() {
    const { idleMs, warningMs } = getLimits();
    const now = Date.now();
    const idleFor = now - lastActivityAt;

    if (idleFor >= idleMs) {
        performLogout();

        return;
    }

    if (idleFor >= warningMs) {
        const secondsToLogout = Math.ceil((idleMs - idleFor) / 1000);
        showWarning(secondsToLogout);

        const el = getCountdownEl();
        if (el) {
            el.textContent = String(Math.max(0, secondsToLogout));
        }
    } else if (warningVisible) {
        hideWarning();
    }
}

export function initIdleSession() {
    teardown?.();
    teardown = null;

    if (typeof window.APP_IDLE_CONFIG === 'undefined') {
        return;
    }

    lastActivityAt = Date.now();
    logoutInProgress = false;
    hideWarning();

    const controller = new AbortController();
    const opts = { capture: true, passive: true, signal: controller.signal };

    for (const evt of ACTIVITY_EVENTS) {
        window.addEventListener(evt, onActivity, opts);
    }

    tickTimer = window.setInterval(tick, TICK_MS);

    const stayBtn = document.getElementById('idle-timeout-stay');
    const logoutBtn = document.getElementById('idle-timeout-logout');

    stayBtn?.addEventListener(
        'click',
        () => {
            resetIdleTimer();
        },
        { signal: controller.signal },
    );

    logoutBtn?.addEventListener(
        'click',
        () => {
            performLogout();
        },
        { signal: controller.signal },
    );

    teardown = () => {
        controller.abort();
        if (tickTimer !== null) {
            window.clearInterval(tickTimer);
            tickTimer = null;
        }
    };
}
