@script
<script>
    window.docutrustGetMainScrollArea = () => document.querySelector('.main-scroll-area');

    window.docutrustResetMainScroll = () => {
        const scrollArea = window.docutrustGetMainScrollArea();

        if (scrollArea) {
            scrollArea.scrollTop = 0;
        }
    };

    window.docutrustScrollToSectionWithRetry = (targetId, scrollMarginTop = 96, shouldReset = false) => {
        if (shouldReset) {
            window.docutrustResetMainScroll();
        }

        let attempts = 0;
        const maxAttempts = 24;
        const timer = window.setInterval(() => {
            attempts += 1;

            const element = document.getElementById(targetId);
            const isVisible = element
                && element.isConnected
                && element.getClientRects().length > 0;

            if (element && isVisible) {
                const scrollArea = window.docutrustGetMainScrollArea();

                if (scrollArea) {
                    const scrollAreaRect = scrollArea.getBoundingClientRect();
                    const elementRect = element.getBoundingClientRect();
                    const top = scrollArea.scrollTop + (elementRect.top - scrollAreaRect.top) - scrollMarginTop;

                    scrollArea.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                } else {
                    const top = element.getBoundingClientRect().top + window.scrollY - scrollMarginTop;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                }

                window.clearInterval(timer);

                return;
            }

            if (attempts >= maxAttempts) {
                window.clearInterval(timer);
            }
        }, 100);
    };

    window.docutrustHighlightSettlementNavTarget = (targetId) => {
        document.querySelectorAll('[data-settlement-nav-target]').forEach((button) => {
            const isActive = button.getAttribute('data-settlement-nav-target') === targetId;
            button.dataset.scrollSpyActive = isActive ? 'true' : 'false';
        });
    };

    window.docutrustInitSettlementScrollSpy = () => {
        const nav = document.querySelector('[data-settlement-sub-nav]');
        if (! nav) {
            return;
        }

        const sectionIds = (nav.getAttribute('data-settlement-section-ids') || '')
            .split(',')
            .map((id) => id.trim())
            .filter((id) => id !== '');

        if (sectionIds.length === 0) {
            return;
        }

        const sections = sectionIds
            .map((id) => document.getElementById(id))
            .filter((element) => element !== null);

        if (sections.length === 0) {
            return;
        }

        if (window.__docutrustSettlementScrollSpy) {
            window.__docutrustSettlementScrollSpy.disconnect();
        }

        const scrollRoot = window.docutrustGetMainScrollArea();

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                if (visible.length === 0) {
                    return;
                }

                window.docutrustHighlightSettlementNavTarget(visible[0].target.id);
            },
            {
                root: scrollRoot,
                rootMargin: '-30% 0px -55% 0px',
                threshold: [0.1, 0.25, 0.5],
            },
        );

        sections.forEach((section) => observer.observe(section));
        window.__docutrustSettlementScrollSpy = observer;
    };

    window.docutrustScrollToPaymentHashTarget = () => {
        if (window.location.hash !== '#section-payment') {
            return;
        }

        window.docutrustScrollToSectionWithRetry('section-payment', 96, true);
    };

    if (! window.__docutrustSettlementScrollListeners) {
        window.__docutrustSettlementScrollListeners = true;

        window.addEventListener('hashchange', window.docutrustScrollToPaymentHashTarget);
        window.addEventListener('reset-main-scroll', window.docutrustResetMainScroll);
        window.addEventListener('scroll-to-section', (event) => {
            const payload = Array.isArray(event?.detail) ? event.detail[0] : event?.detail;
            const targetId = payload?.id;
            if (! targetId) {
                return;
            }

            const shouldReset = payload?.reset === true;
            window.docutrustScrollToSectionWithRetry(targetId, 96, shouldReset);
            window.docutrustHighlightSettlementNavTarget(targetId);
        });

        Livewire.on('reset-main-scroll', () => {
            window.docutrustResetMainScroll();
        });

        Livewire.on('scroll-to-section', (payload) => {
            const targetId = payload?.id;
            if (! targetId) {
                return;
            }

            const shouldReset = payload?.reset === true;
            window.docutrustScrollToSectionWithRetry(targetId, 96, shouldReset);
            window.docutrustHighlightSettlementNavTarget(targetId);
        });

        window.addEventListener('notary-status-updated', () => {
            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('notary-status-updated-livewire');
            }
        });
    }

    window.docutrustInitSettlementScrollSpy();
    window.docutrustScrollToPaymentHashTarget();

    const activeTab = new URL(window.location.href).searchParams.get('tab');

    if (activeTab === 'closing') {
        window.requestAnimationFrame(() => {
            window.docutrustScrollToSectionWithRetry('section-settlement-start', 96, true);
        });
    }
</script>
@endscript

<style>
    [data-settlement-nav-target][data-scroll-spy-active='true'] {
        border-color: rgb(56 189 248);
        background-color: rgb(240 249 255);
        color: rgb(12 74 110);
    }

    :is(.dark) [data-settlement-nav-target][data-scroll-spy-active='true'] {
        border-color: rgb(3 105 161);
        background-color: rgb(8 47 73 / 0.5);
        color: rgb(186 230 253);
    }
</style>
