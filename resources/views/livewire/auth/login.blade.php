<?php

use App\Models\User;
use App\Support\AuthSession;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        Session::forget([
            AuthSession::TWO_FACTOR_PASSED,
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
        ]);
    }

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Invalid credentials'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = User::query()->where('email', $this->email)->first();
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials'),
            ]);
        }

        Auth::login($user, $this->remember);
        Session::regenerate();

        if ($user->two_factor_enabled && $user->two_factor_confirmed_at !== null) {
            Session::put([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_USER_ID => (int) $user->id,
                AuthSession::PENDING_TWO_FACTOR_REMEMBER => $this->remember,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ]);

            $this->redirect(route('two-factor.challenge', absolute: false), navigate: true);

            return;
        }

        Session::put(AuthSession::TWO_FACTOR_PASSED, true);
        Session::forget([
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
            AuthSession::TRUSTED_DEVICE_UNTIL,
            AuthSession::REGISTER_PENDING_DATA,
            AuthSession::REGISTER_TWO_FACTOR_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_USER_ID,
            AuthSession::SETUP_SECRET,
        ]);

        $this->redirectIntended(default: route($user->homeRouteName(), absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

@php
    $mode = request()->query('mode') === 'enotary' ? 'enotary' : 'standard';
@endphp

<div
    x-data="{
        mode: @js($mode),
        setMode: function(nextMode) {
            this.mode = nextMode;

            var url = new URL(window.location.href);
            if (nextMode === 'enotary') {
                url.searchParams.set('mode', 'enotary');
            } else {
                url.searchParams.delete('mode');
            }

            window.history.replaceState({}, '', url);
        },
    }"
    class="min-h-screen"
>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="grid min-h-screen lg:grid-cols-12">
        {{-- Sidebar --}}
        <aside class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end">
            <div
                x-bind:class="mode === 'enotary' ? 'from-[#0f2b1f] via-[#1a6b48] to-[#7a5c28]' : 'from-[#0d9488] via-[#0f766e] to-[#134e4a]'"
                class="absolute inset-0 bg-gradient-to-br transition-all duration-700"
            ></div>
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80')] bg-cover bg-center opacity-[0.07]"></div>

            {{-- Decorative orbs --}}
            <div
                x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]/20' : 'bg-teal-300/20'"
                class="absolute -left-20 top-16 h-64 w-64 rounded-full blur-[80px] transition-all duration-700"
            ></div>
            <div
                x-bind:class="mode === 'enotary' ? 'bg-[#f3e7d0]/15' : 'bg-emerald-200/15'"
                class="absolute -bottom-8 right-0 h-52 w-52 rounded-full blur-[60px] transition-all duration-700"
            ></div>

            <div class="relative flex h-full flex-col justify-between p-10 xl:p-12">
                {{-- Hero card --}}
                <div class="max-w-sm rounded-2xl border border-white/15 bg-white/[0.08] p-6 shadow-2xl backdrop-blur-xl">
                    <p
                        x-text="mode === 'enotary' ? '{{ __('Remote online notarization') }}' : '{{ __('Secure document signing') }}'"
                        class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70"
                    ></p>
                    <h2
                        x-text="mode === 'enotary' ? '{{ __('Enter the notarization workspace with your existing account.') }}' : '{{ __('Welcome back to seamless and secure signing.') }}'"
                        class="mt-3 text-xl font-semibold leading-snug text-white"
                    ></h2>
                    <p
                        x-text="mode === 'enotary' ? '{{ __('Same login flow and two-factor checks across both modes.') }}' : '{{ __('Manage contracts, signatures, and document workflows in one place.') }}'"
                        class="mt-3 text-[13px] leading-relaxed text-white/75"
                    ></p>
                </div>

                {{-- Brand footer --}}
                <div class="flex items-center gap-3">
                    <div class="grid size-12 shrink-0 place-items-stretch rounded-xl border border-white/15 bg-white/[0.08] p-2.5 backdrop-blur-xl">
                        <x-app-logo-icon class="size-full fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">DocuTrust</p>
                        <p
                            x-text="mode === 'enotary' ? '{{ __('e-Notary access') }}' : '{{ __('Secure. Sign. Simplify.') }}'"
                            class="text-xs text-white/60"
                        ></p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main content --}}
        <main class="col-span-12 flex items-center justify-center bg-[#F8FAFC] px-5 py-8 transition-colors duration-300 dark:bg-zinc-950 sm:px-8 lg:col-span-7 lg:px-12">
            <div class="w-full max-w-[420px]">
                {{-- Mobile brand header --}}
                <div class="mb-5 rounded-2xl border border-zinc-200/80 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div
                            x-bind:class="mode === 'enotary' ? 'border-[#c6a666]/30 bg-[#c6a666]/10' : 'border-teal-500/20 bg-teal-500/10'"
                            class="grid size-10 shrink-0 place-items-stretch rounded-xl border p-2 transition-all duration-500"
                        >
                            <x-app-logo-icon
                                x-bind:class="mode === 'enotary' ? 'text-[#123629]' : 'text-teal-700'"
                                class="size-full fill-current dark:text-teal-300"
                            />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ config('app.name', 'DocuTrust') }}</p>
                            <p
                                x-text="mode === 'enotary' ? '{{ __('e-Notary access') }}' : '{{ __('Secure access portal') }}'"
                                class="text-xs text-zinc-500 dark:text-zinc-400"
                            ></p>
                        </div>
                    </div>
                </div>

                {{-- Login card --}}
                <div
                    x-bind:class="mode === 'enotary' ? 'border-[#e0d9cc] bg-[#fffdf9] shadow-xl shadow-amber-900/5' : 'border-zinc-200/80 bg-white shadow-xl shadow-zinc-900/5 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-none'"
                    class="rounded-2xl border p-6 transition-all duration-500 sm:p-8"
                >
                    {{-- Mode toggle --}}
                    <div class="mb-7">
                        <div
                            x-bind:class="mode === 'enotary' ? 'bg-[#f0ebe0]' : 'bg-zinc-100 dark:bg-zinc-800'"
                            class="inline-grid w-full grid-cols-2 gap-1 rounded-xl p-1 transition-all duration-500"
                        >
                            <button
                                type="button"
                                x-on:click="setMode('standard')"
                                x-bind:class="mode === 'standard' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200"
                            >
                                {{ __('Standard') }}
                            </button>
                            <button
                                type="button"
                                x-on:click="setMode('enotary')"
                                x-bind:class="mode === 'enotary' ? 'bg-[#123629] text-[#f7f1e6] shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200"
                            >
                                {{ __('e-Notary') }}
                            </button>
                        </div>
                    </div>

                    {{-- Heading --}}
                    <div class="mb-6">
                        <template x-if="mode === 'standard'">
                            <div>
                                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Sign in') }}</h1>
                                <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter your credentials to continue') }}</p>
                            </div>
                        </template>
                        <template x-if="mode === 'enotary'">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#8a6b2f]">{{ __('Portal access') }}</p>
                                <h1 class="mt-2 text-2xl font-bold text-[#1a1f1a]">{{ __('Sign in to e-Notary') }}</h1>
                                <p class="mt-1.5 text-sm text-[#5c6259]">{{ __('Use your DocuTrust account to access the notarization workspace.') }}</p>
                            </div>
                        </template>
                    </div>

                    {{-- Session status --}}
                    <x-auth-session-status
                        x-bind:class="mode === 'enotary' ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800' : 'rounded-xl bg-teal-50 px-4 py-3 text-teal-800 dark:bg-teal-900/30 dark:text-teal-200'"
                        class="mb-5 text-center text-sm"
                        :status="session('status')"
                    />

                    {{-- Login form --}}
                    <form wire:submit="login" class="flex flex-col gap-5" x-data="{ showPassword: false }">
                        {{-- Email field --}}
                        <div>
                            <label
                                x-bind:class="mode === 'enotary' ? 'text-[#283126]' : 'text-zinc-700 dark:text-zinc-300'"
                                class="mb-1.5 block text-sm font-medium"
                            >
                                {{ __('Email address') }}
                            </label>
                            <input
                                wire:model="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                                inputmode="email"
                                autocapitalize="off"
                                spellcheck="false"
                                x-bind:placeholder="mode === 'enotary' ? 'name@company.com' : '{{ __('you@example.com') }}'"
                                x-bind:class="mode === 'enotary'
                                    ? 'w-full rounded-xl border border-[#d7d0c2] bg-[#fbf9f4] px-4 py-3 text-sm text-[#1b1b1b] outline-none transition-all duration-200 placeholder:text-[#a09882] focus:border-[#1c7c54] focus:ring-2 focus:ring-[#1c7c54]/20'
                                    : 'w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 outline-none transition-all duration-200 placeholder:text-zinc-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-teal-400 dark:focus:ring-teal-400/20'"
                            />
                            @error('email')
                                <p
                                    x-bind:class="mode === 'enotary' ? 'text-[#b42318]' : 'text-red-500 dark:text-red-400'"
                                    class="mt-1.5 text-xs font-medium"
                                >{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password field --}}
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label
                                    x-bind:class="mode === 'enotary' ? 'text-[#283126]' : 'text-zinc-700 dark:text-zinc-300'"
                                    class="text-sm font-medium"
                                >
                                    {{ __('Password') }}
                                </label>
                                @if (Route::has('password.request'))
                                    <a
                                        href="{{ route('password.request') }}"
                                        x-bind:class="mode === 'enotary' ? 'text-[#1c7c54] hover:text-[#14573a]' : 'text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300'"
                                        class="text-xs font-medium transition-colors"
                                    >
                                        {{ __('Forgot password?') }}
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <input
                                    wire:model="password"
                                    name="password"
                                    x-bind:type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    x-bind:placeholder="mode === 'enotary' ? '{{ __('Enter your password') }}' : '••••••••'"
                                    x-bind:class="mode === 'enotary'
                                        ? 'w-full rounded-xl border border-[#d7d0c2] bg-[#fbf9f4] px-4 py-3 pr-12 text-sm text-[#1b1b1b] outline-none transition-all duration-200 placeholder:text-[#a09882] focus:border-[#1c7c54] focus:ring-2 focus:ring-[#1c7c54]/20'
                                        : 'w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 pr-12 text-sm text-zinc-900 outline-none transition-all duration-200 placeholder:text-zinc-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-teal-400 dark:focus:ring-teal-400/20'"
                                />
                                <button
                                    type="button"
                                    x-on:click="showPassword = !showPassword"
                                    x-bind:aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                                    x-bind:class="mode === 'enotary'
                                        ? 'text-[#6f6a5f] hover:text-[#123629]'
                                        : 'text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300'"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 transition-colors"
                                >
                                    <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-[18px]" aria-hidden="true">
                                        <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                        <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                    </svg>
                                    <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-[18px]" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.092 1.092a4 4 0 0 0-5.558-5.558Z" clip-rule="evenodd" />
                                        <path d="M10.748 13.93 8.07 11.252A2.5 2.5 0 0 0 10.748 13.93ZM7.4 15.862a9.955 9.955 0 0 0 2.6.338c4.257 0 7.893-2.66 9.336-6.41a1.651 1.651 0 0 0 0-1.186 10.007 10.007 0 0 0-2.3-3.63L7.4 15.862ZM.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 4.478 5.53L.664 10.59Z" />
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p
                                    x-bind:class="mode === 'enotary' ? 'text-[#b42318]' : 'text-red-500 dark:text-red-400'"
                                    class="mt-1.5 text-xs font-medium"
                                >{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remember me --}}
                        <div>
                            <template x-if="mode === 'standard'">
                                <label class="inline-flex cursor-pointer items-center gap-2.5">
                                    <input
                                        wire:model="remember"
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-teal-600 shadow-sm focus:ring-teal-500/30 dark:border-zinc-600 dark:bg-zinc-800 dark:text-teal-400 dark:focus:ring-teal-400/30"
                                    />
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Remember me') }}</span>
                                </label>
                            </template>
                            <template x-if="mode === 'enotary'">
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[#e3dccf] bg-[#faf7f1] px-4 py-3">
                                    <span>
                                        <span class="block text-sm font-medium text-[#283126]">{{ __('Keep this device signed in') }}</span>
                                        <span class="mt-0.5 block text-xs text-[#6f6a5f]">{{ __('Recommended only on a private workstation.') }}</span>
                                    </span>
                                    <input
                                        wire:model="remember"
                                        type="checkbox"
                                        class="size-4.5 shrink-0 rounded border-[#bcae96] text-[#1c7c54] focus:ring-[#1c7c54]/30"
                                    />
                                </label>
                            </template>
                        </div>

                        {{-- Submit button --}}
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="login"
                            x-bind:class="mode === 'enotary'
                                ? 'group relative w-full overflow-hidden rounded-xl bg-[#123629] px-5 py-3.5 text-sm font-semibold uppercase tracking-wider text-[#f7f1e6] shadow-md shadow-[#123629]/20 transition-all duration-200 hover:bg-[#0d2a1f] hover:shadow-lg hover:shadow-[#123629]/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#123629]/50 focus-visible:ring-offset-2 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-70'
                                : 'group relative w-full overflow-hidden rounded-xl bg-teal-600 px-5 py-3.5 text-sm font-semibold text-white shadow-md shadow-teal-600/20 transition-all duration-200 hover:bg-teal-700 hover:shadow-lg hover:shadow-teal-600/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-[0.98] disabled:pointer-events-none disabled:opacity-70 dark:bg-teal-500 dark:shadow-teal-500/20 dark:hover:bg-teal-400 dark:hover:shadow-teal-500/30 dark:focus-visible:ring-teal-400/50 dark:focus-visible:ring-offset-zinc-900'"
                        >
                            {{-- Animated progress bar at top of button --}}
                            <span
                                wire:loading
                                wire:target="login"
                                class="absolute inset-x-0 top-0 h-0.5 overflow-hidden"
                            >
                                <span
                                    x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]' : 'bg-teal-200 dark:bg-teal-200'"
                                    class="absolute inset-0 animate-[shimmer_1.5s_ease-in-out_infinite] rounded-full opacity-80"
                                ></span>
                            </span>

                            {{-- Default state --}}
                            <span
                                wire:loading.remove
                                wire:target="login"
                                class="inline-flex items-center justify-center gap-2"
                            >
                                <svg
                                    x-bind:class="mode === 'enotary' ? 'text-[#c6a666]' : 'text-teal-200 dark:text-teal-900'"
                                    class="size-4 opacity-70"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                </svg>
                                <span x-text="mode === 'enotary' ? '{{ __('Enter e-Notary workspace') }}' : '{{ __('Sign in') }}'"></span>
                            </span>

                            {{-- Loading state --}}
                            <span
                                wire:loading
                                wire:target="login"
                                class="inline-flex items-center justify-center gap-2.5"
                            >
                                {{-- Pulsing dots --}}
                                <span class="flex items-center gap-1">
                                    <span
                                        x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]' : 'bg-teal-200 dark:bg-teal-200'"
                                        class="inline-block size-1.5 animate-[bounce_1s_ease-in-out_infinite] rounded-full"
                                    ></span>
                                    <span
                                        x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]' : 'bg-teal-200 dark:bg-teal-200'"
                                        class="inline-block size-1.5 animate-[bounce_1s_ease-in-out_0.15s_infinite] rounded-full"
                                    ></span>
                                    <span
                                        x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]' : 'bg-teal-200 dark:bg-teal-200'"
                                        class="inline-block size-1.5 animate-[bounce_1s_ease-in-out_0.3s_infinite] rounded-full"
                                    ></span>
                                </span>
                                <span x-text="mode === 'enotary' ? '{{ __('Authenticating…') }}' : '{{ __('Signing in…') }}'"></span>
                            </span>
                        </button>

                        <style>
                            @keyframes shimmer {
                                0% { transform: translateX(-100%); }
                                100% { transform: translateX(100%); }
                            }
                        </style>
                    </form>

                    {{-- Register link --}}
                    <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Need an account?') }}
                        <a
                            href="{{ route('register') }}"
                            x-bind:class="mode === 'enotary' ? 'text-[#1c7c54] hover:text-[#14573a]' : 'text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300'"
                            class="font-medium transition-colors hover:underline"
                        >
                            {{ __('Create one') }}
                        </a>
                    </p>
                </div>
            </div>
        </main>
    </div>
</div>
