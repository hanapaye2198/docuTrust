let activeSignRequestNotifications = null;

export function initSignRequestNotifications() {
    const cfgEl = document.getElementById('sign-request-notifications-config');

    if (!cfgEl) {
        activeSignRequestNotifications?.stop();
        activeSignRequestNotifications = null;
        return;
    }

    if (activeSignRequestNotifications?.owns(cfgEl)) {
        return;
    }

    activeSignRequestNotifications?.stop();
    activeSignRequestNotifications = createSignRequestNotifications(cfgEl);
    activeSignRequestNotifications.start();
}

function createSignRequestNotifications(cfgEl) {
    let config;
    try {
        config = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        config = {};
    }

    const userId = Number(config.userId || 0);
    const fallbackUrl = config.signRequestsUrl || '/sign-requests';
    const channelName = userId > 0 ? `App.Models.User.${userId}` : '';
    let channel = null;

    function owns(node) {
        return cfgEl === node;
    }

    function start() {
        if (!channelName || !window.Echo?.private) {
            return;
        }

        channel = window.Echo.private(channelName)
            .listen('.sign.request.received', (payload) => {
                handleSignRequestReceived(payload || {});
            });
    }

    function stop() {
        if (channel && window.Echo) {
            window.Echo.leave(channelName);
            channel = null;
        }
    }

    function handleSignRequestReceived(payload) {
        if (window.Livewire?.dispatch) {
            window.Livewire.dispatch('sign-request-received');
        }

        showToast({
            message: payload.message || 'You have a new sign request.',
            title: payload.title || 'New sign request',
            url: payload.url || fallbackUrl,
        });
    }

    return { owns, start, stop };
}

function showToast(payload) {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = [
        'pointer-events-auto',
        'w-full',
        'max-w-sm',
        'overflow-hidden',
        'rounded-2xl',
        'border',
        'border-teal-200/80',
        'bg-white',
        'p-4',
        'shadow-xl',
        'shadow-zinc-950/10',
        'ring-1',
        'ring-teal-500/10',
        'transition',
        'dark:border-teal-900/60',
        'dark:bg-zinc-900',
        'dark:shadow-black/30',
    ].join(' ');
    toast.setAttribute('role', 'status');

    const title = escapeHtml(payload.title);
    const message = escapeHtml(payload.message);
    const url = escapeAttribute(payload.url);

    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-300">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">${title}</p>
                <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">${message}</p>
                <a href="${url}" wire:navigate class="mt-3 inline-flex text-sm font-semibold text-teal-700 transition hover:text-teal-600 dark:text-teal-300 dark:hover:text-teal-200">View sign requests</a>
            </div>
            <button type="button" class="rounded-lg p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200" aria-label="Dismiss notification">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;

    const removeToast = () => {
        toast.classList.add('opacity-0', 'translate-y-2');
        window.setTimeout(() => toast.remove(), 200);
    };

    toast.querySelector('button')?.addEventListener('click', removeToast);
    container.appendChild(toast);
    window.setTimeout(removeToast, 8000);
}

function ensureToastContainer() {
    let container = document.getElementById('sign-request-toast-container');
    if (container) {
        return container;
    }

    container = document.createElement('div');
    container.id = 'sign-request-toast-container';
    container.className = 'pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6';
    document.body.appendChild(container);

    return container;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttribute(value) {
    const text = String(value ?? '');

    if (!text.startsWith('/') && !text.startsWith(window.location.origin)) {
        return '/sign-requests';
    }

    return escapeHtml(text);
}
