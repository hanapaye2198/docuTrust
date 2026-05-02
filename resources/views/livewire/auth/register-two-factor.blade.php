<?php

use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use App\Models\User;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component
{
    public string $code = '';

    public string $qrInlineUrl = '';

    public string $qrFallbackUrl = '';

    public string $pendingEmail = '';

    public function mount(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        $pending = Session::get(AuthSession::REGISTER_PENDING_DATA);
        $isValidPendingRegistration = is_array($pending)
            && isset($pending['name'], $pending['email'], $pending['password'], $pending['role']);

        if ($user !== null) {
            if (! $user->needsTwoFactorOnboarding()) {
                $this->redirect($user->intendedHomeUrl(), navigate: true);

                return;
            }

            $sessionUserId = Session::get(AuthSession::REGISTER_TWO_FACTOR_USER_ID);
            $secret = Session::get(AuthSession::REGISTER_TWO_FACTOR_SECRET);

            if ($sessionUserId !== $user->id || ! is_string($secret) || $secret === '') {
                $secret = $twoFactor->generateSecretKey();
                Session::put([
                    AuthSession::REGISTER_TWO_FACTOR_USER_ID => $user->id,
                    AuthSession::REGISTER_TWO_FACTOR_SECRET => $secret,
                ]);
            }

            $this->pendingEmail = $user->email;
            $this->setQrCodeUrls($twoFactor, $user->email, $secret);

            return;
        }

        if (! $isValidPendingRegistration) {
            $this->redirect(route('register'), navigate: true);

            return;
        }

        $secret = Session::get(AuthSession::REGISTER_TWO_FACTOR_SECRET);
        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactor->generateSecretKey();
            Session::put(AuthSession::REGISTER_TWO_FACTOR_SECRET, $secret);
        }

        $email = (string) $pending['email'];
        $this->pendingEmail = $email;
        $this->setQrCodeUrls($twoFactor, $email, $secret);
    }

    public function verify(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        $pending = Session::get(AuthSession::REGISTER_PENDING_DATA);
        $isValidPendingRegistration = is_array($pending)
            && isset($pending['name'], $pending['email'], $pending['password'], $pending['role']);

        if ($user !== null && ! $user->needsTwoFactorOnboarding()) {
            return;
        }

        if ($user === null && ! $isValidPendingRegistration) {
            $this->redirect(route('register'), navigate: true);

            return;
        }

        $actorId = $user?->id ?? crc32((string) ($pending['email'] ?? 'guest-register'));

        $this->ensureVerifyIsNotRateLimited($actorId);

        $this->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        $secret = Session::get(AuthSession::REGISTER_TWO_FACTOR_SECRET);
        if (! is_string($secret) || $secret === '') {
            RateLimiter::hit($this->verifyThrottleKey($actorId));

            throw ValidationException::withMessages([
                'code' => __('Refresh this page and scan the QR code again.'),
            ]);
        }

        if (! $twoFactor->verifyRawSecret($secret, $this->code)) {
            RateLimiter::hit($this->verifyThrottleKey($actorId));

            throw ValidationException::withMessages([
                'code' => __('Invalid code.'),
            ]);
        }

        RateLimiter::clear($this->verifyThrottleKey($actorId));

        if ($user !== null) {
            $user->update([
                'two_factor_secret' => $secret,
                'two_factor_enabled' => true,
                'two_factor_onboarding_completed_at' => now(),
            ]);

            Session::forget([
                AuthSession::REGISTER_TWO_FACTOR_SECRET,
                AuthSession::REGISTER_TWO_FACTOR_USER_ID,
            ]);

            $this->redirect($user->fresh()->intendedHomeUrl(), navigate: true);

            return;
        }

        $email = (string) $pending['email'];
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Email already exists. Start registration again.'),
            ]);
        }

        /** @var UserRole $role */
        $role = UserRole::from((string) $pending['role']);
        $registeredUser = User::query()->create([
            'name' => (string) $pending['name'],
            'email' => $email,
            'password' => (string) $pending['password'],
            'role' => $role,
            'organization_role' => OrganizationRole::Admin,
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        event(new Registered($registeredUser));
        Auth::login($registeredUser);
        Session::regenerate();

        Session::forget([
            AuthSession::REGISTER_PENDING_DATA,
            AuthSession::REGISTER_TWO_FACTOR_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_USER_ID,
        ]);

        $this->redirect($registeredUser->fresh()->intendedHomeUrl(), navigate: true);
    }

    public function skip(): void
    {
        $user = Auth::user();
        $pending = Session::get(AuthSession::REGISTER_PENDING_DATA);
        $isValidPendingRegistration = is_array($pending)
            && isset($pending['name'], $pending['email'], $pending['password'], $pending['role']);

        if ($user !== null) {
            if (! $user->needsTwoFactorOnboarding()) {
                return;
            }

            $user->update([
                'two_factor_enabled' => false,
                'two_factor_onboarding_completed_at' => now(),
            ]);

            Session::forget([
                AuthSession::REGISTER_TWO_FACTOR_SECRET,
                AuthSession::REGISTER_TWO_FACTOR_USER_ID,
            ]);

            $this->redirect($user->fresh()->intendedHomeUrl(), navigate: true);

            return;
        }

        if (! $isValidPendingRegistration) {
            $this->redirect(route('register'), navigate: true);

            return;
        }

        $email = (string) $pending['email'];
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Email already exists. Start registration again.'),
            ]);
        }

        /** @var UserRole $role */
        $role = UserRole::from((string) $pending['role']);
        $registeredUser = User::query()->create([
            'name' => (string) $pending['name'],
            'email' => $email,
            'password' => (string) $pending['password'],
            'role' => $role,
            'organization_role' => OrganizationRole::Admin,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        event(new Registered($registeredUser));
        Auth::login($registeredUser);
        Session::regenerate();

        Session::forget([
            AuthSession::REGISTER_PENDING_DATA,
            AuthSession::REGISTER_TWO_FACTOR_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_USER_ID,
        ]);

        $this->redirect($registeredUser->fresh()->intendedHomeUrl(), navigate: true);
    }

    protected function ensureVerifyIsNotRateLimited(int $userId): void
    {
        if (! RateLimiter::tooManyAttempts($this->verifyThrottleKey($userId), 10)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->verifyThrottleKey($userId));

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function verifyThrottleKey(int $userId): string
    {
        return 'register-2fa-verify|'.$userId.'|'.Str::lower((string) request()->ip());
    }

    protected function setQrCodeUrls(TwoFactorAuthenticationService $twoFactor, string $email, string $secret): void
    {
        $qrData = $twoFactor->registrationQrCodeData($email, $secret);

        $this->qrInlineUrl = $qrData['inline'];
        $this->qrFallbackUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data='.rawurlencode($qrData['uri']);
    }
}; ?>

<div class="min-h-screen">
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
                        <div class="rounded-xl border border-white/20 bg-white/10 p-2.5 backdrop-blur-md">
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

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-2xl">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-200/60 backdrop-blur sm:p-7">
                    <nav class="mb-6 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                        <div class="rounded-lg border border-[#2EC4B6] bg-[#2EC4B6] p-2 text-white">{{ __('1. Account Setup') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 p-2 text-[#1F2937]">{{ __('2. Mobile Verification') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 p-2 text-[#1F2937]">{{ __('3. eKYC Verification') }}</div>
                        <div class="rounded-lg border border-gray-200 bg-gray-100 p-2 text-[#1F2937]">{{ __('4. MFA Setup') }}</div>
                    </nav>

                    <h1 class="text-2xl font-semibold text-[#1F2937]">{{ __('Create your free Signer account') }}</h1>
                    <p class="mt-2 text-sm text-[#1F2937]/80">
                        {{ __('Enter your 6-digit authenticator code first. You will proceed to Mobile Verification after this step.') }}
                    </p>

                    <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <ol class="list-decimal space-y-2 ps-5 text-sm text-[#1F2937]">
                            <li>{{ __('Install Google Authenticator or another TOTP app.') }}</li>
                            <li>{{ __('Scan the QR code below with your app.') }}</li>
                            <li>{{ __('Enter the 6-digit code to confirm.') }}</li>
                        </ol>
                    </div>

                    @if ($qrInlineUrl !== '')
                        <div class="mt-5 flex justify-center" data-qr-block>
                            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm" data-qr-wrapper>
                                <img
                                    src="{{ $qrInlineUrl }}"
                                    data-fallback-src="{{ $qrFallbackUrl }}"
                                    onerror="if (this.dataset.fallbackSrc && this.src !== this.dataset.fallbackSrc) { this.src = this.dataset.fallbackSrc; return; } this.closest('[data-qr-wrapper]')?.classList.add('hidden'); this.closest('[data-qr-block]')?.querySelector('[data-qr-error]')?.classList.remove('hidden');"
                                    alt="{{ __('Authenticator QR code') }}"
                                    width="280"
                                    height="280"
                                    class="mx-auto max-h-[min(280px,70vw)] w-auto max-w-full"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <p data-qr-error class="hidden max-w-sm rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                {{ __('Unable to load QR image automatically. Use the setup key below or refresh the page.') }}
                            </p>
                        </div>
                    @endif

                    @if (session()->has(\App\Support\AuthSession::REGISTER_TWO_FACTOR_SECRET))
                        <details class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-medium text-[#1F2937]">
                                {{ __('Can’t scan? Show setup key') }}
                            </summary>
                            <p class="mt-3 text-xs text-[#1F2937]/80">
                                {{ __('Enter this key manually in your authenticator app.') }}
                            </p>
                            <code class="mt-2 block break-all rounded-md bg-white px-3 py-2 font-mono text-sm text-[#1F2937] border border-gray-200">{{ session(\App\Support\AuthSession::REGISTER_TWO_FACTOR_SECRET) }}</code>
                        </details>
                    @endif

                    <form wire:submit="verify" class="mt-6 flex flex-col gap-4">
                        <div
                            x-data="{
                                codeModel: @entangle('code'),
                                digits: ['', '', '', '', '', ''],
                                get inputs() {
                                    return this.$el.querySelectorAll('.otp-input');
                                },
                                syncFromModel() {
                                    const clean = String(this.codeModel ?? '').replace(/\D/g, '').slice(0, 6);
                                    this.digits = Array.from({ length: 6 }, (_, i) => clean[i] ?? '');
                                },
                                updateModel() {
                                    this.codeModel = this.digits.join('');
                                },
                                focusInput(index) {
                                    this.$nextTick(() => {
                                        this.inputs[index]?.focus();
                                    });
                                },
                                onInput(index, event) {
                                    const value = event.target.value.replace(/\D/g, '');
                                    this.digits[index] = value ? value.slice(-1) : '';
                                    this.updateModel();

                                    if (this.digits[index] !== '' && index < 5) {
                                        this.focusInput(index + 1);
                                    }
                                },
                                onKeydown(index, event) {
                                    if (event.key === 'Backspace' && this.digits[index] === '' && index > 0) {
                                        this.focusInput(index - 1);
                                    }
                                },
                                onPaste(event) {
                                    event.preventDefault();
                                    const pasted = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 6);

                                    if (pasted === '') {
                                        return;
                                    }

                                    for (let i = 0; i < 6; i++) {
                                        this.digits[i] = pasted[i] ?? '';
                                    }

                                    this.updateModel();
                                    const focusIndex = Math.min(pasted.length, 6) - 1;
                                    this.focusInput(Math.max(focusIndex, 0));
                                },
                            }"
                            x-init="syncFromModel()"
                            x-effect="if ((codeModel ?? '') !== digits.join('')) { syncFromModel(); }"
                        >
                            <p class="mb-2 text-sm text-[#1F2937]">{{ __('Authentication code') }}</p>
                            <div class="grid grid-cols-6 gap-3 sm:gap-4" @paste="onPaste($event)">
                                <template x-for="index in 6" :key="index">
                                    <input
                                        x-model="digits[index - 1]"
                                        x-on:input="onInput(index - 1, $event)"
                                        x-on:keydown="onKeydown(index - 1, $event)"
                                        x-on:focus="$event.target.select()"
                                        :data-otp-index="index - 1"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        maxlength="1"
                                        class="otp-input h-14 rounded-xl border-2 border-gray-300 bg-white text-center text-xl font-semibold text-[#1F2937] outline-none transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/30 sm:h-16 sm:text-2xl"
                                        required
                                    />
                                </template>
                            </div>
                            <input type="hidden" wire:model="code" />
                        </div>

                        <flux:error name="code" />

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#2EC4B6] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1B5E20] focus:outline-none focus:ring-2 focus:ring-[#2EC4B6]/40">
                            {{ __('Verify and continue') }}
                        </button>
                    </form>

                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <flux:button type="button" variant="ghost" class="w-full" wire:click="skip">
                            {{ __('Skip for now') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
