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

    /**
     * Handle an incoming authentication request.
     */
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
        Session::forget([
            AuthSession::TWO_FACTOR_PASSED,
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
            AuthSession::REGISTER_PENDING_DATA,
            AuthSession::REGISTER_TWO_FACTOR_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_USER_ID,
            AuthSession::SETUP_SECRET,
        ]);

        $this->redirectIntended(default: route('documents.index', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
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

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="min-h-screen">
    <div class="grid min-h-screen lg:grid-cols-12">
        <aside class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end">
            <div class="absolute inset-0 bg-gradient-to-b from-[#2EC4B6] via-[#2AAE9F] to-[#1B5E20]"></div>
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80')] bg-cover bg-center opacity-10"></div>
            <div class="absolute -left-24 top-12 h-56 w-56 rounded-full bg-[#2EC4B6]/35 blur-3xl"></div>
            <div class="absolute bottom-12 right-8 h-44 w-44 rounded-full bg-[#FFD166]/20 blur-3xl"></div>

            <div class="relative flex h-full flex-col justify-between p-10">
                <div class="max-w-sm rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-md">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#d8fff8]">{{ __('Secure access portal') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
                        {{ __('Welcome back to seamless and secure signing.') }}
                    </h2>
                    <p class="mt-3 text-sm text-zinc-100/90">
                        {{ __('Sign in to continue managing contracts, signatures, and secure document workflows.') }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="grid size-[3.25rem] shrink-0 place-items-stretch rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
                        <x-app-logo-icon class="size-full fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">DocuTrust</p>
                        <p class="text-xs text-zinc-200">{{ __('Secure. Sign. Simplify.') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-6 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 sm:py-8 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-4 rounded-2xl border border-[#2EC4B6]/30 bg-white/80 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="grid size-11 shrink-0 place-items-stretch rounded-xl border border-[#2EC4B6]/30 bg-[#2EC4B6]/10 p-2 dark:border-teal-400/30 dark:bg-teal-400/10">
                            <x-app-logo-icon class="size-full fill-current text-[#1B5E20] dark:text-teal-300" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ config('app.name', 'DocuTrust') }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Your secure access portal') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg transition-all duration-300 dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Sign in') }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Enter your email and password to continue') }}</p>

                    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

                    @php
                        $authInputClasses = 'rounded-xl border-gray-300 bg-white/95 text-base text-[#1F2937] placeholder:text-gray-400 transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400';
                    @endphp

                    <form wire:submit="login" class="mt-6 flex flex-col gap-5 sm:gap-6" x-data="{ showPassword: false }">
                        <flux:input
                            wire:model="email"
                            label="{{ __('Email address') }}"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="Email"
                            inputmode="email"
                            autocapitalize="off"
                            spellcheck="false"
                            class="{{ $authInputClasses }}"
                        />

                        <div class="space-y-2">
                            <div>
                                <label for="password" class="mb-1.5 block text-sm text-[#1F2937] dark:text-zinc-100">{{ __('Password') }}</label>
                                <div class="relative">
                                    <input
                                        wire:model="password"
                                        id="password"
                                        name="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="current-password"
                                        placeholder="{{ __('Password') }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white/95 px-3 py-3 pr-12 text-base text-[#1F2937] outline-none transition duration-200 placeholder:text-gray-400 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400"
                                    />
                                    <button
                                        type="button"
                                        x-on:click="showPassword = !showPassword"
                                        x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        class="absolute inset-y-0 right-0 inline-flex min-h-11 items-center px-3 text-gray-500 transition hover:text-[#1B5E20] dark:text-zinc-400 dark:hover:text-teal-300"
                                    >
                                        <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                                            <path d="M12 4.5c4.78 0 8.86 2.9 10.5 7.5-1.64 4.6-5.72 7.5-10.5 7.5S3.14 16.6 1.5 12C3.14 7.4 7.22 4.5 12 4.5Zm0 3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z" />
                                        </svg>
                                        <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                                            <path d="m3.53 2.47 18 18-1.06 1.06-2.49-2.5A12.27 12.27 0 0 1 12 20c-4.78 0-8.86-2.9-10.5-7.5a11.75 11.75 0 0 1 3.82-5.29L2.47 3.53 3.53 2.47Zm4.27 4.27A8.9 8.9 0 0 0 3.14 12c1.45 3.86 4.86 6 8.86 6 1.48 0 2.89-.3 4.17-.85l-1.9-1.9A4.5 4.5 0 0 1 8.75 9.7L7.8 8.74Zm12.32 5.26a8.88 8.88 0 0 0-4.84-5.3l1.5-1.5A10.57 10.57 0 0 1 22.5 12c-.44 1.23-1.07 2.34-1.85 3.3L19.12 12Z" />
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            @if (Route::has('password.request'))
                                <x-text-link class="inline-flex min-h-10 items-center text-sm text-[#1B5E20] hover:text-[#2EC4B6] dark:text-teal-300 dark:hover:text-teal-200" href="{{ route('password.request') }}">
                                    {{ __('Forgot password?') }}
                                </x-text-link>
                            @endif
                        </div>

                        <flux:checkbox wire:model="remember" label="{{ __('Remember me') }}" class="accent-[#2EC4B6]" />

                        <div class="flex items-center justify-end">
                            <flux:button
                                type="submit"
                                variant="primary"
                                class="w-full rounded-xl bg-teal-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-teal-600 hover:shadow-md active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:bg-teal-300 disabled:text-white/80 dark:bg-teal-400 dark:text-zinc-900 dark:hover:bg-teal-300 dark:focus-visible:ring-teal-300 dark:focus-visible:ring-offset-zinc-950 dark:disabled:bg-teal-800 dark:disabled:text-zinc-400"
                            >
                                {{ __('Sign in') }}
                            </flux:button>
                        </div>
                    </form>

                    <div class="mt-6 space-x-1 text-center text-sm text-[#1F2937] dark:text-zinc-300">
                        {{ __('Need an account?') }}
                        <x-text-link href="{{ route('register') }}" class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">
                            {{ __('Create one') }}
                        </x-text-link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
