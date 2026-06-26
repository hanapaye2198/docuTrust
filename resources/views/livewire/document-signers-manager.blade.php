@php
    use App\Enums\DocumentSignerStatus;
    use App\Enums\DocumentStatus;
    use App\Enums\SigningMethod;
    use App\Services\SigningMethodService;

    $signingMethodService = app(SigningMethodService::class);

    $signersByOrder = $document->documentSigners
        ->sortBy(['signing_order', 'id'])
        ->groupBy(fn ($s) => $s->signing_order ?? 0);

    $signingMethodLabel = fn (SigningMethod $m): string => match ($m) {
        SigningMethod::EmailLink      => 'Email link',
        SigningMethod::AccountVerified => 'Account',
        default                        => $m->value,
    };
@endphp

<div class="space-y-3">
    @if ($document->status === DocumentStatus::Draft)

        {{-- ── Workflow checkbox pill ── --}}
        <label class="flex w-full cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition
            {{ $workflowOrdered
                ? 'border-teal-500 bg-teal-50/50 dark:border-teal-600 dark:bg-teal-900/20'
                : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700' }}">
            <input
                type="checkbox"
                wire:model.live="workflowOrdered"
                class="h-4 w-4 rounded border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-900"
            />
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Set document workflow') }}</span>
        </label>

        {{-- ══════════════════════════════════════════════
             SEQUENTIAL MODE — numbered order cards
        ══════════════════════════════════════════════ --}}
        @if ($workflowOrdered)

            @if ($document->documentSigners->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-zinc-100/70 p-3 space-y-2 dark:border-zinc-700 dark:bg-zinc-800/40">
                    @foreach ($signersByOrder as $order => $signers)
                        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                             wire:key="order-group-{{ $order }}">

                            {{-- Card header --}}
                            <div class="flex items-center gap-2 border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
                                <svg class="h-4 w-4 shrink-0 cursor-grab text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor">
                                    <circle cx="5" cy="4" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="5" cy="12" r="1.2"/>
                                    <circle cx="11" cy="4" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                                </svg>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-700 dark:bg-teal-900/50 dark:text-teal-300">
                                    {{ $order }}
                                </span>
                                <span class="flex-1 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                    {{ __('Participant order :n', ['n' => $order]) }}
                                </span>
                                {{-- + = add signer to this same order slot --}}
                                <button
                                    type="button"
                                    wire:click="startAddingToOrder({{ $order }})"
                                    title="{{ __('Add signer to this order') }}"
                                    class="flex h-6 w-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-teal-50 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Signer rows --}}
                            @foreach ($signers as $signer)
                                <div class="flex items-center gap-2 border-b border-zinc-50 px-3 py-2.5 last:border-b-0 dark:border-zinc-800/60"
                                     wire:key="signer-row-{{ $signer->id }}">
                                    @if ($editingSignerId === $signer->id)
                                        {{-- Inline edit form --}}
                                        <form wire:submit="saveSignerEdits" class="flex flex-1 flex-wrap items-center gap-2">
                                            <flux:input wire:model="editingName" type="text" placeholder="{{ __('Name') }}" class="min-w-0 flex-1" required />
                                            <flux:input wire:model="editingEmail" type="email" placeholder="{{ __('Email') }}" class="min-w-0 flex-1" required />
                                            <flux:select wire:model="editingRoleType" class="w-28 shrink-0">
                                                <option value="{{ \App\Enums\TemplateRoleType::Signer->value }}">{{ __('Signer') }}</option>
                                                <option value="{{ \App\Enums\TemplateRoleType::Approver->value }}">{{ __('Approver') }}</option>
                                                <option value="{{ \App\Enums\TemplateRoleType::Recipient->value }}">{{ __('Recipient') }}</option>
                                            </flux:select>
                                            <div class="flex shrink-0 gap-1">
                                                <flux:button variant="primary" size="xs" type="submit">{{ __('Save') }}</flux:button>
                                                <flux:button variant="ghost" size="xs" type="button" wire:click="cancelEditingSigner">{{ __('Cancel') }}</flux:button>
                                            </div>
                                        </form>
                                    @else
                                        {{-- Read row: Name · Email · Role/Method · Edit · Delete --}}
                                        <div class="min-w-0 flex-1 grid grid-cols-[1fr_1fr_auto] gap-x-3 items-center">
                                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $signer->name ?: '—' }}</p>
                                            <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $signer->email ?: '—' }}</p>
                                            <div class="text-right">
                                                <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ ucfirst($signer->roleType()->value) }}</p>
                                                <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $signingMethodLabel($signer->signingMethod()) }}</p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="startEditingSigner({{ $signer->id }})"
                                            class="shrink-0 rounded-md p-1.5 text-zinc-300 transition hover:bg-zinc-100 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                            title="{{ __('Edit') }}"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="removeSigner({{ $signer->id }})"
                                            wire:confirm="{{ __('Remove this signer?') }}"
                                            class="shrink-0 rounded-md p-1.5 text-zinc-300 transition hover:bg-red-50 hover:text-red-500 dark:text-zinc-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                            title="{{ __('Remove') }}"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Inline "add to this order" form --}}
                            @if ($addingToOrder === $order)
                                <div class="border-t border-zinc-100 bg-zinc-50/50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-800/30">
                                    <p class="mb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Add co-signer to order :n', ['n' => $order]) }}</p>
                                    <form wire:submit="addSignerToOrder({{ $order }})" class="space-y-2">
                                        <div class="grid gap-2 sm:grid-cols-3">
                                            <flux:input wire:model.live.debounce.300ms="name" type="text" autocomplete="off" placeholder="{{ __('Name') }}" required />
                                            <flux:input wire:model.live.debounce.300ms="email" type="email" autocomplete="off" placeholder="{{ __('Email') }}" required />
                                            <flux:select wire:model="roleType">
                                                <option value="{{ \App\Enums\TemplateRoleType::Signer->value }}">{{ __('Signer') }}</option>
                                                <option value="{{ \App\Enums\TemplateRoleType::Approver->value }}">{{ __('Approver') }}</option>
                                                <option value="{{ \App\Enums\TemplateRoleType::Recipient->value }}">{{ __('Recipient') }}</option>
                                            </flux:select>
                                        </div>
                                        <flux:error name="name" /><flux:error name="email" />

                                        <div class="flex gap-2">
                                            <flux:button type="submit" variant="primary" size="sm">{{ __('Add') }}</flux:button>
                                            <flux:button type="button" variant="ghost" size="sm" wire:click="cancelAddingToOrder">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty state for sequential --}}
                <div class="flex items-center gap-3 rounded-xl border-2 border-dashed border-zinc-200 px-4 py-5 dark:border-zinc-700">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('No participants yet') }}</p>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Add participants below — each will sign in order.') }}</p>
                    </div>
                </div>
            @endif

        {{-- ══════════════════════════════════════════════
             PARALLEL MODE — flat list, no order cards
        ══════════════════════════════════════════════ --}}
        @else

            @if ($document->documentSigners->isNotEmpty())
                <div class="space-y-1.5">
                    @foreach ($document->documentSigners->sortBy('id') as $signer)
                        <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900"
                             wire:key="par-signer-{{ $signer->id }}">
                            @if ($editingSignerId === $signer->id)
                                {{-- Inline edit form --}}
                                <form wire:submit="saveSignerEdits" class="flex flex-1 flex-wrap items-center gap-2">
                                    <flux:input wire:model="editingName" type="text" placeholder="{{ __('Name') }}" class="min-w-0 flex-1" required />
                                    <flux:input wire:model="editingEmail" type="email" placeholder="{{ __('Email') }}" class="min-w-0 flex-1" required />
                                    <flux:select wire:model="editingRoleType" class="w-28 shrink-0">
                                        <option value="{{ \App\Enums\TemplateRoleType::Signer->value }}">{{ __('Signer') }}</option>
                                        <option value="{{ \App\Enums\TemplateRoleType::Approver->value }}">{{ __('Approver') }}</option>
                                        <option value="{{ \App\Enums\TemplateRoleType::Recipient->value }}">{{ __('Recipient') }}</option>
                                    </flux:select>
                                    <div class="flex shrink-0 gap-1">
                                        <flux:button variant="primary" size="xs" type="submit">{{ __('Save') }}</flux:button>
                                        <flux:button variant="ghost" size="xs" type="button" wire:click="cancelEditingSigner">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                {{-- Avatar initial --}}
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ mb_substr($signer->name, 0, 1) ?: '?' }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $signer->name ?: '—' }}</p>
                                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signer->email ?: '—' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ ucfirst($signer->roleType()->value) }}
                                </span>
                                <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $signingMethodLabel($signer->signingMethod()) }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="startEditingSigner({{ $signer->id }})"
                                    class="shrink-0 rounded-md p-1.5 text-zinc-300 transition hover:bg-zinc-100 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                    title="{{ __('Edit') }}"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    wire:click="removeSigner({{ $signer->id }})"
                                    wire:confirm="{{ __('Remove this signer?') }}"
                                    class="shrink-0 rounded-md p-1.5 text-zinc-300 transition hover:bg-red-50 hover:text-red-500 dark:text-zinc-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                    title="{{ __('Remove') }}"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty state for parallel --}}
                <div class="flex items-center gap-3 rounded-xl border-2 border-dashed border-zinc-200 px-4 py-5 dark:border-zinc-700">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('No participants yet') }}</p>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Add participants below — all will be invited at once.') }}</p>
                    </div>
                </div>
            @endif

        @endif

        {{-- ── Add participant form ── --}}
        <form wire:submit="addSigner" class="space-y-2">

            <div class="relative">
                <div class="grid gap-2 sm:grid-cols-3">
                    <flux:input
                        wire:model.live.debounce.300ms="name"
                        type="text"
                        autocomplete="off"
                        placeholder="{{ __('Name') }}"
                        required
                    />
                    <flux:input
                        wire:model.live.debounce.300ms="email"
                        type="email"
                        autocomplete="off"
                        placeholder="{{ __('DocuTrust email') }}"
                        required
                    />
                    <flux:select wire:model="roleType">
                        <option value="{{ \App\Enums\TemplateRoleType::Signer->value }}">{{ __('Signer') }}</option>
                        <option value="{{ \App\Enums\TemplateRoleType::Approver->value }}">{{ __('Approver') }}</option>
                        <option value="{{ \App\Enums\TemplateRoleType::Recipient->value }}">{{ __('Recipient') }}</option>
                    </flux:select>
                </div>

                {{-- Verified user autocomplete (always active) --}}
                @if (count($verifiedContactSuggestions) > 0)
                    <ul class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-auto rounded-xl border border-teal-200 bg-white py-1 text-sm shadow-lg dark:border-teal-800 dark:bg-zinc-900" role="listbox">
                        @foreach ($verifiedContactSuggestions as $suggestion)
                            <li wire:key="vc-{{ $suggestion['id'] }}">
                                <button type="button"
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-teal-50 dark:hover:bg-teal-900/20"
                                    wire:click="selectVerifiedContact({{ $suggestion['id'] }})">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-semibold uppercase text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                                        {{ mb_substr($suggestion['name'], 0, 1) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-zinc-900 dark:text-zinc-50">{{ $suggestion['name'] }}</p>
                                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $suggestion['email'] }}</p>
                                    </div>
                                    <span class="ml-auto shrink-0 rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                                        {{ __('Verified') }}
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <flux:error name="name" />
            <flux:error name="email" />
            <flux:error name="roleType" />
            <flux:error name="signingMethod" />

            <div class="flex items-center gap-2 pt-1">
                <button type="button"
                    wire:click="includeMe"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                    {{ __('Include me') }}
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    {{ __('Add participant') }}
                </button>
            </div>
        </form>

    @else

        {{-- ── Non-draft: read-only list ── --}}
        <div class="space-y-2">
            @forelse ($document->documentSigners->sortBy(['signing_order','id']) as $signer)
                <div wire:key="signer-ro-{{ $signer->id }}"
                     class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @if ($document->usesSequentialSigningWorkflow())
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ $signer->signing_order ?? '—' }}
                        </span>
                    @else
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ mb_substr($signer->name, 0, 1) ?: '?' }}
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $signer->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signer->email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ ucfirst($signer->roleType()->value) }}
                    </span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        {{ $signingMethodLabel($signer->signingMethod()) }}
                    </span>
                    <span class="shrink-0 text-xs capitalize
                        {{ in_array($signer->status, [DocumentSignerStatus::Signed, DocumentSignerStatus::Approved])
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-zinc-400 dark:text-zinc-500' }}">
                        {{ $signer->status->value }}
                    </span>
                    @if ($signer->requiresAction() && $signer->status === DocumentSignerStatus::Pending && $document->status === DocumentStatus::Pending)
                        <a href="{{ $signingMethodService->signerEntryUrl($signer) }}" target="_blank" rel="noopener noreferrer"
                           class="shrink-0 text-xs font-medium text-teal-600 underline hover:text-teal-700 dark:text-teal-400">{{ __('Open') }}</a>
                        <flux:button size="xs" variant="ghost" type="button"
                            wire:click="resendInvitation({{ $signer->id }})"
                            wire:confirm="{{ __('Resend the invitation email to :name?', ['name' => $signer->name]) }}">{{ __('Resend') }}</flux:button>
                        <flux:button size="xs" variant="ghost" type="button"
                            wire:click="sendReminder({{ $signer->id }})"
                            wire:confirm="{{ __('Send a reminder email to :name?', ['name' => $signer->name]) }}">{{ __('Remind') }}</flux:button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No participants.') }}</p>
            @endforelse
        </div>

    @endif
</div>
