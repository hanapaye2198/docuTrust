<?php

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Mail\EmailOtpVerificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use App\Support\AuthSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $suffix = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $agreed_to_terms = false;

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'agreed_to_terms' => ['accepted'],
        ]);

        $fullName = collect([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
            $validated['suffix'] ?? null,
        ])->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value): string => trim($value))
            ->implode(' ');

        $otp = sprintf('%06d', random_int(0, 999999));

        $user = User::query()->create([
            'name' => $fullName,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Signer,
            'onboarding_step' => OnboardingStep::EmailVerification,
            'email_verified_at' => null,
            'email_otp' => $otp,
            'email_otp_expires_at' => now()->addMinutes(10),
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => null,
        ]);

        Mail::to($user)->send(new EmailOtpVerificationMail($otp));

        event(new Registered($user));
        Auth::login($user);
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

        $this->redirect(route('onboarding.email.verify', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen overflow-x-clip">
    <div class="grid min-h-screen lg:grid-cols-12">
        <aside
            class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end"
            style="background-image: linear-gradient(180deg, rgba(9, 9, 11, 0.35) 0%, rgba(9, 9, 11, 0.85) 100%), url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1400&q=80'); background-size: cover; background-position: center;"
        >
            <div class="absolute inset-0 bg-linear-to-b from-[#2EC4B6]/25 via-[#2EC4B6]/30 to-[#1B5E20]/90"></div>
            <div class="absolute -left-24 top-12 h-56 w-56 rounded-full bg-[#2EC4B6]/30 blur-3xl"></div>
            <div class="absolute bottom-16 right-6 h-40 w-40 rounded-full bg-[#FFD166]/20 blur-3xl"></div>

            <div class="relative flex h-full flex-col justify-between p-10">
                <div class="max-w-sm rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-md">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#d8fff8]">{{ __('Secure digital onboarding') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
                        {{ __('Built for trust, speed, and seamless signing.') }}
                    </h2>
                    <p class="mt-3 text-sm text-zinc-200/90">
                        {{ __('Create your account and complete verification with an enterprise-grade user experience.') }}
                    </p>
                </div>

                <div>
                    <div class="flex items-center gap-3">
                        <div
                            class="rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md [&_.docutrust-logo-tile]:bg-transparent [&_.docutrust-logo-tile]:shadow-none [&_.docutrust-logo-tile]:ring-0 [&_.docutrust-logo-tile]:p-0 [&_.docutrust-logo-tile_img]:!filter-none"
                        >
                            <x-app-logo-icon class="size-8 fill-current text-white" />
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-white">{{ config('app.name', 'DocuTrust') }}</p>
                            <p class="text-xs text-zinc-200">{{ __('Trust the digital future.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-6 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 sm:py-8 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-4 rounded-2xl border border-[#2EC4B6]/30 bg-white/80 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-[#2EC4B6]/30 bg-[#2EC4B6]/10 p-2 dark:border-teal-400/30 dark:bg-teal-400/10">
                            <x-app-logo-icon class="size-5 fill-current text-[#1B5E20] dark:text-teal-300" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ config('app.name', 'DocuTrust') }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Secure onboarding in minutes') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xl shadow-gray-200/60 backdrop-blur transition-colors duration-300 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30 sm:p-7">
                    <nav class="mb-6 grid grid-cols-2 gap-2 text-[11px] leading-tight sm:grid-cols-4 sm:gap-3 sm:text-xs">
                        <div class="rounded-lg border border-[#2EC4B6] bg-[#2EC4B6] px-2.5 py-2 text-center text-white sm:px-3">{{ __('1. Account Setup') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-2 text-center text-[#1F2937] dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 sm:px-3">{{ __('2. Mobile Verification') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-2 text-center text-[#1F2937] dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 sm:px-3">{{ __('3. eKYC Verification') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-2 text-center text-[#1F2937] dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 sm:px-3">{{ __('4. MFA Setup') }}</div>
                    </nav>

                    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Create your free Signer account') }}</h1>
                    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

                    @php
                        $authInputClasses = 'rounded-xl border-gray-300 bg-white/95 text-base text-[#1F2937] placeholder:text-gray-400 transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400';
                    @endphp

                    <form wire:submit="register" class="mt-6 flex flex-col gap-5 sm:gap-6" x-data="{ showPassword: false }">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input
                                wire:model.live="first_name"
                                id="first_name"
                                label="{{ __('First Name') }}"
                                type="text"
                                name="first_name"
                                required
                                autofocus
                                autocomplete="given-name"
                                placeholder="Enter first name"
                                class="{{ $authInputClasses }}"
                            />
                            <flux:input
                                wire:model.live="middle_name"
                                id="middle_name"
                                label="{{ __('Middle Name - optional') }}"
                                type="text"
                                name="middle_name"
                                autocomplete="additional-name"
                                placeholder="Enter middle name"
                                class="{{ $authInputClasses }}"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <flux:input
                                    wire:model.live="last_name"
                                    id="last_name"
                                    label="{{ __('Last Name') }}"
                                    type="text"
                                    name="last_name"
                                    required
                                    autocomplete="family-name"
                                    placeholder="Enter last name"
                                    class="{{ $authInputClasses }}"
                                />
                            </div>
                            <flux:input
                                wire:model.live="suffix"
                                id="suffix"
                                label="{{ __('Suffix') }}"
                                type="text"
                                name="suffix"
                                autocomplete="honorific-suffix"
                                placeholder="Jr., Sr."
                                class="{{ $authInputClasses }}"
                            />
                        </div>

                        <flux:input
                            wire:model.live="email"
                            id="email"
                            label="{{ __('Email Address') }}"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="email@example.com"
                            inputmode="email"
                            autocapitalize="off"
                            spellcheck="false"
                            class="{{ $authInputClasses }}"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-1.5 block text-sm text-[#1F2937] dark:text-zinc-100">{{ __('Password') }}</label>
                                <div class="relative">
                                    <input
                                        wire:model.live="password"
                                        id="password"
                                        name="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Create a strong password"
                                        class="w-full rounded-xl border border-gray-300 bg-white/95 px-3 py-3 text-base text-[#1F2937] outline-none transition duration-200 placeholder:text-gray-400 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400"
                                    />
                                    <button
                                        type="button"
                                        x-on:click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 inline-flex min-h-11 items-center px-3 text-xs font-medium text-gray-500 transition hover:text-[#1B5E20] dark:text-zinc-400 dark:hover:text-teal-300"
                                    >
                                        <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <flux:input
                                wire:model.live="password_confirmation"
                                id="password_confirmation"
                                label="{{ __('Confirm Password') }}"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Re-enter your password"
                                class="{{ $authInputClasses }}"
                            />
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 transition-colors duration-300 dark:border-zinc-700 dark:bg-zinc-800/70">
                            <flux:checkbox
                                wire:model="agreed_to_terms"
                                id="agreed_to_terms"
                                name="agreed_to_terms"
                                label="{{ __('I agree to the Terms of Use and Privacy Policy') }}"
                                class="accent-[#2EC4B6]"
                            />
                            @error('agreed_to_terms')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-[#1F2937] dark:text-zinc-200">
                                <a href="#" class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Terms of Use') }}</a>
                                {{ __('and') }}
                                <a href="#" class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Privacy Policy') }}</a>
                            </p>
                        </div>

                        <flux:button type="submit" variant="primary" class="w-full bg-[#2EC4B6] text-white transition hover:bg-[#1B5E20] hover:text-white dark:text-black dark:hover:text-black">
                            {{ __('Create account') }}
                        </flux:button>
                    </form>
                </div>

                <div class="mt-4 text-center text-sm text-[#1F2937] dark:text-zinc-200">
                    {{ __('Already have an account?') }}
                    <x-text-link href="{{ route('login') }}" class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Log in') }}</x-text-link>
                </div>
            </div>
        </main>
    </div>
</div>
