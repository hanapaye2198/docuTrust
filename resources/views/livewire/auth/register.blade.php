<?php

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        $user = User::query()->create([
            'name' => $fullName,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Client,
            'workspace' => UserWorkspace::Signing,
            'onboarding_step' => OnboardingStep::EmailVerification,
            'email_verified_at' => null,
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => null,
        ]);

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

@php
    $authInputClasses = 'register-auth-input rounded-xl border-gray-300 bg-white/95 text-base text-[#1F2937] placeholder:text-gray-400 transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400';
    $sidebarGradient = 'linear-gradient(180deg, rgba(9, 9, 11, 0.35) 0%, rgba(9, 9, 11, 0.85) 100%)';
    $sidebarImage = 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1400&q=80';
    $onboardingSteps = [
        ['label' => __('Account'), 'active' => true],
        ['label' => __('Mobile'), 'active' => false],
        ['label' => __('eKYC'), 'active' => false],
        ['label' => __('MFA'), 'active' => false],
    ];
@endphp

<div class="register-shell min-h-screen overflow-x-clip transition-colors duration-500">
    <div class="grid min-h-screen lg:grid-cols-12">
        {{-- Sidebar --}}
        <aside class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end">
            <div
                aria-hidden="true"
                class="absolute inset-0 bg-cover bg-center"
                style="background-image: {{ $sidebarGradient }}, url('{{ $sidebarImage }}');"
            ></div>
            <div class="absolute inset-0 bg-linear-to-b from-[#2EC4B6]/25 via-[#2EC4B6]/30 to-[#1B5E20]/90"></div>
            <div class="absolute -left-24 top-12 h-56 w-56 rounded-full bg-[#2EC4B6]/30 blur-3xl"></div>
            <div class="absolute bottom-16 right-6 h-40 w-40 rounded-full bg-[#FFD166]/20 blur-3xl"></div>

            <div class="relative flex h-full flex-col justify-between p-10 xl:p-12">
                <div class="max-w-sm rounded-2xl border border-[#2EC4B6]/35 bg-white/10 p-6 shadow-2xl shadow-[#2EC4B6]/10 backdrop-blur-md">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#d8fff8]">{{ __('Secure digital onboarding') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold leading-tight text-white">
                        {{ __('Built for trust, speed, and seamless signing.') }}
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-200/90">
                        {{ __('Create your account and complete verification with an enterprise-grade user experience.') }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="grid size-[3.25rem] shrink-0 place-items-stretch rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
                        <x-app-logo-icon class="size-full fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">{{ config('app.name', 'DocuTrust') }}</p>
                        <p class="text-xs text-zinc-200">{{ __('Contracts & agreements') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main content --}}
        <main class="col-span-12 flex items-center justify-center bg-[#F8FAFC] px-4 py-4 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 sm:py-6 lg:col-span-7 lg:px-10 lg:py-6 xl:px-12">
            <div class="register-enter w-full max-w-full sm:max-w-xl lg:max-w-4xl xl:max-w-4xl 2xl:max-w-5xl">
                {{-- Mobile hero --}}
                <div class="relative mb-3 overflow-hidden rounded-2xl border border-white/20 shadow-lg lg:hidden">
                    <div class="relative h-24 sm:h-28">
                        <div
                            aria-hidden="true"
                            class="absolute inset-0 bg-cover bg-center"
                            style="background-image: {{ $sidebarGradient }}, url('{{ $sidebarImage }}');"
                        ></div>
                        <div class="absolute inset-0 bg-linear-to-t from-[#1B5E20]/80 via-[#2EC4B6]/30 to-transparent"></div>
                        <div class="relative flex h-full flex-col justify-between p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/90">{{ __('Document Signer onboarding') }}</p>
                            <div class="flex items-center gap-3">
                                <div class="grid size-10 shrink-0 place-items-stretch rounded-xl border border-white/25 bg-white/15 p-2 backdrop-blur-md">
                                    <x-app-logo-icon class="size-full fill-current text-white" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ config('app.name', 'DocuTrust') }}</p>
                                    <p class="text-xs text-zinc-200/90">{{ __('Secure onboarding in minutes') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-200/60 backdrop-blur transition-colors duration-500 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30 sm:p-6 lg:p-7">
                    <div class="register-accent-bar absolute inset-x-0 top-0 h-1 bg-gradient-to-r" aria-hidden="true"></div>

                    {{-- Onboarding progress --}}
                    <div class="mb-5 mt-1">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                {{ __('Step 1 of 4') }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('~5 minutes total') }}</p>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full w-1/4 rounded-full bg-gradient-to-r from-[#2EC4B6] to-[#1B5E20] transition-all duration-700 ease-out motion-reduce:transition-none"></div>
                        </div>
                        <ol class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-2">
                            @foreach ($onboardingSteps as $index => $step)
                                <li @class([
                                    'flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-2 transition-colors duration-300 sm:px-3',
                                    'register-step-pulse border-[#2EC4B6]/35 bg-[#2EC4B6]/8 dark:border-teal-500/25 dark:bg-teal-500/8' => $step['active'],
                                    'border-gray-200 bg-gray-50/80 dark:border-zinc-700 dark:bg-zinc-800/40' => ! $step['active'],
                                ])>
                                    <span @class([
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                        'bg-[#2EC4B6] text-white' => $step['active'],
                                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $step['active'],
                                    ])>{{ $index + 1 }}</span>
                                    <span @class([
                                        'text-xs font-medium leading-tight',
                                        'text-[#1B5E20] dark:text-teal-300' => $step['active'],
                                        'text-zinc-600 dark:text-zinc-400' => ! $step['active'],
                                    ])>{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <div class="mb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            {{ __('Document signing workspace') }}
                        </p>
                        <h1 class="mt-0.5 text-xl font-semibold text-[#1F2937] dark:text-zinc-100 sm:text-2xl">
                            {{ __('Create your free Client account') }}
                        </h1>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500 sm:text-sm dark:text-zinc-400">
                            {{ __('Profile details first — mobile, identity, and security setup come next.') }}
                        </p>
                    </div>

                    <div class="mb-4 rounded-xl border border-[#2EC4B6]/30 bg-[#2EC4B6]/5 px-3.5 py-2.5 dark:border-teal-500/20 dark:bg-teal-500/5">
                        <p class="text-[11px] font-semibold text-[#1B5E20] dark:text-teal-300">{{ __('What you will need') }}</p>
                        <p class="mt-1 text-[11px] leading-relaxed text-zinc-600 sm:text-xs dark:text-zinc-400">
                            {{ __('Email, mobile number, and a government ID for the steps after signup.') }}
                        </p>
                    </div>

                    <x-auth-session-status class="mb-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

                    <form wire:submit="register" class="flex flex-col gap-4 sm:gap-5" x-data="{ showPassword: false }">
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
                            placeholder="juandelacruz@gmail.com"
                            inputmode="email"
                            autocapitalize="off"
                            spellcheck="false"
                            class="{{ $authInputClasses }}"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-1 block text-sm text-[#1F2937] dark:text-zinc-100">{{ __('Password') }}</label>
                                <div class="relative">
                                    <input
                                        wire:model.live="password"
                                        id="password"
                                        name="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="new-password"
                                        placeholder="{{ __('Create a strong password') }}"
                                        class="register-auth-input w-full rounded-xl border border-gray-300 bg-white/95 py-2.5 pr-14 pl-3 text-base text-[#1F2937] outline-none transition duration-200 placeholder:text-gray-400 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400"
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

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3 transition-colors duration-300 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <input
                                wire:model.boolean="agreed_to_terms"
                                id="agreed_to_terms"
                                name="agreed_to_terms"
                                type="checkbox"
                                class="register-remember-checkbox mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-[#2EC4B6] shadow-sm focus:ring-[#2EC4B6]/30 dark:border-zinc-600 dark:bg-zinc-800 dark:text-teal-400"
                            />
                            <span>
                                <span class="block text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('I agree to the Terms of Use and Privacy Policy') }}</span>
                                @error('agreed_to_terms')
                                    <p class="mt-1 text-xs text-red-500 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    <a href="#" class="font-medium text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Terms of Use') }}</a>
                                    {{ __('and') }}
                                    <a href="#" class="font-medium text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Privacy Policy') }}</a>
                                </p>
                            </span>
                        </label>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="register"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-black/10 bg-[#2EC4B6] px-4 text-sm font-medium text-white shadow-[inset_0px_1px_--theme(--color-white/.2)] transition duration-200 motion-safe:active:scale-[0.98] hover:bg-[#1B5E20] disabled:cursor-wait disabled:opacity-80 dark:border-0 dark:text-black dark:hover:text-black"
                        >
                            <span class="relative inline-flex size-4 shrink-0 items-center justify-center" aria-hidden="true">
                                <svg
                                    wire:loading.remove
                                    wire:target="register"
                                    class="size-4 opacity-90"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                                </svg>
                                <svg
                                    wire:loading
                                    wire:target="register"
                                    class="absolute size-4 motion-safe:animate-spin opacity-90"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span>{{ __('Create account') }}</span>
                        </button>
                    </form>
                </div>

                <div class="mt-4 text-center text-sm text-[#1F2937] dark:text-zinc-200 sm:mt-5">
                    {{ __('Already have an account?') }}
                    <x-text-link href="{{ route('login') }}" class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Log in') }}</x-text-link>
                </div>
            </div>
        </main>
    </div>
</div>
