<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-shell min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <main class="app-workspace flex h-dvh min-h-0 flex-col">
            <div class="flex min-h-0 flex-1 flex-col">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200/80 bg-white/90 px-4 py-3 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-950/90">
            <flux:button
                variant="ghost"
                :href="route('documents.show', $document)"
                wire:navigate
                icon="arrow-left"
                class="rounded-xl"
            >
                {{ __('Back') }}
            </flux:button>

            <div class="min-w-0 text-right">
                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->title }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Prepare document') }}</p>
            </div>
        </div>

        @if (session('status'))
            <div class="shrink-0 border-b border-emerald-200/70 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="shrink-0 border-b border-red-200/70 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="shrink-0 border-b border-red-200/70 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
                <p class="font-medium">{{ __('Unable to save signature fields.') }}</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        @if (! $firstSignerId)
            <div class="shrink-0 border-b border-amber-200/70 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                {{ __('Add at least one signer on the document page before placing fields.') }}
            </div>
        @endif

        <div class="grid min-h-0 flex-1 gap-0 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="min-h-0 overflow-y-auto border-b border-zinc-200/80 bg-white/92 p-4 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-950/88 xl:border-b-0 xl:border-r">
                <div class="space-y-5">
                    <section class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 px-4 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Controls') }}</h2>
                            <span id="editor-status" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                                {{ __('Saved') }}
                            </span>
                        </div>

                        @if (count($signers) > 0)
                            <label class="mt-4 flex flex-col gap-1.5">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned signatory') }}</span>
                                <select
                                    id="field-signer"
                                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                >
                                    @foreach ($signers as $signer)
                                        <option value="{{ $signer['id'] }}">{{ $signer['name'] }}{{ $signer['email'] ? ' - '.$signer['email'] : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <div class="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                            <button type="button" id="btn-prev-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Prev') }}</button>
                            <span id="page-indicator" class="text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
                            <button type="button" id="btn-next-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}</button>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @if ($isAttorneySigningPhase ?? false)
                                {{-- Attorney signing phase: single "Save & Sign" button (full width) --}}
                                <button
                                    type="button"
                                    id="btn-save-fields"
                                    class="col-span-2 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    @if (! $firstSignerId) disabled @endif
                                >
                                    {{ __('Save & Sign') }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    id="btn-save-fields"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-500 dark:hover:bg-teal-600"
                                    @if (! $firstSignerId) disabled @endif
                                >
                                    {{ __('Save') }}
                                </button>

                                <form method="POST" action="{{ route(auth()->user()?->role->value === 'notary' ? 'notary.documents.send' : 'documents.send', $document) }}" class="contents">
                                    @csrf
                                    @if ($document->notary_request_id !== null)
                                        <button
                                            type="submit"
                                            id="btn-send-to-signer"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                            @if (! $canSend) disabled @endif
                                        >
                                            {{ __('Send to Signers') }}
                                        </button>
                                    @else
                                        <button
                                            type="submit"
                                            id="btn-send-to-signer"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                            @if (! $canSend) disabled @endif
                                        >
                                            {{ __('Send') }}
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </section>

                    @php
                        $fieldControlIcons = [
                            'signature' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H3Zm0 0h14M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'signature_left' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H3Zm0 0h10M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'signature_right' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H6Zm-3 0h14M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'text' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" d="M4 5.5h12M10 5.5v9M6.5 14.5h7"/></svg>',
                            'date' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><rect x="3.5" y="4.5" width="13" height="12" rx="2"/><path stroke-linecap="round" d="M6.5 3.5v3M13.5 3.5v3M3.5 8h13"/></svg>',
                            'email' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><rect x="3" y="5" width="14" height="10" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 6.5 5.5 4 5.5-4"/></svg>',
                            'name' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path stroke-linecap="round" d="M4.5 15.5c1.4-2.2 3.2-3.3 5.5-3.3s4.1 1.1 5.5 3.3"/></svg>',
                            'initials' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" d="M4.5 14.5v-9m0 4.5h4M10 14.5l2.3-9 2.2 9M11 11.5h2.7"/></svg>',
                        ];
                    @endphp

                    @if (count($signers) > 1 && ! ($isAttorneySigningPhase ?? false))
                    <section id="signer-page-assignments" class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 px-4 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <button type="button" id="toggle-page-assignments" class="flex w-full items-center justify-between gap-2 text-left">
                            <h2 class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Signer page assignments') }}</h2>
                            <svg id="page-assignments-chevron" class="size-4 text-zinc-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                        </button>
                        <div id="page-assignments-body" class="mt-3 hidden space-y-3">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Configure which pages each signer can sign on. The signer dropdown will filter based on the current page.') }}</p>
                            <div id="page-assignments-list" class="space-y-3">
                                {{-- Dynamically populated by JS after PDF page count is known --}}
                            </div>
                            <button type="button" id="btn-save-page-assignments" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-700 transition hover:bg-teal-100 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-300 dark:hover:bg-teal-950/60">
                                {{ __('Save assignments') }}
                            </button>
                        </div>
                    </section>
                    @endif

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Standard fields') }}</h2>
                        <div class="mt-3 space-y-2">
                            <button type="button" draggable="true" data-field-type="signature" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300">{!! $fieldControlIcons['signature'] !!}</span><span>{{ __('Signature') }}</span></button>
                            <button type="button" draggable="true" data-field-type="signature_left" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300">{!! $fieldControlIcons['signature_left'] !!}</span><span>{{ __('Signature (Left aligned)') }}</span></button>
                            <button type="button" draggable="true" data-field-type="signature_right" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300">{!! $fieldControlIcons['signature_right'] !!}</span><span>{{ __('Signature (Right aligned)') }}</span></button>
                            <button type="button" draggable="true" data-field-type="text" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-amber-300 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-amber-700 dark:hover:bg-amber-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">{!! $fieldControlIcons['text'] !!}</span><span>{{ __('Text Field') }}</span></button>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Auto-fill fields') }}</h2>
                        <div class="mt-3 space-y-2">
                            <button type="button" draggable="true" data-field-type="date" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-violet-300 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-violet-700 dark:hover:bg-violet-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">{!! $fieldControlIcons['date'] !!}</span><span>{{ __('Date Signed') }}</span></button>
                            <button type="button" draggable="true" data-field-type="email" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-rose-700 dark:hover:bg-rose-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">{!! $fieldControlIcons['email'] !!}</span><span>{{ __('Email') }}</span></button>
                            <button type="button" draggable="true" data-field-type="name" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{!! $fieldControlIcons['name'] !!}</span><span>{{ __('Name') }}</span></button>
                            <button type="button" draggable="true" data-field-type="initials" class="field-palette-btn flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-fuchsia-300 hover:bg-fuchsia-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-fuchsia-700 dark:hover:bg-fuchsia-950/40" @if (! $firstSignerId) disabled @endif><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/60 dark:text-fuchsia-300">{!! $fieldControlIcons['initials'] !!}</span><span>{{ __('Initials') }}</span></button>
                        </div>
                    </section>

                    <section id="field-inspector" class="rounded-2xl border border-zinc-200/80 bg-white/90 px-4 py-4 text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-900/80">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Selected field') }}</h2>
                            <span id="field-inspector-empty" class="text-xs font-medium uppercase tracking-[0.14em] text-zinc-400">{{ __('None') }}</span>
                        </div>
                        <div id="field-inspector-body" class="mt-4 hidden space-y-4">
                            <label class="flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Field type') }}</span>
                                <select id="selected-field-type" class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                    <option value="signature">{{ __('Signature') }}</option>
                                    <option value="signature_left">{{ __('Signature (Left aligned)') }}</option>
                                    <option value="signature_right">{{ __('Signature (Right aligned)') }}</option>
                                    <option value="text">{{ __('Text Field') }}</option>
                                    <option value="date">{{ __('Date Signed') }}</option>
                                    <option value="email">{{ __('Email') }}</option>
                                    <option value="name">{{ __('Name') }}</option>
                                    <option value="initials">{{ __('Initials') }}</option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned signatory') }}</span>
                                <select id="selected-field-signer" class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                    @foreach ($signers as $signer)
                                        <option value="{{ $signer['id'] }}">{{ $signer['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Rotation') }}</span>
                                <div class="flex items-center gap-2">
                                    <input type="number" id="selected-field-angle" min="0" max="360" step="1" value="0" class="w-20 rounded-lg border border-zinc-300 bg-white px-2.5 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                                    <span class="text-xs text-zinc-400">°</span>
                                    <button type="button" id="btn-rotate-0" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-[10px] font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">0°</button>
                                    <button type="button" id="btn-rotate-90" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-[10px] font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">90°</button>
                                    <button type="button" id="btn-rotate-180" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-[10px] font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">180°</button>
                                    <button type="button" id="btn-rotate-270" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-[10px] font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">270°</button>
                                </div>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btn-duplicate-field" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Duplicate') }}</button>
                                <button type="button" id="btn-delete-field" class="rounded-xl border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/30">{{ __('Delete') }}</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btn-bring-forward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Bring forward') }}</button>
                                <button type="button" id="btn-send-backward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Send backward') }}</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btn-bring-forward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Bring forward') }}</button>
                                <button type="button" id="btn-send-backward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Send backward') }}</button>
                            </div>
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Tip: use Ctrl/Cmd + C and Ctrl/Cmd + V to copy and paste the selected field.') }}</p>
                        </div>
                    </section>
                </div>
            </aside>

            <section id="pdf-stage" class="flex min-h-0 min-w-0 flex-col bg-[linear-gradient(180deg,rgba(250,250,250,0.98),rgba(244,244,245,0.98))] dark:bg-zinc-950">
                <div id="pdf-load-error" class="m-4 mb-0 hidden rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"></div>
                <div class="flex min-h-0 flex-1 items-start justify-center overflow-auto p-4 sm:p-6">
                    <div id="pdf-shell" class="relative inline-block min-h-[200px] min-w-[200px] shrink-0 rounded-2xl bg-white ring-1 ring-zinc-200/80 shadow-[0_18px_50px_rgba(15,23,42,0.08)] dark:bg-zinc-950 dark:ring-zinc-700/80">
                        <div
                            id="pdf-loading-indicator"
                            class="absolute inset-0 z-30 flex items-center justify-center rounded-2xl bg-white/88 px-6 text-center backdrop-blur-sm dark:bg-zinc-950/88"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <div class="w-full max-w-xs space-y-4">
                                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-zinc-200 border-t-teal-500 dark:border-zinc-700 dark:border-t-teal-400"></div>
                                <div class="space-y-1.5">
                                    <p id="pdf-loading-label" class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Loading document...') }}</p>
                                    <p id="pdf-loading-progress" class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Preparing secure preview') }}</p>
                                </div>
                            </div>
                        </div>
                        <canvas id="pdf-canvas" class="relative z-0 block max-w-none rounded-xl bg-white shadow-sm"></canvas>
                        <canvas id="fabric-canvas" class="absolute left-0 top-0 z-20 block"></canvas>
                    </div>
                </div>
            </section>
        </div>

        <form id="save-fields-form" method="POST" action="{{ route(auth()->user()?->role->value === 'notary' ? 'notary.documents.signature-fields.store' : 'documents.signature-fields.store', $document) }}" class="hidden">
            @csrf
            <input type="hidden" name="fields" id="fields-payload" value="[]" />
        </form>
            </div>
        </main>

        @include('partials.idle-session')

        <script id="template-prepare-config" type="application/json">
            {!! json_encode([
                'pdfUrl' => $pdfUrl,
                'firstSignerId' => $firstSignerId,
                'signers' => $signers,
                'initialFields' => $initialFields,
                'initialPage' => (int) request()->query('page', 1) ?: 1,
                'signerPagesUrl' => route(auth()->user()?->role->value === 'notary' ? 'notary.documents.signer-pages.store' : 'documents.signer-pages.store', $document),
                'messages' => [
                    'saved' => __('Saved'),
                    'saving' => __('Saving...'),
                    'unsaved' => __('Unsaved changes'),
                    'none' => __('None'),
                    'selected' => __('Selected'),
                    'loadingDocument' => __('Loading document...'),
                    'loadingProgress' => __('Preparing secure preview'),
                    'renderingPage' => __('Rendering page :page of :total...', ['page' => '__PAGE__', 'total' => '__TOTAL__']),
                    'previewLoading' => __('Preview still loading. Please wait a second, then try again.'),
                    'noSigner' => __('No signer found. Add at least one signer first.'),
                    'loadFailed' => __('Unable to load document preview. Please refresh the page and try again.'),
                    'saveBeforeSend' => __('Save your latest field changes before sending to signer.'),
                    'pageAssignmentsSaved' => __('Page assignments saved.'),
                    'pageAssignmentsFailed' => __('Failed to save page assignments.'),
                    'signerNotAllowedOnPage' => __('This signer is not assigned to this page.'),
                ],
            ]) !!}
        </script>

        @stack('scripts')
        @fluxScripts
    </body>
</html>
