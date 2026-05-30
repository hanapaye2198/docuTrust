<?php

use App\Enums\UserRole;
use App\Models\NotaryRequest;
use App\Models\SignerCertificate;
use App\Services\Attorney\AttorneyDashboardService;
use App\Services\SignerCertificateRevocationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var array<int, string> */
    public array $revocationReasons = [];

    public bool $showCompliance = false;

    public function revokeCertificate(int $certificateId): void
    {
        abort_unless(Auth::user()?->role === UserRole::Notary, 403);

        $certificate = SignerCertificate::query()
            ->whereKey($certificateId)
            ->whereHas('documentSigner.document', fn ($query) => $query->where('organization_id', Auth::user()?->organization_id))
            ->firstOrFail();

        $reason = trim((string) ($this->revocationReasons[$certificateId] ?? ''));

        validator(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:255']]
        )->validate();

        app(SignerCertificateRevocationService::class)->revoke($certificate, $reason);

        $this->revocationReasons[$certificateId] = '';
        session()->flash('status', __('Signer certificate revoked.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(AttorneyDashboardService $dashboard): array
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        return [
            'user' => $user,
            'dashboardData' => $dashboard->dashboardData($user),
            'dashboardService' => $dashboard,
        ];
    }
}; ?>

@php
    use App\Services\TrustProfile\TrustProfileService;

    $eligibility = $dashboardData['eligibility'];
    $metrics = $dashboardData['metrics'];
    $credential = $dashboardData['credential'];
    $readiness = $dashboardData['enotaryReadiness'];
    $certificates = $dashboardData['certificates'];
    $roleLabel = app(TrustProfileService::class)->summary($user)['role_label'];
    $readinessPercent = $readiness['total'] > 0 ? (int) round(($readiness['met'] / $readiness['total']) * 100) : 0;
    $greeting = match (true) {
        now()->hour < 12 => __('Good morning'),
        now()->hour < 17 => __('Good afternoon'),
        default => __('Good evening'),
    };
    $displayName = $user->buildFullName() ?: $user->name;
@endphp

<x-admin.page gap="gap-8">
    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl border border-teal-200/50 bg-gradient-to-br from-teal-50 via-white to-indigo-50/80 p-6 shadow-[0_8px_40px_rgb(20_184_166/0.08)] ring-1 ring-teal-500/10 sm:p-8 dark:border-teal-500/20 dark:from-teal-950/40 dark:via-zinc-900 dark:to-indigo-950/30 dark:shadow-[0_8px_48px_rgb(0_0_0/0.35)] dark:ring-teal-400/10">
        <div class="pointer-events-none absolute -end-16 -top-16 size-56 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-400/15" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-20 start-8 size-48 rounded-full bg-indigo-400/10 blur-3xl dark:bg-indigo-400/10" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700/80 dark:text-teal-400/90">{{ __('Attorney workspace') }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-50">
                    {{ $greeting }}, {{ $displayName }}
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ __('Track assigned notarizations, upcoming sessions, and everything you need to stay practice-ready.') }}
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-zinc-700 ring-1 ring-zinc-200/80 dark:bg-zinc-800/80 dark:text-zinc-200 dark:ring-white/10">
                        <flux:icon.user-circle variant="mini" class="size-3.5 text-teal-600 dark:text-teal-400" />
                        {{ $roleLabel }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $readiness['ready'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200/80 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-50 text-amber-800 ring-amber-200/80 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30' }}">
                        <span class="size-1.5 rounded-full {{ $readiness['ready'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ $readiness['ready'] ? __('eNOTARY ready') : __(':pct% practice ready', ['pct' => $readinessPercent]) }}
                    </span>
                    @if ($metrics['blocked'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-500/25">
                            <flux:icon.exclamation-triangle variant="mini" class="size-3.5" />
                            {{ trans_choice(':count needs attention|:count need attention', $metrics['blocked'], ['count' => $metrics['blocked']]) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-stretch gap-3 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                <p class="text-center text-xs font-medium text-zinc-500 sm:text-end dark:text-zinc-400">{{ now()->format('l, F j, Y') }}</p>
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" :href="route('notary.requests.index')" wire:navigate icon="clipboard-document-list">
                        {{ __('All notarizations') }}
                    </flux:button>
                    <flux:button variant="primary" :href="route('notary.requests.create')" wire:navigate icon="plus">
                        {{ __('New notarization') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </section>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('status') }}" />
    @endif

    @if (! $eligibility['allowed'])
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Action required before you can practice') }}">
            <p>{{ $eligibility['message'] }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button variant="ghost" size="sm" :href="route('settings.trust-profile')" wire:navigate>{{ __('Trust profile') }}</flux:button>
                <flux:button variant="ghost" size="sm" :href="route('settings.profile', ['tab' => 'security'])" wire:navigate>{{ __('Security settings') }}</flux:button>
                <flux:button variant="ghost" size="sm" :href="route('settings.attorney-application')" wire:navigate>{{ __('Attorney application') }}</flux:button>
            </div>
        </flux:callout>
    @elseif ($dashboardData['requiresRenewal'])
        <flux:callout variant="warning" icon="clock" heading="{{ __('Commission renewal') }}">
            {{ __('Your commission is approaching expiry. Submit a renewal application to avoid interruptions.') }}
            <flux:button variant="ghost" size="sm" class="mt-2" :href="route('settings.attorney-application')" wire:navigate>{{ __('Renew now') }}</flux:button>
        </flux:callout>
    @endif

    {{-- Metric cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $dashboardService->requestsIndexUrl() }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Open notarizations') }}</p>
                <div class="flex size-9 items-center justify-center rounded-xl bg-zinc-100 transition group-hover:bg-zinc-200/80 dark:bg-zinc-800 dark:group-hover:bg-zinc-700">
                    <flux:icon.clipboard-document-list variant="outline" class="size-4.5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
            <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-50">{{ $metrics['open'] }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __(':total total assigned', ['total' => $metrics['total']]) }}</p>
            <flux:icon.arrow-right variant="mini" class="absolute bottom-5 end-5 size-4 text-zinc-300 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100 dark:text-zinc-600" />
        </a>

        <a href="{{ $dashboardService->requestsIndexUrl('blocked') }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/90 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-amber-900/40 dark:from-amber-950/30 dark:to-zinc-900 dark:hover:border-amber-800/60">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 dark:text-amber-400">{{ __('Needs attention') }}</p>
                <div class="flex size-9 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon.exclamation-triangle variant="outline" class="size-4.5 text-amber-700 dark:text-amber-400" />
                </div>
            </div>
            <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-amber-800 dark:text-amber-300">{{ $metrics['blocked'] }}</p>
            <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-400/80">{{ __('Blocked from sending') }}</p>
            <flux:icon.arrow-right variant="mini" class="absolute bottom-5 end-5 size-4 text-amber-400/60 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
        </a>

        <a href="{{ $dashboardService->requestsIndexUrl('ready_to_send') }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-teal-200/70 bg-gradient-to-br from-teal-50/80 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md dark:border-teal-900/40 dark:from-teal-950/30 dark:to-zinc-900 dark:hover:border-teal-800/60">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-teal-700 dark:text-teal-400">{{ __('Ready to send') }}</p>
                <div class="flex size-9 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/40">
                    <flux:icon.paper-airplane variant="outline" class="size-4.5 text-teal-700 dark:text-teal-400" />
                </div>
            </div>
            <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-teal-800 dark:text-teal-300">{{ $metrics['ready_to_send'] }}</p>
            <p class="mt-1 text-xs text-teal-700/80 dark:text-teal-400/80">{{ __('Prepared for signers') }}</p>
            <flux:icon.arrow-right variant="mini" class="absolute bottom-5 end-5 size-4 text-teal-400/60 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
        </a>

        <a href="{{ $dashboardService->requestsIndexUrl('awaiting_signatures') }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50/80 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md dark:border-violet-900/40 dark:from-violet-950/30 dark:to-zinc-900 dark:hover:border-violet-800/60">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-700 dark:text-violet-400">{{ __('Awaiting signatures') }}</p>
                <div class="flex size-9 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/40">
                    <flux:icon.pencil-square variant="outline" class="size-4.5 text-violet-700 dark:text-violet-400" />
                </div>
            </div>
            <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-violet-800 dark:text-violet-300">{{ $metrics['awaiting_signatures'] }}</p>
            <p class="mt-1 text-xs text-violet-700/80 dark:text-violet-400/80">{{ __(':sessions sessions · :signing signing', ['sessions' => $metrics['sessions'], 'signing' => $metrics['attorney_signing']]) }}</p>
            <flux:icon.arrow-right variant="mini" class="absolute bottom-5 end-5 size-4 text-violet-400/60 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Continue work --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="ui-panel overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200/70 bg-zinc-50/50 px-6 py-4 dark:border-zinc-700/80 dark:bg-zinc-800/30">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-500/15">
                            <flux:icon.bolt variant="outline" class="size-5 text-teal-700 dark:text-teal-400" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Continue work') }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Assigned notarizations that need your attention next.') }}</p>
                        </div>
                    </div>
                    <flux:button variant="ghost" size="sm" :href="route('notary.requests.index')" wire:navigate>{{ __('View all') }}</flux:button>
                </div>

                <div class="p-4 sm:p-6">
                    @forelse ($dashboardData['continueWork'] as $request)
                        @php
                            $presentation = $dashboardService->statusPresentation($request);
                        @endphp
                        <a
                            href="{{ route('notary.requests.show', $request) }}"
                            wire:navigate
                            class="group mb-3 flex last:mb-0 flex-col gap-4 rounded-2xl border border-zinc-200/80 bg-white p-4 transition hover:border-teal-200/80 hover:bg-teal-50/30 hover:shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700/80 dark:bg-zinc-900/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/5"
                        >
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500/15 to-emerald-500/10 text-sm font-bold text-teal-800 ring-1 ring-teal-500/20 dark:text-teal-300 dark:ring-teal-500/25">
                                    {{ strtoupper(mb_substr($request->title, 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-semibold text-zinc-900 group-hover:text-teal-800 dark:text-zinc-100 dark:group-hover:text-teal-300">{{ $request->title }}</p>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $presentation['badge'] }}">
                                            <span class="size-1.5 rounded-full {{ $presentation['dot'] }}"></span>
                                            {{ $dashboardService->statusLabel($request) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $request->requester?->name ?? __('Unknown requester') }}
                                        <span class="mx-1 text-zinc-300 dark:text-zinc-600">·</span>
                                        {{ __('Updated :time', ['time' => $request->updated_at?->diffForHumans() ?? '—']) }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:bg-teal-700 dark:bg-teal-600 dark:group-hover:bg-teal-500">
                                {{ $dashboardService->workActionLabel($request) }}
                                <flux:icon.arrow-right variant="mini" class="size-4" />
                            </span>
                        </a>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300/90 bg-zinc-50/50 px-6 py-14 text-center dark:border-zinc-600 dark:bg-zinc-800/20">
                            <div class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon.inbox variant="outline" class="size-7 text-zinc-400 dark:text-zinc-500" />
                            </div>
                            <p class="mt-4 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('You are all caught up') }}</p>
                            <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('No open notarizations assigned to you right now. Start a new notarization or check back later.') }}</p>
                            <flux:button variant="primary" class="mt-5" :href="route('notary.requests.create')" wire:navigate icon="plus">
                                {{ __('New notarization') }}
                            </flux:button>
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($dashboardData['upcomingSessions']->isNotEmpty())
                <div class="ui-panel overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-zinc-200/70 px-6 py-4 dark:border-zinc-700/80">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-500/15">
                            <flux:icon.calendar-days variant="outline" class="size-5 text-sky-700 dark:text-sky-400" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Upcoming sessions') }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Scheduled remote notarization sessions.') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-zinc-200/70 dark:divide-zinc-700/80">
                        @foreach ($dashboardData['upcomingSessions'] as $request)
                            @php
                                $session = $request->sessions->first();
                            @endphp
                            <a
                                href="{{ route('notary.requests.show', $request) }}"
                                wire:navigate
                                class="flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-sky-50/50 dark:hover:bg-sky-500/5"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg border border-sky-200/80 bg-sky-50 text-center dark:border-sky-800/60 dark:bg-sky-950/40">
                                        <span class="text-[10px] font-bold uppercase leading-none text-sky-600 dark:text-sky-400">{{ $session?->scheduled_for?->format('M') ?? '—' }}</span>
                                        <span class="text-sm font-bold leading-tight text-sky-800 dark:text-sky-300">{{ $session?->scheduled_for?->format('j') ?? '·' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $request->title }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $session?->scheduled_for?->format('l, g:i A') ?? __('Schedule pending') }}
                                        </p>
                                    </div>
                                </div>
                                <flux:icon.chevron-right variant="mini" class="size-5 shrink-0 text-zinc-400" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar panels --}}
        <div class="space-y-6">
            <div class="ui-panel p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Quick actions') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ route('notary.requests.index') }}" wire:navigate class="flex flex-col gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-3 transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/5">
                        <flux:icon.clipboard-document-list variant="outline" class="size-5 text-teal-700 dark:text-teal-400" />
                        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Notarizations') }}</span>
                    </a>
                    <a href="{{ route('settings.trust-profile') }}" wire:navigate class="flex flex-col gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-3 transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/5">
                        <flux:icon.shield-check variant="outline" class="size-5 text-teal-700 dark:text-teal-400" />
                        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Trust') }}</span>
                    </a>
                    <a href="{{ route('notary.credentials') }}" wire:navigate class="flex flex-col gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-3 transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/5">
                        <flux:icon.identification variant="outline" class="size-5 text-teal-700 dark:text-teal-400" />
                        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Credentials') }}</span>
                    </a>
                    <a href="{{ route('settings.profile') }}" wire:navigate class="flex flex-col gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-3 transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/5">
                        <flux:icon.cog-6-tooth variant="outline" class="size-5 text-teal-700 dark:text-teal-400" />
                        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Settings') }}</span>
                    </a>
                </div>
            </div>

            <div class="ui-panel p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('eNOTARY readiness') }}</h2>
                    <flux:badge size="sm" :color="$readiness['ready'] ? 'green' : 'amber'">
                        {{ $readiness['met'] }}/{{ $readiness['total'] }}
                    </flux:badge>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs font-medium text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Progress') }}</span>
                        <span>{{ $readinessPercent }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500"
                            style="width: {{ $readinessPercent }}%"
                        ></div>
                    </div>
                </div>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($readiness['checks'] as $check)
                        <li class="flex items-start gap-2.5 rounded-lg px-2 py-1.5 text-xs {{ $check['met'] ? 'bg-emerald-50/60 dark:bg-emerald-500/5' : 'bg-amber-50/40 dark:bg-amber-500/5' }}">
                            @if ($check['met'])
                                <flux:icon.check-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            @else
                                <flux:icon.x-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            @endif
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $check['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <flux:button variant="ghost" size="sm" class="mt-4 w-full" :href="route('settings.trust-profile')" wire:navigate icon="arrow-right" icon-position="right">
                    {{ __('Complete trust profile') }}
                </flux:button>
            </div>

            <div class="ui-panel p-5">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-500/15">
                        <flux:icon.identification variant="outline" class="size-5 text-indigo-700 dark:text-indigo-400" />
                    </div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Commission & credentials') }}</h2>
                </div>
                @if ($credential['has_credential'])
                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50">
                            <dt class="text-xs font-medium text-zinc-500">{{ __('Status') }}</dt>
                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $credential['status'] }}</dd>
                        </div>
                        @if ($credential['commission_expires_at'])
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium text-zinc-500">{{ __('Expires') }}</dt>
                                <dd class="text-end text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $credential['commission_expires_at']->format('M j, Y') }}
                                    @if ($credential['days_until_expiry'] !== null && $credential['days_until_expiry'] >= 0)
                                        <span class="block text-xs font-normal text-zinc-500">{{ __(':days days left', ['days' => $credential['days_until_expiry']]) }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-lg border px-3 py-2.5 {{ $credential['has_seal'] ? 'border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200/80 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                                <div class="flex items-center gap-1.5">
                                    @if ($credential['has_seal'])
                                        <flux:icon.check-circle variant="mini" class="size-4 text-emerald-600" />
                                    @else
                                        <flux:icon.x-circle variant="mini" class="size-4 text-amber-600" />
                                    @endif
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Seal') }}</span>
                                </div>
                                <p class="mt-1 text-[11px] {{ $credential['has_seal'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                    {{ $credential['has_seal'] ? __('On file') : __('Missing') }}
                                </p>
                            </div>
                            <div class="rounded-lg border px-3 py-2.5 {{ $credential['has_signature'] ? 'border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200/80 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                                <div class="flex items-center gap-1.5">
                                    @if ($credential['has_signature'])
                                        <flux:icon.check-circle variant="mini" class="size-4 text-emerald-600" />
                                    @else
                                        <flux:icon.x-circle variant="mini" class="size-4 text-amber-600" />
                                    @endif
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Signature') }}</span>
                                </div>
                                <p class="mt-1 text-[11px] {{ $credential['has_signature'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                    {{ $credential['has_signature'] ? __('On file') : __('Missing') }}
                                </p>
                            </div>
                        </div>
                    </dl>
                @else
                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('No approved commission on file.') }}</p>
                @endif
                <flux:button variant="ghost" size="sm" class="mt-4 w-full" :href="route('notary.credentials')" wire:navigate>{{ __('Manage credentials') }}</flux:button>
            </div>
        </div>
    </div>

    {{-- Compliance: certificate revocation (collapsed by default) --}}
    <div class="ui-panel overflow-hidden">
        <button
            type="button"
            wire:click="$toggle('showCompliance')"
            class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40"
        >
            <div class="flex items-center gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.shield-check variant="outline" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Compliance · signer certificates') }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200/80 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/25">
                            {{ __(':count active', ['count' => $certificates['total_active']]) }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-200/80 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/25">
                            {{ __(':count revoked', ['count' => $certificates['total_revoked']]) }}
                        </span>
                    </div>
                </div>
            </div>
            <flux:icon :name="$showCompliance ? 'chevron-up' : 'chevron-down'" variant="mini" class="size-5 shrink-0 text-zinc-400" />
        </button>

        @if ($showCompliance)
            <div class="space-y-6 border-t border-zinc-200/80 bg-zinc-50/30 px-6 py-6 dark:border-zinc-700 dark:bg-zinc-950/20">
                <div class="rounded-2xl border border-zinc-200/80 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Active signer certificates') }}</h3>
                    <div class="mt-4 space-y-4">
                        @forelse ($certificates['active'] as $certificate)
                            <div class="rounded-xl border border-zinc-200/90 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $certificate->documentSigner?->name ?? __('Unknown signer') }}</div>
                                        <div class="text-zinc-500">{{ $certificate->documentSigner?->document?->title ?? '-' }}</div>
                                        <div class="break-all font-mono text-[11px] text-zinc-400">{{ $certificate->serial_number }}</div>
                                    </div>
                                    <div class="w-full max-w-sm space-y-2 rounded-xl border border-zinc-200/80 bg-white p-3 dark:border-zinc-600 dark:bg-zinc-900">
                                        <flux:input wire:model="revocationReasons.{{ $certificate->id }}" label="{{ __('Revocation reason') }}" type="text" />
                                        <flux:button type="button" variant="danger" wire:click="revokeCertificate({{ $certificate->id }})" icon="no-symbol">
                                            {{ __('Revoke certificate') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600">{{ __('No active signer certificates.') }}</p>
                        @endforelse
                    </div>
                </div>

                @if ($certificates['revoked']->isNotEmpty())
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/50 p-5 dark:border-red-900/40 dark:bg-red-950/25">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-red-800 dark:text-red-200">
                            <flux:icon.no-symbol variant="mini" class="size-4" />
                            {{ __('Recent revocations') }}
                        </h3>
                        <ul class="mt-3 space-y-2">
                            @foreach ($certificates['revoked'] as $certificate)
                                <li class="rounded-lg bg-white/60 px-3 py-2 text-sm text-red-900/90 dark:bg-red-950/30 dark:text-red-200/90">
                                    <span class="font-medium">{{ $certificate->documentSigner?->name }}</span>
                                    <span class="text-red-700/70 dark:text-red-400/70"> — {{ $certificate->revocation_reason ?? '-' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-admin.page>
