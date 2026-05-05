@props([
    'activeStep' => 1,
])

@php
    $labels = [
        1 => __('1. Account Setup'),
        2 => __('2. Mobile Verification'),
        3 => __('3. eKYC Verification'),
        4 => __('4. MFA Setup'),
    ];
@endphp

<div class="min-h-screen overflow-x-clip">
    <div class="grid min-h-screen lg:grid-cols-12">
        <aside
            class="relative hidden overflow-hidden lg:col-span-5 lg:flex lg:flex-col lg:justify-end"
            style="background-image: linear-gradient(180deg, rgba(9, 9, 11, 0.35) 0%, rgba(9, 9, 11, 0.85) 100%), url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1400&q=80'); background-size: cover; background-position: center;"
        >
            <div class="absolute inset-0 bg-linear-to-b from-[#2EC4B6]/25 via-[#2EC4B6]/30 to-[#1B5E20]/90"></div>
            <div class="absolute -left-24 top-12 h-56 w-56 rounded-full bg-[#2EC4B6]/30 blur-3xl motion-safe:animate-[pulse-soft_6s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-16 right-6 h-40 w-40 rounded-full bg-[#FFD166]/20 blur-3xl motion-safe:animate-[pulse-soft_8s_ease-in-out_infinite_.5s]"></div>

            <div class="relative flex h-full flex-col justify-between p-10">
                <div class="max-w-sm rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-md motion-safe:animate-[docutrust-onboarding-in_0.55s_ease-out_both]">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#d8fff8]">{{ __('Secure digital onboarding') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
                        {{ __('Built for trust, speed, and seamless signing.') }}
                    </h2>
                    <p class="mt-3 text-sm text-zinc-200/90">
                        {{ __('Complete each step to unlock DocuTrust for your organization.') }}
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

        <main class="col-span-12 flex items-center bg-[#F8FAFC] px-4 py-6 transition-colors duration-300 dark:bg-zinc-950 sm:px-6 sm:py-8 lg:col-span-7 lg:px-10">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-4 rounded-2xl border border-[#2EC4B6]/30 bg-white/80 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-[#2EC4B6]/30 bg-[#2EC4B6]/10 p-2 dark:border-teal-400/30 dark:bg-teal-400/10">
                            <x-app-logo-icon class="size-5 fill-current text-[#1B5E20] dark:text-teal-300" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ config('app.name', 'DocuTrust') }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                {{ __('Step :step of :total', ['step' => $activeStep, 'total' => count($labels)]) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xl shadow-gray-200/60 backdrop-blur transition-colors duration-300 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30 sm:p-7">
                    <nav class="mb-6 grid grid-cols-2 gap-2 text-[11px] leading-tight sm:grid-cols-4 sm:gap-3 sm:text-xs" aria-label="{{ __('Onboarding progress') }}">
                        @foreach ($labels as $num => $label)
                            @php
                                $isCurrent = (int) $activeStep === $num;
                                $isDone = (int) $activeStep > $num;
                            @endphp
                            <div
                                @class([
                                    'flex min-h-[3.25rem] flex-col items-center justify-center gap-1 rounded-lg border px-2.5 py-2.5 text-center transition-all duration-300 ease-out',
                                    'border-[#2EC4B6] bg-[#2EC4B6] text-white shadow-md ring-2 ring-[#2EC4B6]/40 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 motion-safe:scale-[1.02]' => $isCurrent,
                                    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-200' => $isDone && ! $isCurrent,
                                    'border-gray-200 bg-gray-100 text-[#1F2937] dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' => ! $isCurrent && ! $isDone,
                                ])
                            >
                                @if ($isDone && ! $isCurrent)
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white dark:bg-emerald-500">
                                        <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                @elseif ($isCurrent)
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full border border-white/40 bg-white/20 text-[0.65rem] font-bold tabular-nums">{{ $num }}</span>
                                @endif
                                <span class="font-semibold leading-tight">{{ $label }}</span>
                            </div>
                        @endforeach
                    </nav>

                    <div class="motion-safe:animate-[docutrust-onboarding-in_0.45s_ease-out_both]">
                        {{ $slot }}
                    </div>
                </div>

                <div class="mt-4 text-center text-sm text-[#1F2937] dark:text-zinc-200">
                    {{ __('Already have an account?') }}
                    <x-text-link href="{{ route('login') }}" wire:navigate class="text-[#1B5E20] hover:text-[#2EC4B6] hover:underline dark:text-teal-300 dark:hover:text-teal-200">{{ __('Log in') }}</x-text-link>
                </div>
            </div>
        </main>
    </div>
</div>
