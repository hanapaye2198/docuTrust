@php
    use App\Enums\DocumentSignerStatus;
    use App\Enums\DocumentStatus;
@endphp

<div class="space-y-8">
    @if ($document->status === DocumentStatus::Draft)
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Add signers') }}</h3>
            <p class="ui-muted mt-1">{{ __('Invite people who need to sign this document') }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/70 p-4 dark:border-zinc-700/80 dark:bg-zinc-800/30">
            <form wire:submit="saveSigningWorkflow" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <flux:field class="min-w-0 flex-1">
                    <flux:label>{{ __('Signing workflow') }}</flux:label>
                    <flux:select wire:model="signingWorkflow">
                        <option value="{{ \App\Models\Document::SIGNING_WORKFLOW_SEQUENTIAL }}">{{ __('Sequential') }}</option>
                        <option value="{{ \App\Models\Document::SIGNING_WORKFLOW_PARALLEL }}">{{ __('Parallel') }}</option>
                    </flux:select>
                    <flux:error name="signingWorkflow" />
                </flux:field>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 lg:max-w-md">
                    {{ $signingWorkflow === \App\Models\Document::SIGNING_WORKFLOW_SEQUENTIAL
                        ? __('Signers will be prompted in order. Reorder the list below to control who signs next.')
                        : __('Any signer can sign as soon as the document is sent. Signer order is ignored in parallel mode.') }}
                </p>
                <flux:button variant="outline" type="submit">{{ __('Save workflow') }}</flux:button>
            </form>
        </div>

        <form wire:submit="addSigner" class="space-y-4">
            <div class="relative space-y-2 sm:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model.live.debounce.300ms="name" type="text" autocomplete="name" required />
                        <flux:error name="name" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Email') }}</flux:label>
                        <flux:input wire:model.live.debounce.300ms="email" type="email" autocomplete="email" required />
                        <flux:error name="email" />
                    </flux:field>
                </div>

                @if (count($contactSuggestions) > 0)
                    <ul
                        class="absolute left-0 right-0 top-full z-20 mt-1 max-h-60 overflow-auto rounded-xl border border-zinc-200/90 bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 dark:border-zinc-700 dark:bg-zinc-900"
                        role="listbox"
                    >
                        @foreach ($contactSuggestions as $suggestion)
                            <li wire:key="contact-suggestion-{{ $suggestion['id'] }}">
                                <button
                                    type="button"
                                    class="flex w-full flex-col gap-0.5 px-3 py-2 text-left transition hover:bg-teal-500/10 dark:hover:bg-white/5"
                                    wire:click="selectContact({{ $suggestion['id'] }})"
                                >
                                    <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ $suggestion['name'] }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $suggestion['email'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <flux:button variant="primary" type="submit">{{ __('Add signer') }}</flux:button>
        </form>

        @if ($editingSignerId !== null)
            <div class="mt-6 rounded-2xl border border-zinc-200/80 bg-zinc-50/70 p-4 dark:border-zinc-700/80 dark:bg-zinc-800/30">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Edit signer') }}</h4>
                </div>

                <form wire:submit="saveSignerEdits" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Name') }}</flux:label>
                            <flux:input wire:model="editingName" type="text" autocomplete="name" required />
                            <flux:error name="editingName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Email') }}</flux:label>
                            <flux:input wire:model="editingEmail" type="email" autocomplete="email" required />
                            <flux:error name="editingEmail" />
                        </flux:field>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button variant="primary" type="submit">{{ __('Save signer') }}</flux:button>
                        <flux:button variant="ghost" type="button" wire:click="cancelEditingSigner">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </div>
        @endif
    @endif

    <div>
        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signers') }}</h3>

        @if ($document->documentSigners->isEmpty())
            <p class="ui-muted mt-2">{{ __('No signers yet.') }}</p>
        @else
            <div class="ui-table-wrap mt-4">
                <table class="min-w-full divide-y divide-zinc-200/80 text-sm dark:divide-zinc-700/80">
                    <thead class="bg-zinc-50/80 dark:bg-zinc-800/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Order') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signed') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signing link') }}</th>
                            @if ($document->status === DocumentStatus::Draft)
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                        @foreach ($document->documentSigners as $signer)
                            <tr class="transition-colors hover:bg-teal-500/[0.04] dark:hover:bg-white/[0.03]">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $signer->name }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $signer->email }}</td>
                                <td class="px-4 py-3 capitalize text-zinc-600 dark:text-zinc-300">{{ $signer->status->value }}</td>
                                <td class="px-4 py-3 tabular-nums text-zinc-500 dark:text-zinc-400">{{ $document->usesSequentialSigningWorkflow() ? $signer->signing_order : '—' }}</td>
                                <td class="px-4 py-3 tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ $signer->signed_at?->format('M j, Y g:i A') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($signer->status === DocumentSignerStatus::Pending && $document->status === DocumentStatus::Pending)
                                        <a
                                            href="{{ route('sign.show', $signer->access_token ?? $signer->id) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-medium text-teal-700 underline decoration-teal-500/30 underline-offset-2 hover:text-teal-800 hover:decoration-teal-500 dark:text-teal-300 dark:hover:text-teal-200"
                                        >
                                            {{ __('Open') }}
                                        </a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                @if ($document->status === DocumentStatus::Draft)
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            @if ($document->usesSequentialSigningWorkflow())
                                                <flux:button size="sm" variant="ghost" type="button" wire:click="moveSignerUp({{ $signer->id }})">
                                                    {{ __('Up') }}
                                                </flux:button>
                                                <flux:button size="sm" variant="ghost" type="button" wire:click="moveSignerDown({{ $signer->id }})">
                                                    {{ __('Down') }}
                                                </flux:button>
                                            @endif
                                            <flux:button size="sm" variant="ghost" type="button" wire:click="startEditingSigner({{ $signer->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                type="button"
                                                wire:click="removeSigner({{ $signer->id }})"
                                                wire:confirm="{{ __('Remove this signer?') }}"
                                            >
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
