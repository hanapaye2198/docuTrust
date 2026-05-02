<x-layouts.app>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3">
        <x-template-stepper :current="2" />

        <header
            class="mt-4 flex flex-col gap-3 border-b border-zinc-200/90 pb-4 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-800"
        >
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 md:text-3xl">{{ __('Prepare template') }}</h1>
                <p class="mt-1 truncate text-base text-zinc-500 dark:text-zinc-400" title="{{ $template->name }}">{{ $template->name }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:max-w-xl sm:items-end">
                @if (count($signerRoleNames) > 0)
                    <label class="flex flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400 sm:flex-row sm:items-center sm:gap-2">
                        <span>{{ __('Assign new fields to') }}</span>
                        <select
                            id="field-role"
                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            @foreach ($signerRoleNames as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        id="btn-add-signature"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-500 dark:hover:bg-teal-600"
                        @if (! $firstSignerRoleName) disabled @endif
                    >
                        {{ __('Add signature field') }}
                    </button>
                    <button
                        type="button"
                        id="btn-add-text"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                        @if (! $firstSignerRoleName) disabled @endif
                    >
                        {{ __('Add text field') }}
                    </button>
                    <button
                        type="button"
                        id="btn-add-name"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-950/60"
                        @if (! $firstSignerRoleName) disabled @endif
                    >
                        {{ __('Add name field') }}
                    </button>
                    <button
                        type="button"
                        id="btn-add-date"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm font-semibold text-violet-900 shadow-sm transition hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100 dark:hover:bg-violet-950/60"
                        @if (! $firstSignerRoleName) disabled @endif
                    >
                        {{ __('Add date field') }}
                    </button>
                    <button
                        type="button"
                        id="btn-save-fields"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-300 dark:hover:bg-white/5"
                        @if (! $firstSignerRoleName) disabled @endif
                    >
                        {{ __('Save template') }}
                    </button>
                    <flux:button variant="ghost" :href="route('templates.index')" wire:navigate>{{ __('Back') }}</flux:button>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-white px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-zinc-900 dark:text-emerald-100"
            >
                <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-emerald-500"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (! $firstSignerRoleName)
            <div class="rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Add at least one signer role in the previous step before placing fields.') }}
            </div>
        @endif

        <div
            id="pdf-load-error"
            class="hidden rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"
            role="alert"
        ></div>

        <div class="ui-panel overflow-auto p-3 sm:p-4">
            <div id="pdf-shell" class="relative inline-block min-h-[200px] min-w-[200px]">
                <canvas id="pdf-canvas" class="block max-w-none rounded-lg border border-zinc-200 shadow-sm dark:border-zinc-700"></canvas>
                <canvas id="fabric-canvas" class="absolute left-0 top-0 block cursor-crosshair rounded-lg"></canvas>
            </div>
        </div>

        <form id="save-fields-form" method="POST" action="{{ route('templates.fields.store', $template) }}" class="hidden">
            @csrf
            <input type="hidden" name="fields" id="fields-payload" value="[]" />
        </form>

        <script type="application/json" id="template-prepare-config">@json($templatePrepareConfig)</script>
    </div>
</x-layouts.app>
