<?php

use App\Models\EnotaryInvitation;
use App\Models\User;
use App\Services\EnotaryInvitationService;
use App\Support\AuthSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $token;

    public ?EnotaryInvitation $invitation = null;

    public bool $invalidInvitation = false;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $agreed_to_terms = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = app(EnotaryInvitationService::class)->findPendingByToken($token);

        if ($this->invitation === null) {
            $this->invalidInvitation = true;

            return;
        }

        $parts = preg_split('/\s+/', trim($this->invitation->full_name)) ?: [];
        $this->first_name = $parts[0] ?? '';
        $this->last_name = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        if (count($parts) > 2) {
            $this->middle_name = implode(' ', array_slice($parts, 1, -1));
        }
    }

    public function with(): array
    {
        $existingAccount = $this->invitation !== null
            ? User::query()->where('email', $this->invitation->email)->first()
            : null;

        return [
            'existingAccount' => $existingAccount,
            'authenticatedMatchesInvite' => auth()->check()
                && $this->invitation !== null
                && strtolower((string) auth()->user()?->email) === strtolower($this->invitation->email),
        ];
    }

    public function acceptAsNewUser(): void
    {
        if ($this->invitation === null) {
            return;
        }

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'agreed_to_terms' => ['accepted'],
        ]);

        try {
            $user = app(EnotaryInvitationService::class)->acceptAsNewUser($this->invitation, [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'password' => $validated['password'],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        event(new Registered($user));
        Auth::login($user);
        Session::regenerate();
        Session::forget([
            AuthSession::TWO_FACTOR_PASSED,
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
        ]);

        $this->redirect(route('onboarding.email.verify', absolute: false), navigate: true);
    }

    public function acceptWhileSignedIn(): void
    {
        if ($this->invitation === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user !== null, 401);

        $user = app(EnotaryInvitationService::class)->acceptForAuthenticatedUser($this->invitation, $user);

        Session::put(AuthSession::TWO_FACTOR_PASSED, true);

        $this->redirect(
            route('notary-requests.show', ['notaryRequest' => $this->invitation->notary_request_id], absolute: false),
            navigate: true,
        );
    }
}; ?>

@php
    $authInputClasses = 'rounded-xl border-gray-300 bg-white/95 text-base text-[#1F2937] placeholder:text-gray-400 transition duration-200 focus:border-[#c6a666] focus:ring-2 focus:ring-[#c6a666]/25 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-400';
@endphp

<div class="min-h-screen overflow-x-clip">
    <div class="grid min-h-screen lg:grid-cols-12">
        <aside
            class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end"
            style="background-image: linear-gradient(180deg, rgba(9, 9, 11, 0.35) 0%, rgba(9, 9, 11, 0.85) 100%), url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1400&q=80'); background-size: cover; background-position: center;"
        >
            <div class="absolute inset-0 bg-linear-to-b from-[#0f2b1f]/90 via-[#1a6b48]/85 to-[#1B5E20]/95"></div>
            <div class="relative flex h-full flex-col justify-between p-10">
                <div class="max-w-sm rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-md">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#f3e7d0]">{{ __('Attorney invitation') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">{{ __('Join your e-Notary case') }}</h2>
                    <p class="mt-3 text-sm text-zinc-200/90">{{ __('Your attorney invited you to DocuTrust e-Notary. Set up your account to view the case and complete verification steps.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="grid size-[3.25rem] shrink-0 place-items-stretch rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
                        <x-app-logo-icon class="size-full fill-current text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">{{ config('app.name', 'DocuTrust') }}</p>
                        <p class="text-xs text-zinc-200">{{ __('e-Notary portal') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-6 dark:bg-zinc-950 sm:px-6 sm:py-8 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-lg">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900 sm:p-7">
                    @if ($invalidInvitation)
                        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Invitation unavailable') }}</h1>
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('This link may have expired, already been used, or is invalid. Ask your attorney to send a new invitation.') }}
                        </p>
                        <div class="mt-6">
                            <flux:button href="{{ route('login', ['mode' => 'enotary']) }}" variant="primary" class="!bg-[#123629] !text-[#f7f1e6]">
                                {{ __('Go to e-Notary sign in') }}
                            </flux:button>
                        </div>
                    @else
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8a6b2f] dark:text-[#c6a666]">
                            {{ __('eNotary signer invitation') }}
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Accept invitation') }}</h1>

                        <div class="mt-4 rounded-xl border border-[#c6a666]/30 bg-[#f7f1e6]/60 px-4 py-3 text-sm dark:border-[#c6a666]/20 dark:bg-[#1a2e24]/60">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invitation->notaryRequest?->title }}</p>
                            <p class="mt-1 text-zinc-600 dark:text-zinc-400">
                                {{ __('Invited by :attorney', ['attorney' => $invitation->invitedBy?->name ?? __('Attorney')]) }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Email: :email', ['email' => $invitation->email]) }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Expires: :date', ['date' => $invitation->expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A')]) }}
                            </p>
                        </div>

                        @if ($authenticatedMatchesInvite)
                            <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('You are signed in as :email. Accept to open this notarization case.', ['email' => auth()->user()->email]) }}
                            </p>
                            <flux:button
                                type="button"
                                variant="primary"
                                wire:click="acceptWhileSignedIn"
                                class="mt-6 w-full !bg-[#123629] !text-[#f7f1e6]"
                            >
                                {{ __('Accept & open case') }}
                            </flux:button>
                        @elseif ($existingAccount !== null)
                            <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('An account already exists for this email. Sign in with the e-Notary tab, then open this invitation link again.') }}
                            </p>
                            <flux:button
                                href="{{ route('login', ['mode' => 'enotary']) }}"
                                variant="primary"
                                class="mt-6 w-full !bg-[#123629] !text-[#f7f1e6]"
                            >
                                {{ __('Sign in to e-Notary') }}
                            </flux:button>
                        @else
                            <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Create your eNotary signer account. After setup you will complete email, mobile, eKYC, and MFA verification.') }}
                            </p>

                            <form wire:submit="acceptAsNewUser" class="mt-6 flex flex-col gap-4" x-data="{ showPassword: false }">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <flux:input wire:model="first_name" label="{{ __('First name') }}" required class="{{ $authInputClasses }}" />
                                    <flux:input wire:model="last_name" label="{{ __('Last name') }}" required class="{{ $authInputClasses }}" />
                                    <flux:input wire:model="middle_name" label="{{ __('Middle name') }}" class="{{ $authInputClasses }} sm:col-span-2" />
                                    <flux:input wire:model="suffix" label="{{ __('Suffix') }}" class="{{ $authInputClasses }} sm:col-span-2" />
                                </div>

                                <flux:input
                                    value="{{ $invitation->email }}"
                                    label="{{ __('Email') }}"
                                    type="email"
                                    disabled
                                    class="{{ $authInputClasses }}"
                                />

                                <div class="relative">
                                    <label class="mb-1.5 block text-sm text-zinc-900 dark:text-zinc-100">{{ __('Password') }}</label>
                                    <input
                                        wire:model="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="new-password"
                                        class="login-auth-input w-full rounded-xl border border-gray-300 bg-white/95 py-3 pr-14 pl-3 text-base dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                    <button type="button" x-on:click="showPassword = !showPassword" class="absolute inset-y-0 right-0 top-6 inline-flex items-center px-3 text-xs text-zinc-500">
                                        <span x-text="showPassword ? '{{ __('Hide') }}' : '{{ __('Show') }}'"></span>
                                    </button>
                                    @error('password')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <flux:input
                                    wire:model="password_confirmation"
                                    label="{{ __('Confirm password') }}"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="{{ $authInputClasses }}"
                                />

                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                                    <input wire:model="agreed_to_terms" type="checkbox" class="mt-0.5 size-4 rounded" />
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('I agree to the terms and privacy policy for e-Notary services.') }}</span>
                                </label>
                                @error('agreed_to_terms')
                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                @enderror

                                <flux:button type="submit" variant="primary" class="w-full !bg-[#123629] !text-[#f7f1e6]" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="acceptAsNewUser">{{ __('Create eNotary account & continue') }}</span>
                                    <span wire:loading wire:target="acceptAsNewUser">{{ __('Creating account…') }}</span>
                                </flux:button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </main>
    </div>
</div>
