<x-layouts.app>
    <div class="flex w-full max-w-none flex-col gap-6 px-4 py-5 sm:gap-7 sm:px-6 lg:px-8 2xl:px-10">
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-white px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-zinc-900 dark:text-emerald-100"
            >
                <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-emerald-500"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-red-200/90 bg-gradient-to-r from-red-50 to-white px-4 py-3 text-sm text-red-900 shadow-sm dark:border-red-900/50 dark:from-red-950/40 dark:to-zinc-900 dark:text-red-100"
            >
                <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-red-500"></span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="rounded-2xl border border-zinc-200/80 bg-white/85 p-4 shadow-sm shadow-zinc-950/5 backdrop-blur-sm dark:border-zinc-700/70 dark:bg-zinc-900/70">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Document setup') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Prepare document') }}</h1>
                    <p class="mt-1 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $document->title }}</p>
                </div>
                <div class="flex flex-col items-start gap-3 lg:items-end">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ __('Step 3 of 4') }}</span>
                        <span>{{ __('Place fields, drag to position, then save.') }}</span>
                    </div>
                    <form method="POST" action="{{ route('documents.send', $document) }}" class="w-full lg:w-auto">
                        @csrf
                        <button
                            type="submit"
                            id="btn-send-to-signer"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            @if (! $canSend) disabled @endif
                        >
                            {{ __('Send to signer') }}
                        </button>
                    </form>
                </div>
            </div>

        </div>

        @if (! $firstSignerId)
            <div class="rounded-2xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Add at least one signer on the document page before placing fields.') }}
            </div>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[280px_minmax(0,1fr)] 2xl:grid-cols-[300px_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-zinc-200/80 bg-white/85 p-4 shadow-sm shadow-zinc-950/5 backdrop-blur-sm dark:border-zinc-700/70 dark:bg-zinc-900/70 xl:sticky xl:top-4 xl:self-start xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto xl:pr-3">
                <div class="space-y-6">
                    <section class="rounded-xl border border-zinc-200/80 bg-zinc-50/80 px-4 py-4 text-sm dark:border-zinc-700 dark:bg-zinc-950/50">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Controls') }}</h2>
                            <span id="editor-status" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                                {{ __('Saved') }}
                            </span>
                        </div>
                        @if (count($signers) > 0)
                            <label class="mt-4 flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned signatory') }}</span>
                                <select
                                    id="field-signer"
                                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                >
                                    @foreach ($signers as $signer)
                                        <option value="{{ $signer['id'] }}">{{ $signer['name'] }}{{ $signer['email'] ? ' - '.$signer['email'] : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <div class="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                            <button type="button" id="btn-prev-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Prev') }}</button>
                            <span id="page-indicator" class="text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
                            <button type="button" id="btn-next-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}</button>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                id="btn-save-fields"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-500 dark:hover:bg-teal-600"
                                @if (! $firstSignerId) disabled @endif
                            >
                                {{ __('Save') }}
                            </button>
                            <flux:button class="rounded-xl justify-center" variant="ghost" :href="route('documents.show', $document)" wire:navigate>{{ __('Back') }}</flux:button>
                        </div>
                    </section>
                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Standard fields') }}</h2>
                        <div class="mt-3 space-y-2">
                            <button type="button" draggable="true" data-field-type="signature" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Signature') }}</button>
                            <button type="button" draggable="true" data-field-type="signature_left" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Signature (Left aligned)') }}</button>
                            <button type="button" draggable="true" data-field-type="signature_right" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-teal-700 dark:hover:bg-teal-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Signature (Right aligned)') }}</button>
                            <button type="button" draggable="true" data-field-type="text" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-amber-300 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-amber-700 dark:hover:bg-amber-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Text Field') }}</button>
                            <button type="button" draggable="true" data-field-type="checkbox" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-sky-700 dark:hover:bg-sky-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Checkbox') }}</button>
                            <button type="button" draggable="true" data-field-type="radio" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-indigo-300 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Radio Button') }}</button>
                        </div>
                    </section>
                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Auto-fill fields') }}</h2>
                        <div class="mt-3 space-y-2">
                            <button type="button" draggable="true" data-field-type="date" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-violet-300 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-violet-700 dark:hover:bg-violet-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Date Signed') }}</button>
                            <button type="button" draggable="true" data-field-type="email" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-rose-700 dark:hover:bg-rose-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Email') }}</button>
                            <button type="button" draggable="true" data-field-type="name" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Name') }}</button>
                            <button type="button" draggable="true" data-field-type="initials" class="field-palette-btn block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left text-sm font-medium text-zinc-800 transition hover:border-fuchsia-300 hover:bg-fuchsia-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:hover:border-fuchsia-700 dark:hover:bg-fuchsia-950/40" @if (! $firstSignerId) disabled @endif>{{ __('Initials') }}</button>
                        </div>
                    </section>
                    <section id="field-inspector" class="rounded-xl border border-zinc-200/80 bg-white/80 px-4 py-4 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950/50">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Selected field') }}</h2>
                            <span id="field-inspector-empty" class="text-xs font-medium uppercase tracking-[0.14em] text-zinc-400">{{ __('None') }}</span>
                        </div>
                        <div id="field-inspector-body" class="mt-4 hidden space-y-4">
                            <label class="flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Field type') }}</span>
                                <select id="selected-field-type" class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                                    <option value="signature">{{ __('Signature') }}</option>
                                    <option value="signature_left">{{ __('Signature (Left aligned)') }}</option>
                                    <option value="signature_right">{{ __('Signature (Right aligned)') }}</option>
                                    <option value="text">{{ __('Text Field') }}</option>
                                    <option value="checkbox">{{ __('Checkbox') }}</option>
                                    <option value="radio">{{ __('Radio Button') }}</option>
                                    <option value="date">{{ __('Date Signed') }}</option>
                                    <option value="email">{{ __('Email') }}</option>
                                    <option value="name">{{ __('Name') }}</option>
                                    <option value="initials">{{ __('Initials') }}</option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned signatory') }}</span>
                                <select id="selected-field-signer" class="rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                                    @foreach ($signers as $signer)
                                        <option value="{{ $signer['id'] }}">{{ $signer['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btn-duplicate-field" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Duplicate') }}</button>
                                <button type="button" id="btn-delete-field" class="rounded-xl border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/30">{{ __('Delete') }}</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btn-bring-forward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Bring forward') }}</button>
                                <button type="button" id="btn-send-backward" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Send backward') }}</button>
                            </div>
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Tip: use Ctrl/Cmd + C and Ctrl/Cmd + V to copy and paste the selected field.') }}</p>
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Fields snap to page edges and center lines when moved close enough.') }}</p>
                        </div>
                    </section>
                    <section class="rounded-xl border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-200">
                        <p class="font-semibold">{{ __('Notice') }}</p>
                        <p class="mt-2">{{ __('This editor should feel like editing a document: place fields, drag them naturally, and save when the layout looks right.') }}</p>
                    </section>
                </div>
            </aside>

            <div class="ui-panel flex min-w-0 flex-col overflow-auto rounded-2xl border border-zinc-200/80 bg-[linear-gradient(180deg,rgba(250,250,250,0.98),rgba(244,244,245,0.98))] p-4 shadow-sm shadow-zinc-950/5 dark:border-zinc-700/70 dark:bg-zinc-900/50 sm:max-h-[calc(100vh-2rem)] sm:p-6">
                <div id="pdf-load-error" class="mb-3 hidden rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"></div>
                <div id="pdf-shell" class="relative mx-auto inline-block min-h-[200px] min-w-[200px] shrink-0 rounded-2xl bg-white ring-1 ring-zinc-200/80 shadow-[0_18px_50px_rgba(15,23,42,0.08)] dark:bg-zinc-950 dark:ring-zinc-700/80">
                    <canvas id="pdf-canvas" class="relative z-0 block max-w-none rounded-xl bg-white shadow-sm"></canvas>
                    <canvas id="fabric-canvas" class="absolute left-0 top-0 z-20 block"></canvas>
                </div>
            </div>
        </div>

        <form id="save-fields-form" method="POST" action="{{ route('documents.signature-fields.store', $document) }}" class="hidden">
            @csrf
            <input type="hidden" name="fields" id="fields-payload" value="[]" />
        </form>
    </div>
    <script id="template-prepare-config" type="application/json">
        {!! json_encode([
            'pdfUrl' => $pdfUrl,
            'firstSignerId' => $firstSignerId,
            'signers' => $signers,
            'initialFields' => $initialFields,
            'messages' => [
                'saved' => __('Saved'),
                'saving' => __('Saving...'),
                'unsaved' => __('Unsaved changes'),
                'none' => __('None'),
                'selected' => __('Selected'),
                'previewLoading' => __('Preview still loading. Please wait a second, then try again.'),
                'noSigner' => __('No signer found. Add at least one signer first.'),
                'loadFailed' => __('Unable to load document preview. Please refresh the page and try again.'),
                'saveBeforeSend' => __('Save your latest field changes before sending to signer.'),
            ],
        ]) !!}
    </script>
</x-layouts.app>
