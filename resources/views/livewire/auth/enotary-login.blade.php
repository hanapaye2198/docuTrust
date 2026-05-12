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

new #[Layout('components.layouts.auth.simple')] class extends Component {
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

<div class="min-h-screen bg-[#f6f3ed] text-[#1b1b1b]">
    <style>
        .enotary-shell {
            font-family: "Plus Jakarta Sans", "Inter", system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(24, 108, 76, 0.18), transparent 28%),
                radial-gradient(circle at right center, rgba(198, 166, 102, 0.18), transparent 24%),
                linear-gradient(180deg, #f6f3ed 0%, #efe8dd 100%);
        }

        .enotary-grid {
            background-image:
                linear-gradient(rgba(24, 108, 76, 0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(24, 108, 76, 0.07) 1px, transparent 1px);
            background-size: 32px 32px;
        }

        .enotary-card {
            box-shadow: 0 24px 60px rgba(40, 30, 10, 0.12);
        }
    </style>

    <div class="enotary-shell enotary-grid relative min-h-screen overflow-hidden">
        <div class="absolute left-0 top-0 h-72 w-72 -translate-x-1/3 -translate-y-1/3 rounded-full bg-[#1c7c54]/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 translate-x-1/4 translate-y-1/4 rounded-full bg-[#c6a666]/20 blur-3xl"></div>

        <div class="relative mx-auto grid min-h-screen max-w-7xl gap-10 px-5 py-8 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
            <section class="flex flex-col justify-between rounded-[2rem] border border-[#1c7c54]/15 bg-[#123629] px-6 py-8 text-white sm:px-8 lg:px-10">
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                                <x-app-logo-icon class="h-7 w-7 fill-current text-[#f7f1e6]" />
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-[0.28em] text-[#d6e8e0]">{{ __('DocuTrust') }}</div>
                                <div class="text-lg font-semibold text-[#fff8ef]">{{ __('e-Notary Portal') }}</div>
                            </div>
                        </div>

                        <a
                            href="{{ route('home') }}"
                            class="inline-flex items-center rounded-full border border-white/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-[#e7f4ef] transition hover:bg-white/10"
                        >
                            {{ __('Back to site') }}
                        </a>
                    </div>

                    <div class="mt-14 max-w-2xl">
                        <div class="inline-flex rounded-full border border-[#d7b16b]/35 bg-[#d7b16b]/15 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#f6d79d]">
                            {{ __('Remote online notarization') }}
                        </div>
                        <h1 class="mt-6 max-w-2xl text-4xl font-black leading-tight text-[#fff8ef] sm:text-5xl">
                            {{ __('Secure case review, signer verification, and notarization in one workspace.') }}
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-8 text-[#cfe4db] sm:text-lg">
                            {{ __('Access the dedicated e-notary console for request intake, live-session oversight, completion certificates, and blockchain-backed trust records.') }}
                        </p>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5">
                            <div class="text-3xl font-black text-[#fff8ef]">01</div>
                            <div class="mt-3 text-sm font-semibold text-[#f3e7d0]">{{ __('Identity-first intake') }}</div>
                            <p class="mt-2 text-sm leading-6 text-[#c2d9cf]">{{ __('Collect requests, participant readiness, and signing blockers before attorney review.') }}</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5">
                            <div class="text-3xl font-black text-[#fff8ef]">02</div>
                            <div class="mt-3 text-sm font-semibold text-[#f3e7d0]">{{ __('Session-centered workflow') }}</div>
                            <p class="mt-2 text-sm leading-6 text-[#c2d9cf]">{{ __('Drive video scheduling, location verification, approval, and notarization from one case dashboard.') }}</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5">
                            <div class="text-3xl font-black text-[#fff8ef]">03</div>
                            <div class="mt-3 text-sm font-semibold text-[#f3e7d0]">{{ __('Verifiable output') }}</div>
                            <p class="mt-2 text-sm leading-6 text-[#c2d9cf]">{{ __('Track certificates, final PDFs, and blockchain proof after completion.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10 rounded-[1.75rem] border border-white/10 bg-[#f7f1e6] p-5 text-[#1f271f] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[0.22em] text-[#8a6b2f]">{{ __('Workspace preview') }}</div>
                            <div class="mt-2 text-xl font-bold">{{ __('e-Notary case queue') }}</div>
                        </div>
                        <div class="rounded-full bg-[#123629] px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-[#f3e7d0]">
                            {{ __('Live') }}
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-[#d9cfbf] bg-white px-4 py-3">
                            <div>
                                <div class="text-sm font-semibold">{{ __('Awaiting attorney approval') }}</div>
                                <div class="text-xs text-[#6d675f]">{{ __('Affidavit of support packet') }}</div>
                            </div>
                            <div class="rounded-full bg-[#f8e4b6] px-3 py-1 text-xs font-semibold text-[#8a6114]">{{ __('Ready') }}</div>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-[#d9cfbf] bg-white px-4 py-3">
                            <div>
                                <div class="text-sm font-semibold">{{ __('Missing blockchain proof') }}</div>
                                <div class="text-xs text-[#6d675f]">{{ __('Corporate resolution certificate') }}</div>
                            </div>
                            <div class="rounded-full bg-[#d6efe2] px-3 py-1 text-xs font-semibold text-[#166046]">{{ __('Repairable') }}</div>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-[#d9cfbf] bg-white px-4 py-3">
                            <div>
                                <div class="text-sm font-semibold">{{ __('Session scheduled') }}</div>
                                <div class="text-xs text-[#6d675f]">{{ __('Power of attorney notarization') }}</div>
                            </div>
                            <div class="rounded-full bg-[#e5ddfb] px-3 py-1 text-xs font-semibold text-[#5b3aa7]">{{ __('In progress') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center">
                <div class="enotary-card w-full max-w-xl rounded-[2rem] border border-[#d9cfbf] bg-white/95 p-6 backdrop-blur sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[#8a6b2f]">{{ __('Portal access') }}</div>
                            <h2 class="mt-3 text-3xl font-black tracking-tight text-[#1a1f1a]">{{ __('Sign in to e-Notary') }}</h2>
                            <p class="mt-2 text-sm leading-7 text-[#5c6259]">
                                {{ __('Use your existing DocuTrust account to open the dedicated notarization workspace.') }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-[#f0eadf] px-3 py-2 text-right">
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#7b725e]">{{ __('Protected') }}</div>
                            <div class="mt-1 text-sm font-semibold text-[#123629]">{{ __('2FA aware') }}</div>
                        </div>
                    </div>

                    <x-auth-session-status class="mt-5 rounded-2xl border border-[#d6eadf] bg-[#eef8f3] px-4 py-3 text-sm text-[#166046]" :status="session('status')" />

                    <form wire:submit="login" class="mt-6 space-y-5" x-data="{ showPassword: false }">
                        <div>
                            <label for="enotary-email" class="mb-2 block text-sm font-semibold text-[#283126]">{{ __('Email address') }}</label>
                            <input
                                id="enotary-email"
                                wire:model="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="name@company.com"
                                class="w-full rounded-2xl border border-[#d7d0c2] bg-[#fbf9f4] px-4 py-3 text-base text-[#1b1b1b] outline-none transition focus:border-[#1c7c54] focus:ring-4 focus:ring-[#1c7c54]/15"
                            />
                            @error('email')
                                <p class="mt-2 text-sm text-[#b42318]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="enotary-password" class="block text-sm font-semibold text-[#283126]">{{ __('Password') }}</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-[#1c7c54] transition hover:text-[#14573a]">
                                        {{ __('Forgot password?') }}
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <input
                                    id="enotary-password"
                                    wire:model="password"
                                    x-bind:type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="{{ __('Enter your password') }}"
                                    class="w-full rounded-2xl border border-[#d7d0c2] bg-[#fbf9f4] px-4 py-3 pr-14 text-base text-[#1b1b1b] outline-none transition focus:border-[#1c7c54] focus:ring-4 focus:ring-[#1c7c54]/15"
                                />
                                <button
                                    type="button"
                                    x-on:click="showPassword = !showPassword"
                                    x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    class="absolute inset-y-0 right-0 inline-flex items-center px-4 text-[#6f6a5f] transition hover:text-[#123629]"
                                >
                                    <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                        <path d="M12 4.5c4.78 0 8.86 2.9 10.5 7.5-1.64 4.6-5.72 7.5-10.5 7.5S3.14 16.6 1.5 12C3.14 7.4 7.22 4.5 12 4.5Zm0 3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z" />
                                    </svg>
                                    <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                        <path d="m3.53 2.47 18 18-1.06 1.06-2.49-2.5A12.27 12.27 0 0 1 12 20c-4.78 0-8.86-2.9-10.5-7.5a11.75 11.75 0 0 1 3.82-5.29L2.47 3.53 3.53 2.47Zm4.27 4.27A8.9 8.9 0 0 0 3.14 12c1.45 3.86 4.86 6 8.86 6 1.48 0 2.89-.3 4.17-.85l-1.9-1.9A4.5 4.5 0 0 1 8.75 9.7L7.8 8.74Zm12.32 5.26a8.88 8.88 0 0 0-4.84-5.3l1.5-1.5A10.57 10.57 0 0 1 22.5 12c-.44 1.23-1.07 2.34-1.85 3.3L19.12 12Z" />
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 text-sm text-[#b42318]">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center justify-between gap-3 rounded-2xl border border-[#e3dccf] bg-[#faf7f1] px-4 py-3">
                            <span>
                                <span class="block text-sm font-semibold text-[#283126]">{{ __('Keep this device signed in') }}</span>
                                <span class="mt-1 block text-xs text-[#6f6a5f]">{{ __('Recommended only on a private workstation.') }}</span>
                            </span>
                            <input wire:model="remember" type="checkbox" class="h-5 w-5 rounded border-[#bcae96] text-[#1c7c54] focus:ring-[#1c7c54]" />
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-[#123629] px-5 py-3.5 text-sm font-bold uppercase tracking-[0.18em] text-[#f7f1e6] transition hover:bg-[#0d2a1f] focus:outline-none focus:ring-4 focus:ring-[#123629]/20"
                        >
                            {{ __('Enter e-Notary workspace') }}
                        </button>
                    </form>

                    <div class="mt-6 grid gap-3 rounded-[1.5rem] border border-[#ece5da] bg-[#faf7f1] p-4 sm:grid-cols-2">
                        <a href="{{ route('login') }}" class="rounded-2xl border border-[#d8cfbf] bg-white px-4 py-3 text-center text-sm font-semibold text-[#283126] transition hover:border-[#1c7c54] hover:text-[#1c7c54]">
                            {{ __('Use standard sign in') }}
                        </a>
                        <a href="{{ route('register') }}" class="rounded-2xl border border-[#d8cfbf] bg-white px-4 py-3 text-center text-sm font-semibold text-[#283126] transition hover:border-[#1c7c54] hover:text-[#1c7c54]">
                            {{ __('Create DocuTrust account') }}
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
