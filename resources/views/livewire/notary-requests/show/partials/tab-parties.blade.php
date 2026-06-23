@php
    use App\Enums\NotaryRequestStatus;
@endphp

            <div class="ui-panel p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-1">{{ __('Parties & identity') }}</flux:heading>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Principal signers attached to this case. Identity documents require email OTP confirmation before upload.') }}</p>
                <div class="mt-4 space-y-3">
                    @forelse ($requestSigners as $signer)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $signer->full_name }}</div>
                                    <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $signer->email }} @if ($signer->phone) • {{ $signer->phone }} @endif</div>
                                    @if ($signer->role && $signer->role !== 'signer')
                                        <span class="mt-1 inline-block rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ ucfirst($signer->role) }}</span>
                                    @endif
                                    @php
                                        $invitation = $signerInvitations[$signer->id] ?? null;
                                        $hasPortalAccess = isset($enotaryPortalEmails[strtolower($signer->email)]);
                                    @endphp
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        @if ($hasPortalAccess)
                                            <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                {{ __('Portal active') }}
                                            </span>
                                        @elseif ($invitation instanceof \App\Models\EnotaryInvitation && $invitation->isPending())
                                            <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                                                {{ __('Invitation pending') }}
                                            </span>
                                        @elseif ($invitation instanceof \App\Models\EnotaryInvitation && $invitation->isAccepted())
                                            <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                {{ __('Invitation accepted') }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('No portal invite yet') }}
                                            </span>
                                        @endif
                                        @if ($isNotary && ! $hasPortalAccess && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                                            <flux:button size="xs" variant="outline" type="button" wire:click="resendSignerPortalInvite({{ $signer->id }})">
                                                {{ $invitation instanceof \App\Models\EnotaryInvitation && $invitation->isPending() ? __('Resend invite') : __('Send portal invite') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                                @if ($isNotary && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                                    <button type="button" wire:click="removeSigner({{ $signer->id }})" wire:confirm="{{ __('Remove this signer?') }}"
                                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20 dark:hover:text-red-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($notaryRequest->status === NotaryRequestStatus::Submitted && $canManageLifecycle)
                                <div class="mt-4 space-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                    <flux:button size="sm" variant="outline" type="button" wire:click="sendSignerIdentityOtp({{ $signer->id }})">{{ __('Send email OTP') }}</flux:button>
                                    <flux:field>
                                        <flux:label>{{ __('OTP code') }}</flux:label>
                                        <div class="flex flex-wrap items-end gap-2">
                                            <flux:input class="max-w-xs" type="text" wire:model="identityOtpCode" placeholder="{{ __('Enter code') }}" />
                                            <flux:button size="sm" variant="primary" type="button" wire:click="verifySignerIdentityOtp({{ $signer->id }})">{{ __('Verify OTP') }}</flux:button>
                                        </div>
                                        <flux:error name="identityOtp" />
                                        <flux:error name="identityOtpCode" />
                                    </flux:field>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('ID type') }}</flux:label>
                                            <select wire:model="pendingIdType" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                                <option value="passport">{{ __('Passport') }}</option>
                                                <option value="drivers_license">{{ __('Driver license') }}</option>
                                                <option value="national_id">{{ __('National ID') }}</option>
                                                <option value="other">{{ __('Other') }}</option>
                                            </select>
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('ID number') }}</flux:label>
                                            <flux:input type="text" wire:model="pendingIdNumber" />
                                            <flux:error name="pendingIdNumber" />
                                        </flux:field>
                                    </div>
                                    <flux:field>
                                        <flux:label>{{ __('Government ID scan') }}</flux:label>
                                        <input type="file" wire:model="idImageFile" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                                        <flux:error name="idImageFile" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>{{ __('Selfie (optional)') }}</flux:label>
                                        <input type="file" wire:model="selfieImageFile" accept=".jpg,.jpeg,.png" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                                        <flux:error name="selfieImageFile" />
                                    </flux:field>
                                    <flux:button size="sm" variant="primary" type="button" wire:click="saveSignerIdentityDocuments({{ $signer->id }})">{{ __('Submit ID for review') }}</flux:button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No signers added yet. Add signers below to proceed.') }}</div>
                    @endforelse
                </div>

                @if ($isNotary && $pendingIdentityReviews->isNotEmpty())
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Pending identity review') }}</h3>
                        <div class="mt-3 space-y-3">
                            @foreach ($pendingIdentityReviews as $review)
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                                    <div class="font-medium text-amber-950 dark:text-amber-100">{{ $review->signer?->full_name }}</div>
                                    <div class="mt-1 text-amber-900/80 dark:text-amber-200/80">{{ __('ID type') }}: {{ $review->id_type }} • {{ __('Number') }}: {{ $review->id_number }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button size="sm" variant="primary" type="button" wire:click="approveIdentityRecord({{ $review->id }})">{{ __('Approve') }}</flux:button>
                                        <flux:button size="sm" variant="outline" type="button" wire:click="$set('identityRejectId', {{ $review->id }})">{{ __('Reject') }}</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($identityRejectId)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
                        <flux:field>
                            <flux:label>{{ __('Rejection reason') }}</flux:label>
                            <flux:textarea wire:model="identityRejectReason" rows="3" />
                            <flux:error name="identityRejectReason" />
                        </flux:field>
                        <flux:button class="mt-3" variant="outline" type="button" wire:click="rejectIdentityRecord">{{ __('Confirm rejection') }}</flux:button>
                        <flux:error name="rejectIdentity" />
                    </div>
                @endif

                <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    @if (! $isNotary)
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Philippines location check') }}</h3>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Uses your browser coordinates together with server-side IP intelligence. Failed checks flag the case automatically.') }}</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Latitude (optional)') }}</flux:label>
                            <flux:input type="text" wire:model="geoLatitude" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Longitude (optional)') }}</flux:label>
                            <flux:input type="text" wire:model="geoLongitude" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Signer (optional)') }}</flux:label>
                            <select wire:model="geoSignerId" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option value="">{{ __('Entire case') }}</option>
                                @foreach ($requestSigners as $signer)
                                    <option value="{{ $signer->id }}">{{ $signer->full_name }}</option>
                                @endforeach
                            </select>
                        </flux:field>
                    </div>
                    <flux:button class="mt-3" variant="outline" type="button" wire:click="runBrowserGeoVerification">{{ __('Run location verification') }}</flux:button>
                    <flux:error name="runBrowserGeoVerification" />
                </div>

                @if ($geoHistory->isNotEmpty())
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Recent geo checks') }}</h3>
                        <div class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                            @foreach ($geoHistory as $log)
                                <div class="flex flex-wrap justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                    <span>{{ $log->verified_at?->toDateTimeString() ?? '-' }}</span>
                                    <span class="font-medium">{{ $log->verification_status->value }}</span>
                                    <span>{{ $log->country ?? '—' }} @if ($log->city) • {{ $log->city }} @endif</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @endif
            </div>

