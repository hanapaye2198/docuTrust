@php
    $readOnly = $readOnly ?? false;
    $notarialActTypes = $notarialActTypes ?? $registryService->notarialActTypes();
    $signerSignatures = $signerSignatures ?? [];
    $orEditable = $orEditable ?? true;
    $feesEditable = $feesEditable ?? ! $readOnly;
@endphp

<div @class(['ui-panel overflow-hidden', 'pointer-events-none opacity-95' => $readOnly])>
    <div class="overflow-x-auto">
        <table class="min-w-[1180px] w-full border-collapse text-left text-xs">
            <thead>
                <tr class="bg-violet-600 text-white">
                    @foreach ([
                        1 => __('ENTRY NO.'),
                        2 => __('TITLE AND DESCRIPTION OF DOCUMENT'),
                        3 => __('NAMES & ADDRESS OF PARTIES'),
                        4 => __('NAMES & ADDRESS OF WITNESSES (If Any)'),
                        5 => __('COMPETENT EVIDENCE OF IDENTITIES'),
                        6 => __('DATE & TIME OF NOTARIZATION'),
                        7 => __('TYPE OF NOTARIAL ACT'),
                        8 => __('FEES & O.R. NO.'),
                        9 => __('NOTARY SIGNATURE'),
                    ] as $columnNumber => $columnLabel)
                        <th class="border border-violet-500/40 px-3 py-3 align-top">
                            <div class="flex items-start gap-2">
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-[11px] font-bold">{{ $columnNumber }}</span>
                                <span class="text-[10px] font-semibold uppercase leading-tight tracking-wide">{{ $columnLabel }}</span>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                <tr class="align-top">
                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        <div class="text-lg font-bold text-violet-700 dark:text-violet-300">
                            @if ($previewEntryNumber !== null)
                                {{ str_pad((string) $previewEntryNumber, 3, '0', STR_PAD_LEFT) }}
                            @else
                                —
                            @endif
                        </div>
                        <p class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-400">{{ __('Assigned on official save') }}</p>
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        @if ($readOnly)
                            <div class="text-sm font-semibold uppercase">{{ $title }}</div>
                        @else
                            <flux:input wire:model="title" type="text" class="uppercase" placeholder="{{ __('Document title') }}" required />
                            <flux:error name="title" />
                        @endif
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        <div class="space-y-3">
                            @foreach ($parties as $index => $party)
                                <div>
                                    <div class="text-sm font-semibold uppercase text-zinc-900 dark:text-zinc-100">
                                        {{ $party['name'] }}
                                    </div>
                                    @if ($readOnly)
                                        <div class="mt-1 text-[11px] text-zinc-600 dark:text-zinc-300">{{ $party['address'] }}</div>
                                    @else
                                        <flux:input wire:model="parties.{{ $index }}.address" type="text" class="mt-1" placeholder="{{ __('Complete address') }}" required />
                                        <flux:error name="parties.{{ $index }}.address" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        @if ($witnesses === [])
                            <span class="text-zinc-400">—</span>
                        @else
                            <div class="space-y-3">
                                @foreach ($witnesses as $index => $witness)
                                    <div class="space-y-1">
                                        @if ($readOnly)
                                            <div class="text-sm font-semibold uppercase">{{ $witness['name'] ?: '—' }}</div>
                                            <div class="text-[11px] text-zinc-600 dark:text-zinc-300">{{ $witness['address'] ?: '—' }}</div>
                                        @else
                                            <flux:input wire:model="witnesses.{{ $index }}.name" type="text" placeholder="{{ __('Full name') }}" />
                                            <flux:input wire:model="witnesses.{{ $index }}.address" type="text" placeholder="{{ __('Address') }}" />
                                            <button type="button" wire:click="removeWitness({{ $index }})" class="text-[10px] font-medium text-red-600 hover:underline dark:text-red-400">
                                                {{ __('Remove') }}
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @unless ($readOnly)
                            <button type="button" wire:click="addWitness" class="mt-2 text-[11px] font-semibold text-violet-700 hover:underline dark:text-violet-300">
                                {{ __('+ Add witness') }}
                            </button>
                        @endunless
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        <div class="space-y-3">
                            @foreach ($competentEvidence as $index => $evidence)
                                <div class="flex gap-2">
                                    @if (($evidence['verification_id'] ?? null) && $registryService->isImagePath($evidence['id_image_path'] ?? null))
                                        <img
                                            src="{{ route('notary.identity-verifications.image', [$notaryRequest, $evidence['verification_id']]) }}"
                                            alt="{{ __('ID') }}"
                                            class="h-14 w-20 shrink-0 rounded border border-zinc-200 object-cover dark:border-zinc-600"
                                        >
                                    @else
                                        <div class="flex h-14 w-20 shrink-0 items-center justify-center rounded border border-dashed border-zinc-300 bg-zinc-50 text-[9px] text-zinc-400 dark:border-zinc-600 dark:bg-zinc-800">
                                            {{ __('No image') }}
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="text-[11px] font-semibold uppercase">{{ $evidence['person_name'] }}</div>
                                        @if (($evidence['verification_id'] ?? null) || $readOnly)
                                            <div class="text-[10px] text-zinc-600 dark:text-zinc-400">
                                                {{ $evidence['id_type'] }} · {{ __('ID No.') }} {{ $evidence['id_number'] }}
                                            </div>
                                        @else
                                            <flux:input wire:model="competentEvidence.{{ $index }}.id_type" type="text" placeholder="{{ __('ID type') }}" class="!text-xs" />
                                            <flux:input wire:model="competentEvidence.{{ $index }}.id_number" type="text" placeholder="{{ __('ID number') }}" class="!text-xs" />
                                        @endif
                                    </div>
                                </div>
                                @unless ($readOnly)
                                    <flux:error name="competentEvidence.{{ $index }}.id_type" />
                                    <flux:error name="competentEvidence.{{ $index }}.id_number" />
                                @endunless
                            @endforeach
                        </div>
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Auto timestamp per person') }}
                        </div>
                        <p class="mt-2 text-[11px] leading-relaxed text-zinc-600 dark:text-zinc-300">
                            {{ __('Recorded automatically when you create the official register entry after payment and seal.') }}
                        </p>
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        @if ($readOnly)
                            <span class="text-[11px] font-semibold uppercase">{{ str_replace('_', ' ', $notarialActType) }}</span>
                        @else
                            <div class="flex flex-col gap-1.5">
                                @foreach ($notarialActTypes as $type)
                                    <label @class([
                                        'cursor-pointer rounded-lg border px-2 py-1.5 text-[10px] font-semibold uppercase transition',
                                        'border-violet-500 bg-violet-50 text-violet-800 dark:border-violet-500 dark:bg-violet-950/40 dark:text-violet-200' => $notarialActType === $type,
                                        'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $notarialActType !== $type,
                                    ])>
                                        <input type="radio" wire:model.live="notarialActType" value="{{ $type }}" class="sr-only" />
                                        {{ str_replace('_', ' ', $type) }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        <div class="space-y-2">
                            @if ($readOnly)
                                <div class="text-sm font-semibold">₱{{ number_format((float) $fees, 2) }}</div>
                                @if ($officialReceiptNo !== '')
                                    <div class="text-[10px] text-zinc-600 dark:text-zinc-400">{{ __('O.R.') }} {{ $officialReceiptNo }}</div>
                                @endif
                            @else
                                @if ($feesEditable)
                                    <flux:input wire:model="fees" type="number" step="0.01" min="0" placeholder="500.00" />
                                    <flux:error name="fees" />
                                @else
                                    <div class="text-sm font-semibold">₱{{ number_format((float) $fees, 2) }}</div>
                                    <p class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ __('Fee set on Settlement before payment') }}</p>
                                @endif
                                @if ($orEditable)
                                    <p class="text-[10px] font-semibold text-zinc-500 dark:text-zinc-400">{{ __('O.R. no.') }}</p>
                                    <flux:input wire:model="officialReceiptNo" type="text" placeholder="CR: 0001234" class="!text-xs" />
                                    <flux:error name="officialReceiptNo" />
                                @else
                                    <p class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ __('O.R. number is entered after client payment.') }}</p>
                                @endif
                            @endif
                        </div>
                    </td>

                    <td class="border border-zinc-200 px-3 py-4 dark:border-zinc-700">
                        @if ($signerSignatures !== [])
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ __('Signer signatures') }}
                            </p>
                            <div class="mt-2 space-y-2">
                                @foreach ($signerSignatures as $signerSignature)
                                    <div>
                                        <div class="text-[10px] font-semibold uppercase text-zinc-700 dark:text-zinc-200">{{ $signerSignature['name'] }}</div>
                                        @if ($signerSignature['signature_path'])
                                            <img
                                                src="{{ route('notary.document-signers.signature-image', [
                                                    'notaryRequest' => $notaryRequest,
                                                    'document' => $signerSignature['document_id'],
                                                    'documentSigner' => $signerSignature['document_signer_id'],
                                                    'signature' => $signerSignature['signature_id'],
                                                ]) }}"
                                                alt="{{ $signerSignature['name'] }}"
                                                class="mt-1 h-10 w-auto max-w-full object-contain"
                                            >
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <p @class([
                            'text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400',
                            'mt-3' => $signerSignatures !== [],
                        ])>
                            {{ __('Notary signature') }}
                        </p>
                        @if ($credentialId && $signatureImagePath)
                            <img
                                src="{{ route('notary.credentials.document', ['credential' => $credentialId, 'document' => 'signature']) }}"
                                alt="{{ __('Notary signature') }}"
                                class="mt-2 h-12 w-auto max-w-full object-contain"
                            >
                        @else
                            <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-300">
                                @unless ($readOnly)
                                    <a href="{{ route('notary.credentials') }}" wire:navigate class="underline">{{ __('Upload signature') }}</a>
                                @else
                                    {{ __('Signature on file') }}
                                @endunless
                            </p>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
