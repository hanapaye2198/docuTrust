@auth
    @php
        $path = request()->path();
        $isOnboardingPath =
            str_starts_with($path, 'onboarding')
            || str_starts_with($path, 'verify-email')
            || str_contains($path, 'mfa-setup');
        $idleContext = $isOnboardingPath ? 'onboarding' : 'app';
        $idleOnboarding = config('docutrust.idle_session.onboarding');
        $idleApp = config('docutrust.idle_session.app');
    @endphp
    <script>
        window.APP_CONTEXT = @json($idleContext);
        window.APP_IDLE_CONFIG = {
            onboarding: {
                idleMs: {{ (int) $idleOnboarding['idle_minutes'] * 60 * 1000 }},
                warningMs: {{ (int) $idleOnboarding['warning_minutes'] * 60 * 1000 }},
            },
            app: {
                idleMs: {{ (int) $idleApp['idle_minutes'] * 60 * 1000 }},
                warningMs: {{ (int) $idleApp['warning_minutes'] * 60 * 1000 }},
            },
        };
    </script>
    <x-idle-session.modal />
@endauth
