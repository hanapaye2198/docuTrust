<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-100 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
        @php
                        $fieldControlIcons = [
                            'signature' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H3Zm0 0h14M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'signature_left' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H3Zm0 0h10M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'signature_right' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 14.5c2.2-3.2 5.1-5.7 7.7-5.7 1.6 0 2.5.8 2.5 2 0 2.3-2.9 4.2-5.4 4.2H6Zm-3 0h14M12.7 5.1l1.8-1.8a1.8 1.8 0 0 1 2.5 2.5l-1.8 1.8"/></svg>',
                            'text' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" d="M4 5.5h12M10 5.5v9M6.5 14.5h7"/></svg>',
                            'date' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><rect x="3.5" y="4.5" width="13" height="12" rx="2"/><path stroke-linecap="round" d="M6.5 3.5v3M13.5 3.5v3M3.5 8h13"/></svg>',
                            'email' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><rect x="3" y="5" width="14" height="10" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 6.5 5.5 4 5.5-4"/></svg>',
                            'name' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path stroke-linecap="round" d="M4.5 15.5c1.4-2.2 3.2-3.3 5.5-3.3s4.1 1.1 5.5 3.3"/></svg>',
                            'seal' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3.5h4l.7 5.4a3.8 3.8 0 0 1-5.4 0L8 3.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 11.5h9l1 5h-11l1-5Z"/><path stroke-linecap="round" d="M7 14.5h6"/></svg>',
                            'initials' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" d="M4.5 14.5v-9m0 4.5h4M10 14.5l2.3-9 2.2 9M11 11.5h2.7"/></svg>',
                        ];
                    @endphp

        <main class="flex h-dvh min-h-0 flex-col">
            <header class="flex shrink-0 items-center justify-between gap-4 border-b border-zinc-200 bg-white px-5 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="min-w-0">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('documents.show', $document)"
                        wire:navigate
                        icon="arrow-left"
                    >
                        {{ __('Back') }}
                    </flux:button>
                    <h1 class="mt-2 text-lg font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Prepare document') }}</h1>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('Drag fields onto the document. Assign each to a signer.') }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <span id="editor-status" class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ __('Saved') }}
                    </span>
                    <p class="hidden max-w-64 truncate text-sm font-medium text-zinc-700 dark:text-zinc-200 sm:block">{{ $document->title }}</p>
                </div>
            </header>

            @if (session('status'))
                <div class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-5 py-2 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="shrink-0 border-b border-red-200 bg-red-50 px-5 py-2 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="shrink-0 border-b border-red-200 bg-red-50 px-5 py-2 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
                    <span class="font-medium">{{ __('Unable to save signature fields.') }}</span>
                    {{ $errors->first() }}
                </div>
            @endif

            @if (! $firstSignerId)
                <div class="shrink-0 border-b border-amber-200 bg-amber-50 px-5 py-2 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('Add at least one signer on the document page before placing fields.') }}
                </div>
            @endif

            @if ($attorneySigningLocked ?? false)
                <div class="shrink-0 border-b border-indigo-200 bg-indigo-50 px-5 py-2 text-sm text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-100">
                    {{ __('Attorney signing is locked until the video conference is completed.') }}
                </div>
            @endif

            <div class="grid min-h-0 flex-1 gap-3 p-4 lg:grid-cols-[72px_minmax(0,1fr)_300px]">
                <aside class="order-2 flex gap-2 overflow-x-auto rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:order-1 lg:min-h-0 lg:flex-col lg:overflow-y-auto">
                    @foreach ([
                        ['type' => 'signature', 'label' => __('Signature'), 'icon' => 'signature'],
                        ['type' => 'date', 'label' => __('Date'), 'icon' => 'date'],
                        ['type' => 'initials', 'label' => __('Initial'), 'icon' => 'initials'],
                        ['type' => 'text', 'label' => __('Text'), 'icon' => 'text'],
                        ['type' => 'seal', 'label' => __('Seal'), 'icon' => 'seal'],
                    ] as $tool)
                        <button
                            type="button"
                            draggable="true"
                            data-field-type="{{ $tool['type'] }}"
                            class="field-palette-btn flex min-w-16 flex-col items-center justify-center gap-1 rounded-xl px-2 py-3 text-[11px] font-medium text-zinc-600 transition hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-300 dark:hover:bg-blue-950/30 dark:hover:text-blue-300"
                            @if (! $firstSignerId || ($attorneySigningLocked ?? false)) disabled @endif
                        >
                            <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{!! $fieldControlIcons[$tool['icon']] !!}</span>
                            {{ $tool['label'] }}
                        </button>
                    @endforeach
                </aside>

                <section id="pdf-stage" class="order-1 flex min-h-0 min-w-0 flex-col rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:order-2">
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-prev-page" class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800" aria-label="{{ __('Previous page') }}">&lsaquo;</button>
                            <span id="page-indicator" class="min-w-24 text-center text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Page') }} 1 / 1</span>
                            <button type="button" id="btn-next-page" class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800" aria-label="{{ __('Next page') }}">&rsaquo;</button>
                        </div>

                        <div class="flex items-center gap-3 text-zinc-800 dark:text-zinc-100">
                            <button type="button" id="btn-zoom-out" class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800" aria-label="{{ __('Zoom out') }}">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true">
                                    <circle cx="8.5" cy="8.5" r="5.25" />
                                    <path stroke-linecap="round" d="M6.5 8.5h4M12.5 12.5 16 16" />
                                </svg>
                            </button>
                            <span id="zoom-indicator" class="min-w-12 text-center text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('100%') }}</span>
                            <button type="button" id="btn-zoom-in" class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800" aria-label="{{ __('Zoom in') }}">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true">
                                    <circle cx="8.5" cy="8.5" r="5.25" />
                                    <path stroke-linecap="round" d="M6.5 8.5h4M8.5 6.5v4M12.5 12.5 16 16" />
                                </svg>
                            </button>
                            <button type="button" id="btn-zoom-reset" class="sr-only">{{ __('Default zoom') }}</button>
                        </div>
                    </div>

                    <div id="pdf-load-error" class="m-4 mb-0 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"></div>
                    <div class="flex min-h-0 flex-1 items-start justify-center overflow-auto bg-slate-50 p-6 dark:bg-zinc-950/70">
                        <div id="pdf-shell" class="relative inline-block min-h-[200px] min-w-[200px] shrink-0 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.12)] ring-1 ring-zinc-200 dark:bg-zinc-950 dark:ring-zinc-700">
                            <div
                                id="pdf-loading-indicator"
                                class="absolute inset-0 z-30 flex items-center justify-center bg-white/90 px-6 text-center backdrop-blur-sm dark:bg-zinc-950/90"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                            >
                                <div class="w-full max-w-xs space-y-4">
                                    <div class="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-500 dark:border-zinc-700 dark:border-t-blue-400"></div>
                                    <div class="space-y-1.5">
                                        <p id="pdf-loading-label" class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Loading document...') }}</p>
                                        <p id="pdf-loading-progress" class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Preparing secure preview') }}</p>
                                    </div>
                                </div>
                            </div>
                            <canvas id="pdf-canvas" class="relative z-0 block max-w-none bg-white"></canvas>
                            <canvas id="fabric-canvas" class="absolute left-0 top-0 z-20 block"></canvas>
                        </div>
                    </div>
                </section>

                <aside class="order-3 min-h-0 overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="space-y-5">
                        <section>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned To') }}</p>
                            @if (count($signers) > 0)
                                <select
                                    id="field-signer"
                                    class="sr-only"
                                    aria-label="{{ __('Assigned signatory') }}"
                                >
                                    @foreach ($signers as $signer)
                                        <option value="{{ $signer['id'] }}">{{ $signer['name'] }}{{ $signer['email'] ? ' - '.$signer['email'] : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-3 space-y-2">
                                    @foreach ($signers as $index => $signer)
                                        <button
                                            type="button"
                                            data-signer-id="{{ $signer['id'] }}"
                                            class="signer-assignment-btn flex w-full items-center gap-3 rounded-xl border border-zinc-100 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-zinc-800 dark:hover:border-blue-900 dark:hover:bg-blue-950/20"
                                            aria-pressed="false"
                                        >
                                            <span data-signer-color-dot @class([
                                                'size-3 rounded-full',
                                                'bg-blue-500' => $index % 3 === 0,
                                                'bg-violet-500' => $index % 3 === 1,
                                                'bg-emerald-500' => $index % 3 === 2,
                                            ])></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $signer['name'] }}</span>
                                                <span class="block truncate text-xs text-zinc-500">{{ $signer['email'] }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        @if (count($signers) > 1 && ! ($isAttorneySigningPhase ?? false))
                            <section id="signer-page-assignments" class="rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-950/50">
                                <button type="button" id="toggle-page-assignments" class="flex w-full items-center justify-between gap-2 text-left">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Page assignments') }}</span>
                                    <svg id="page-assignments-chevron" class="size-4 text-zinc-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                                </button>
                                <div id="page-assignments-body" class="mt-3 hidden space-y-3">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Restrict which pages each signer can sign on.') }}</p>
                                    <div id="page-assignments-list" class="space-y-3"></div>
                                    <button type="button" id="btn-save-page-assignments" class="inline-flex w-full items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
                                        {{ __('Save assignments') }}
                                    </button>
                                </div>
                            </section>
                        @endif

                        <section id="field-inspector" class="rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-950/50">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Selected field') }}</h2>
                                <span id="field-inspector-empty" class="text-xs font-medium uppercase tracking-[0.14em] text-zinc-400">{{ __('None') }}</span>
                            </div>
                            <div id="field-inspector-body" class="mt-4 hidden space-y-4">
                                <label class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Field type') }}</span>
                                    <select id="selected-field-type" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        <option value="signature">{{ __('Signature') }}</option>
                                        <option value="signature_left">{{ __('Signature (Left aligned)') }}</option>
                                        <option value="signature_right">{{ __('Signature (Right aligned)') }}</option>
                                        <option value="text">{{ __('Text Field') }}</option>
                                        <option value="seal">{{ __('Seal') }}</option>
                                        <option value="date">{{ __('Date Signed') }}</option>
                                        <option value="email">{{ __('Email') }}</option>
                                        <option value="name">{{ __('Name') }}</option>
                                        <option value="initials">{{ __('Initials') }}</option>
                                    </select>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned signatory') }}</span>
                                    <select id="selected-field-signer" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        @foreach ($signers as $signer)
                                            <option value="{{ $signer['id'] }}">{{ $signer['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Rotation') }}</span>
                                    <div class="flex items-center gap-2">
                                        <input type="number" id="selected-field-angle" min="0" max="360" step="1" value="0" class="w-20 rounded-lg border border-zinc-200 bg-white px-2.5 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                                        <span class="text-xs text-zinc-400">°</span>
                                    </div>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" id="btn-rotate-0" class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">0°</button>
                                    <button type="button" id="btn-rotate-90" class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">90°</button>
                                    <button type="button" id="btn-rotate-180" class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">180°</button>
                                    <button type="button" id="btn-rotate-270" class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">270°</button>
                                </div>
                                <button type="button" id="btn-delete-field" class="w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-300">{{ __('Delete') }}</button>
                            </div>
                        </section>

                        <p class="rounded-xl bg-zinc-50 p-3 text-xs leading-relaxed text-zinc-500 dark:bg-zinc-950/50 dark:text-zinc-400">{{ __('Fields auto-route in order. Drag any field onto the document to reposition.') }}</p>
                    </div>
                </aside>
            </div>

            <div class="shrink-0 border-t border-zinc-200 bg-white px-5 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-center justify-end gap-3">
                    @if ($isAttorneySigningPhase ?? false)
                        <button
                            type="button"
                            id="btn-save-fields"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @if (! $firstSignerId || ($attorneySigningLocked ?? false)) disabled @endif
                            @if ($attorneySigningLocked ?? false) title="{{ __('Attorney signing is locked until the video conference is completed.') }}" @endif
                        >
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 3.5h8l3 3v10h-11v-13Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5v5h6v-5M7 16.5v-4h6v4" />
                            </svg>
                            {{ __('Save & Sign') }}
                        </button>
                    @else
                        <button
                            type="button"
                            id="btn-save-fields"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                            @if (! $firstSignerId) disabled @endif
                        >
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 3.5h8l3 3v10h-11v-13Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5v5h6v-5M7 16.5v-4h6v4" />
                            </svg>
                            {{ __('Save Draft') }}
                        </button>

                        <form method="POST" action="{{ route(auth()->user()?->role->value === 'notary' ? 'notary.documents.send' : 'documents.send', $document) }}" class="contents">
                            @csrf
                            <button
                                type="submit"
                                id="btn-send-to-signer"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @if (! $canSend) disabled @endif
                            >
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 3 8.25 11.75M17 3l-5.5 14-3.25-5.25L3 8.5 17 3Z" />
                                </svg>
                                {{ $document->notary_request_id !== null ? __('Send for Signing') : __('Send') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <form id="save-fields-form" method="POST" action="{{ route(auth()->user()?->role->value === 'notary' ? 'notary.documents.signature-fields.store' : 'documents.signature-fields.store', $document) }}" class="hidden">
                @csrf
                <input type="hidden" name="fields" id="fields-payload" value="[]" />
            </form>
        </main>

        @include('partials.idle-session')

        <script id="template-prepare-config" type="application/json">
            {!! json_encode([
                'pdfUrl' => $pdfUrl,
                'firstSignerId' => $firstSignerId,
                'signers' => $signers,
                'initialFields' => $initialFields,
                'editorLocked' => (bool) ($attorneySigningLocked ?? false),
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
                    'editorLocked' => __('Attorney signing is locked until the video conference is completed.'),
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
