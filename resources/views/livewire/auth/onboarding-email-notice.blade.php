<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    public function mount(): void
    {
        $this->email = (string) request()->query('email', Auth::user()?->email ?? '');
    }

    public function resend(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('onboarding.start', absolute: false), navigate: true);

            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div class="mx-auto w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
    <x-auth.onboarding-progress current="email" />
    <h1 class="text-2xl font-semibold text-[#1F2937]">{{ __('Verify your email') }}</h1>
    <p class="mt-2 text-sm text-gray-600">
        {{ __('We sent a verification link to :email. Open that link to continue onboarding.', ['email' => $email !== '' ? $email : __('your email address')]) }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ __('A new verification link has been sent.') }}
        </p>
    @endif

    <div class="mt-6 space-y-3">
        @auth
            <flux:button wire:click="resend" type="button" variant="primary" class="w-full">
                {{ __('Resend verification email') }}
            </flux:button>
        @endauth

        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-[#1F2937] hover:bg-gray-50">
            {{ __('Back to sign in') }}
        </a>
    </div>
</div>
