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
                    <div class="rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
                        <x-app-logo-icon class="size-8 fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">DocuTrust</p>
                        <p class="text-xs text-zinc-200">{{ __('Secure. Sign. Simplify.') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-8 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-md transition-all duration-300 dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Sign in') }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Enter your email and password to continue') }}</p>

                    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

                    <form wire:submit="login" class="mt-6 flex flex-col gap-6">
                        <flux:input
                            wire:model="email"
                            label="{{ __('Email address') }}"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="email@example.com"
                            class="border-gray-300 focus:border-[#2EC4B6] focus:ring-[#2EC4B6] transition"
                        />

                        <div class="relative">
                            <flux:input
                                wire:model="password"
                                label="{{ __('Password') }}"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="{{ __('Password') }}"
                                class="border-gray-300 focus:border-[#2EC4B6] focus:ring-[#2EC4B6] transition"
                            />

                            @if (Route::has('password.request'))
                                <x-text-link class="absolute right-0 top-0 text-[#1B5E20] hover:text-[#2EC4B6] dark:text-teal-300 dark:hover:text-teal-200" href="{{ route('password.request') }}">
                                    {{ __('Forgot password?') }}
                                </x-text-link>
                            @endif
                        </div>

                        <flux:checkbox wire:model="remember" label="{{ __('Remember me') }}" class="accent-[#2EC4B6]" />

                        <div class="flex items-center justify-end">
                            <flux:button type="submit" variant="primary" class="w-full rounded-lg bg-[#2EC4B6] text-black transition hover:bg-[#1B5E20]">
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
