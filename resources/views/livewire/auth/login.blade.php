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

    public string $mode = 'standard';

    public function mount(): void
    {
        $this->mode = request()->query('mode') === 'enotary' ? 'enotary' : 'standard';

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

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated. Contact your administrator.'),
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
    $authInputClasses = 'login-auth-input rounded-xl border-gray-300 bg-white/95 text-base text-[#1F2937] placeholder:text-gray-400 transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400';
@endphp

<div
    class="login-shell min-h-screen transition-colors duration-500"
    data-mode="{{ $mode }}"
    x-data="{
        mode: @js($mode),
        setMode(nextMode) {
            this.mode = nextMode;
            this.$root.dataset.mode = nextMode;
            $wire.set('mode', nextMode);

            const url = new URL(window.location.href);
            if (nextMode === 'enotary') {
                url.searchParams.set('mode', 'enotary');
            } else {
                url.searchParams.delete('mode');
            }

            window.history.replaceState({}, '', url);
        },
    }"
>
    <div class="grid min-h-screen lg:grid-cols-12">
        {{-- Sidebar --}}
        <aside
            class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end"
            style="background-image: linear-gradient(180deg, rgba(9, 9, 11, 0.35) 0%, rgba(9, 9, 11, 0.85) 100%), url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1400&q=80'); background-size: cover; background-position: center;"
        >
            <div
                x-bind:class="mode === 'enotary'
                    ? 'from-[#0f2b1f]/90 via-[#1a6b48]/85 to-[#1B5E20]/95'
                    : 'from-[#2EC4B6]/25 via-[#2EC4B6]/30 to-[#1B5E20]/90'"
                class="absolute inset-0 bg-linear-to-b transition-all duration-700"
            ></div>
            <div
                x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]/25' : 'bg-[#2EC4B6]/30'"
                class="absolute -left-24 top-12 h-56 w-56 rounded-full blur-3xl transition-all duration-700"
            ></div>
            <div
                x-bind:class="mode === 'enotary' ? 'bg-[#f3e7d0]/20' : 'bg-[#FFD166]/20'"
                class="absolute bottom-16 right-6 h-40 w-40 rounded-full blur-3xl transition-all duration-700"
            ></div>

            <div class="relative flex h-full flex-col justify-between p-10 xl:p-12">
                <div class="max-w-sm rounded-2xl border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-md">
                    <p
                        x-text="mode === 'enotary' ? '{{ __('Remote online notarization') }}' : '{{ __('Secure document signing') }}'"
                        class="text-xs font-medium uppercase tracking-[0.18em] text-[#d8fff8]"
                    ></p>
                    <h2
                        x-text="mode === 'enotary' ? '{{ __('Enter the notarization workspace with your existing account.') }}' : '{{ __('Welcome back to seamless and secure signing.') }}'"
                        class="mt-3 text-2xl font-semibold leading-tight text-white"
                    ></h2>
                    <p
                        x-text="mode === 'enotary' ? '{{ __('Same login flow and two-factor checks across both workspaces.') }}' : '{{ __('Manage contracts, signatures, and document workflows in one place.') }}'"
                        class="mt-3 text-sm text-zinc-200/90"
                    ></p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="grid size-[3.25rem] shrink-0 place-items-stretch rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
                        <x-app-logo-icon class="size-full fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">{{ config('app.name', 'DocuTrust') }}</p>
                        <p
                            x-text="mode === 'enotary' ? '{{ __('e-Notary access') }}' : '{{ __('Trust the digital future.') }}'"
                            class="text-xs text-zinc-200"
                        ></p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main content --}}
        <main class="col-span-12 flex items-center justify-center bg-[#F8FAFC] px-4 py-8 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 sm:py-10 lg:col-span-7 lg:px-10">
            <div class="w-full max-w-md lg:max-w-[28rem]">
                {{-- Mobile brand header --}}
                <div class="mb-4 rounded-2xl border border-[#2EC4B6]/30 bg-white/80 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="grid size-11 shrink-0 place-items-stretch rounded-xl border border-[#2EC4B6]/30 bg-[#2EC4B6]/10 p-2 dark:border-teal-400/30 dark:bg-teal-400/10">
                            <x-app-logo-icon class="size-full fill-current text-[#1B5E20] dark:text-teal-300" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ config('app.name', 'DocuTrust') }}</p>
                            <p
                                x-text="mode === 'enotary' ? '{{ __('e-Notary access') }}' : '{{ __('Secure access portal') }}'"
                                class="text-xs text-zinc-600 dark:text-zinc-400"
                            ></p>
                        </div>
                    </div>
                </div>

                {{-- Login card --}}
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-200/60 backdrop-blur transition-colors duration-500 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30 sm:p-7">
                    <div
                        class="login-accent-bar absolute inset-x-0 top-0 h-1 bg-gradient-to-r transition-all duration-500"
                        aria-hidden="true"
                    ></div>

                    {{-- Mode toggle --}}
                    <div class="mb-6 mt-1">
                        <div class="relative grid grid-cols-2 gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                            <span
                                class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-lg bg-white shadow-sm transition-transform duration-300 ease-out motion-reduce:transition-none dark:bg-zinc-700"
                                x-bind:class="mode === 'enotary' ? 'translate-x-full' : 'translate-x-0'"
                                aria-hidden="true"
                            ></span>
                            <button
                                type="button"
                                x-on:click="setMode('standard')"
                                x-bind:class="mode === 'standard' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="relative z-10 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-200"
                            >
                                <svg class="size-4 shrink-0 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.684a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.949 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z" />
                                </svg>
                                {{ __('Signer') }}
                            </button>
                            <button
                                type="button"
                                x-on:click="setMode('enotary')"
                                x-bind:class="mode === 'enotary' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="relative z-10 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-200"
                            >
                                <svg class="size-4 shrink-0 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                </svg>
                                {{ __('e-Notary') }}
                            </button>
                        </div>
                        <p
                            x-text="mode === 'enotary'
                                ? '{{ __('For notaries and notarization workspace access.') }}'
                                : '{{ __('For signers and document workflow access.') }}'"
                            class="mt-2 text-center text-xs text-zinc-500 dark:text-zinc-400"
                        ></p>
                    </div>

                    {{-- Heading --}}
                    <div class="relative mb-6 min-h-[5.25rem] sm:min-h-[5.5rem]">
                        <div
                            x-show="mode === 'standard'"
                            @if ($mode === 'standard') style="display: block;" @else style="display: none;" @endif
                            class="absolute inset-x-0 top-0"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                {{ __('Signer access') }}
                            </p>
                            <h1 class="mt-1 text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Sign in') }}</h1>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter your credentials to continue') }}</p>
                        </div>
                        <div
                            x-show="mode === 'enotary'"
                            @if ($mode === 'enotary') style="display: block;" @else style="display: none;" @endif
                            class="absolute inset-x-0 top-0"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8a6b2f] dark:text-[#c6a666]">
                                {{ __('Notary workspace') }}
                            </p>
                            <h1 class="mt-1 text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Sign in to e-Notary') }}</h1>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Same account — dedicated notarization console after login.') }}
                            </p>
                        </div>
                    </div>

                    <x-auth-session-status
                        class="mb-5 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300"
                        :status="session('status')"
                    />

                    {{-- Login form --}}
                    <form wire:submit="login" class="flex flex-col gap-5" x-data="{ showPassword: false }">
                        <flux:input
                            wire:model="email"
                            id="login-email"
                            label="{{ __('Email address') }}"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="{{ __('you@example.com') }}"
                            inputmode="email"
                            autocapitalize="off"
                            spellcheck="false"
                            class="{{ $authInputClasses }}"
                        />

                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <label for="login-password" class="text-sm text-[#1F2937] dark:text-zinc-100">{{ __('Password') }}</label>
                                @if (Route::has('password.request'))
                                    <a
                                        href="{{ route('password.request') }}"
                                        class="text-xs font-medium text-[#1B5E20] transition hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200"
                                    >
                                        {{ __('Forgot password?') }}
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <input
                                    wire:model="password"
                                    id="login-password"
                                    name="password"
                                    x-bind:type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="{{ __('Enter your password') }}"
                                    class="login-auth-input w-full rounded-xl border border-gray-300 bg-white/95 py-3 pr-14 pl-3 text-base text-[#1F2937] outline-none transition duration-200 placeholder:text-gray-400 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400"
                                />
                                <button
                                    type="button"
                                    x-on:click="showPassword = !showPassword"
                                    x-bind:aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                                    class="absolute inset-y-0 right-0 inline-flex min-h-11 items-center px-3 text-xs font-medium text-gray-500 transition hover:text-[#1B5E20] dark:text-zinc-400 dark:hover:text-teal-300"
                                >
                                    <span x-text="showPassword ? '{{ __('Hide') }}' : '{{ __('Show') }}'"></span>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs text-red-500 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3 transition-colors duration-300 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <input
                                wire:model="remember"
                                type="checkbox"
                                class="login-remember-checkbox mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-[#2EC4B6] shadow-sm focus:ring-[#2EC4B6]/30 dark:border-zinc-600 dark:bg-zinc-800 dark:text-teal-400"
                            />
                            <span>
                                <span class="block text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Remember this device') }}</span>
                                <span
                                    x-text="mode === 'enotary'
                                        ? '{{ __('Use only on a private workstation you control.') }}'
                                        : '{{ __('Stay signed in on this browser.') }}'"
                                    class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400"
                                ></span>
                            </span>
                        </label>

                        <div class="relative">
                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:target="login"
                                class="group relative w-full min-h-11 overflow-hidden transition duration-200 motion-safe:active:scale-[0.98] disabled:pointer-events-none disabled:opacity-70"
                                x-bind:class="mode === 'enotary'
                                    ? '!bg-[#123629] hover:!bg-[#0d2a1f] !text-[#f7f1e6]'
                                    : '!bg-[#2EC4B6] hover:!bg-[#1B5E20] !text-white dark:!text-black dark:hover:!text-black'"
                            >
                                <span
                                    wire:loading
                                    wire:target="login"
                                    class="absolute inset-x-0 top-0 h-0.5 overflow-hidden"
                                >
                                    <span
                                        class="login-shimmer absolute inset-0 rounded-full opacity-80"
                                        x-bind:class="mode === 'enotary' ? 'bg-[#c6a666]' : 'bg-teal-200'"
                                    ></span>
                                </span>

                                <span wire:loading.remove wire:target="login" class="inline-flex items-center justify-center gap-2">
                                    <svg class="size-4 opacity-80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                    </svg>
                                    <span x-text="mode === 'enotary' ? '{{ __('Enter e-Notary workspace') }}' : '{{ __('Sign in') }}'"></span>
                                </span>

                                <span wire:loading wire:target="login" class="inline-flex items-center justify-center gap-2.5">
                                    <span class="flex items-center gap-1" aria-hidden="true">
                                        <span class="inline-block size-1.5 motion-safe:animate-bounce rounded-full bg-white/80"></span>
                                        <span class="inline-block size-1.5 motion-safe:animate-bounce rounded-full bg-white/80 [animation-delay:0.15s]"></span>
                                        <span class="inline-block size-1.5 motion-safe:animate-bounce rounded-full bg-white/80 [animation-delay:0.3s]"></span>
                                    </span>
                                    <span x-text="mode === 'enotary' ? '{{ __('Authenticating…') }}' : '{{ __('Signing in…') }}'"></span>
                                </span>
                            </flux:button>
                        </div>
                    </form>

                    <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Need an account?') }}
                        <x-text-link
                            href="{{ route('register') }}"
                            class="font-medium text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200"
                        >
                            {{ __('Create one') }}
                        </x-text-link>
                    </p>
                </div>
            </div>
        </main>
    </div>
</div>
