/**
 * Lightweight AJAX polling for real-time notary request status updates.
 *
 * Usage: Call `initNotaryStatusPoll()` on pages that display notary request status.
 * It reads configuration from a `<script id="notary-status-config">` JSON block.
 *
 * Config shape:
 * {
 *   "requestId": 42,
 *   "statusUrl": "/api/notary-requests/42/status",
 *   "interval": 5000,
 *   "currentStatus": "submitted"
 * }
 */

let activePoll = null;

export function initNotaryStatusPoll() {
    const cfgEl = document.getElementById('notary-status-config');

    if (!cfgEl) {
        activePoll?.stop();
        activePoll = null;
        return;
    }

    if (activePoll?.owns(cfgEl)) {
        return;
    }

    activePoll?.stop();
    activePoll = createStatusPoll(cfgEl);
    activePoll.start();
}

function createStatusPoll(cfgEl) {
    let config;
    try {
        config = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        config = {};
    }

    const statusUrl = config.statusUrl || '';
    const channelName = config.channel || '';
    const interval = Math.max(3000, Number(config.interval) || 5000);
    let currentStatus = config.currentStatus || '';
    let currentPaymentFingerprint = paymentFingerprint(config.latestPayment || null);
    let timerId = null;
    let stopped = false;
    let isPageVisible = true;
    let realtimeHealthy = false;
    let realtimeChannel = null;

    function owns(node) {
        return cfgEl === node;
    }

    function stop() {
        stopped = true;
        if (timerId !== null) {
            clearTimeout(timerId);
            timerId = null;
        }

        if (realtimeChannel && window.Echo) {
            window.Echo.leave(channelName);
            realtimeChannel = null;
        }
    }

    function start() {
        if (!statusUrl) {
            return;
        }

        subscribeToRealtime();

        // Listen for visibility changes to pause/resume polling
        document.addEventListener('visibilitychange', () => {
            isPageVisible = !document.hidden;
            if (isPageVisible && !stopped) {
                schedulePoll(1000); // Quick poll when tab becomes visible again
            }
        });

        schedulePoll(realtimeHealthy ? interval * 6 : interval);
    }

    function schedulePoll(delay) {
        if (stopped) {
            return;
        }

        if (timerId !== null) {
            clearTimeout(timerId);
        }

        timerId = setTimeout(() => {
            if (stopped || !isPageVisible) {
                // If page is hidden, just reschedule for later
                if (!stopped) {
                    schedulePoll(realtimeHealthy ? interval * 6 : interval * 2);
                }
                return;
            }

            fetchStatus();
        }, delay);
    }

    async function fetchStatus() {
        if (stopped) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                // If 401/403, stop polling (session expired)
                if (response.status === 401 || response.status === 403) {
                    stop();
                    return;
                }

                schedulePoll(realtimeHealthy ? interval * 6 : interval * 2); // Back off on errors
                return;
            }

            const data = await response.json();
            processUpdate(data);
        } catch {
            // Network error — back off
            schedulePoll(realtimeHealthy ? interval * 6 : interval * 2);
            return;
        }

        schedulePoll(realtimeHealthy ? interval * 6 : interval);
    }

    function subscribeToRealtime() {
        if (!channelName || !window.Echo?.private) {
            return;
        }

        realtimeChannel = window.Echo.private(channelName)
            .listen('.notary.request.status.updated', (payload) => {
                realtimeHealthy = true;
                processUpdate(payload || {});
            });
    }

    function processUpdate(data) {
        const newStatus = data.status || '';
        const nextPaymentFingerprint = paymentFingerprint(data.payment || null);
        const paymentChanged = nextPaymentFingerprint !== currentPaymentFingerprint;

        // Only update DOM if something actually changed
        if (newStatus === currentStatus && !hasSignerChanges(data) && !paymentChanged) {
            return;
        }

        currentStatus = newStatus;
        currentPaymentFingerprint = nextPaymentFingerprint;

        // Update status badges
        updateStatusBadges(newStatus);

        // Update signer statuses
        updateSignerStatuses(data.signers || []);

        // Update document progress
        updateDocumentProgress(data.documents || []);

        // Update session info
        updateSessionInfo(data.session);

        // Dispatch a custom event for Livewire components to listen to
        window.dispatchEvent(new CustomEvent('notary-status-updated', {
            detail: data,
        }));

        // If status reached a terminal state, slow down polling
        if (['notarized', 'rejected', 'failed', 'cancelled'].includes(newStatus)) {
            stop();
        }
    }

    function hasSignerChanges(data) {
        const signerEls = document.querySelectorAll('[data-signer-status]');
        if (signerEls.length === 0) {
            return false;
        }

        const signers = data.signers || [];
        for (const signer of signers) {
            const el = document.querySelector(`[data-signer-status="${signer.id}"]`);
            if (el && el.textContent.trim().toLowerCase() !== (signer.signing_status || '').toLowerCase()) {
                return true;
            }
        }

        return false;
    }

    function paymentFingerprint(payment) {
        if (!payment || typeof payment !== 'object') {
            return '';
        }

        return [
            payment.id || '',
            payment.status || '',
            payment.reference || '',
            payment.gateway || '',
            payment.paid_at || '',
            payment.expires_at || '',
            payment.last_verified_at || '',
            payment.updated_at || '',
        ].join('|');
    }

    function updateStatusBadges(status) {
        const badges = document.querySelectorAll('[data-notary-status-badge]');
        badges.forEach((badge) => {
            const formatted = status.replace(/_/g, ' ');
            badge.textContent = formatted;
            badge.dataset.notaryStatusBadge = status;

            // Update color classes
            badge.className = badge.className.replace(
                /text-\w+-\d+|bg-\w+-\d+|border-\w+-\d+/g,
                '',
            );

            const colorClass = statusColorClass(status);
            if (colorClass) {
                badge.classList.add(...colorClass.split(' '));
            }
        });
    }

    function updateSignerStatuses(signers) {
        signers.forEach((signer) => {
            // Update signing status
            const signingEl = document.querySelector(`[data-signer-signing-status="${signer.id}"]`);
            if (signingEl) {
                signingEl.textContent = formatSigningStatus(signer.signing_status);
                signingEl.dataset.signerSigningStatus = signer.id;
            }

            // Update identity status
            const identityEl = document.querySelector(`[data-signer-identity-status="${signer.id}"]`);
            if (identityEl && signer.identity_status) {
                identityEl.textContent = signer.identity_status;
            }
        });
    }

    function updateDocumentProgress(documents) {
        documents.forEach((doc) => {
            const progressEl = document.querySelector(`[data-document-progress="${doc.id}"]`);
            if (progressEl) {
                progressEl.textContent = `${doc.signers_signed} / ${doc.signers_total} signed`;
            }

            const docStatusEl = document.querySelector(`[data-document-status="${doc.id}"]`);
            if (docStatusEl) {
                docStatusEl.textContent = doc.status.replace(/_/g, ' ');
            }
        });
    }

    function updateSessionInfo(session) {
        const sessionEl = document.querySelector('[data-notary-session-status]');
        if (!sessionEl || !session) {
            return;
        }

        sessionEl.textContent = session.status || 'scheduled';
    }

    function formatSigningStatus(status) {
        if (!status) {
            return 'Pending';
        }

        return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function statusColorClass(status) {
        switch (status) {
            case 'notarized':
            case 'digitalized':
                return 'text-emerald-700 bg-emerald-50 border-emerald-200';
            case 'rejected':
            case 'failed':
                return 'text-red-700 bg-red-50 border-red-200';
            case 'cancelled':
                return 'text-zinc-500 bg-zinc-50 border-zinc-200';
            case 'session_scheduled':
            case 'session_in_progress':
            case 'session_completed':
                return 'text-sky-700 bg-sky-50 border-sky-200';
            case 'attorney_signing':
            case 'attorney_approved':
                return 'text-indigo-700 bg-indigo-50 border-indigo-200';
            default:
                return 'text-amber-700 bg-amber-50 border-amber-200';
        }
    }

    return { start, stop, owns };
}
