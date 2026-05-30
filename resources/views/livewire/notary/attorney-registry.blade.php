<?php

use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Services\AttorneyNotarialRegistryService;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryRequest $notaryRequest;

    public string $title = '';

    public string $notarialActType = 'acknowledgment';

    public string $fees = '';

    public string $officialReceiptNo = '';

    /** @var list<array{name: string, address: string}> */
    public array $parties = [];

    /** @var list<array{name: string, address: string}> */
    public array $witnesses = [];

    /** @var list<array{person_name: string, id_type: string, id_number: string, verification_id: int|null, id_image_path: string|null}> */
    public array $competentEvidence = [];

    public ?int $previewEntryNumber = null;

    public ?string $signatureImagePath = null;

    public ?int $credentialId = null;

    public int $prefilledSignerCount = 0;

    public int $verifiedIdentityCount = 0;

    /** @var list<array{document_signer_id: int, document_id: int, signature_id: int, name: string, signature_path: string|null}> */
    public array $signerSignatures = [];

    public bool $orEditable = false;

    public bool $feesEditable = true;

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);
        abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);

        $workflow = app(NotaryRequestWorkflowService::class);

        abort_unless(
            $workflow->hasAttorneySignedAllDocuments($notaryRequest),
            403,
            __('Notarial register entry becomes available after the attorney signs the document.')
        );

        if (! $workflow->canAccessAttorneyRegistry($notaryRequest)) {
            session()->flash('status', __('Complete client payment on the Settlement tab before opening the notarial register entry.'));
            $this->redirect(route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing']), navigate: true);

            return;
        }

        $this->notaryRequest = $notaryRequest;

        $state = app(AttorneyNotarialRegistryService::class)->draftStateForRequest($notaryRequest, $user);

        $this->title = $state['title'];
        $this->notarialActType = $state['notarial_act_type'];
        $this->fees = $state['fees'];
        $this->officialReceiptNo = $state['official_receipt_no'];
        $this->parties = $state['parties'];
        $this->witnesses = $state['witnesses'];
        $this->competentEvidence = $state['competent_evidence'];
        $this->previewEntryNumber = $state['preview_entry_number'];
        $this->signatureImagePath = $state['signature_image_path'];
        $this->prefilledSignerCount = $state['prefilled_signer_count'];
        $this->verifiedIdentityCount = $state['verified_identity_count'];
        $this->signerSignatures = $state['signer_signatures'];
        $this->orEditable = $state['or_editable'];
        $this->feesEditable = $state['fees_editable'];

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $this->credentialId = $credential?->id;
    }

    public function addWitness(): void
    {
        $this->witnesses[] = ['name' => '', 'address' => ''];
    }

    public function removeWitness(int $index): void
    {
        unset($this->witnesses[$index]);
        $this->witnesses = array_values($this->witnesses);
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $registryService = app(AttorneyNotarialRegistryService::class);
        $requiresOr = $registryService->paymentRequired($this->notaryRequest)
            && $registryService->hasSettledPayment($this->notaryRequest)
            && (float) ($this->notaryRequest->attorneyNotarialRegistry?->fees ?? $this->fees) > 0;

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'notarialActType' => ['required', 'in:acknowledgment,jurat,affidavit,oath,other'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'officialReceiptNo' => [$requiresOr ? 'required' : 'nullable', 'string', 'max:100'],
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.name' => ['required', 'string', 'max:255'],
            'parties.*.address' => ['required', 'string', 'max:500'],
            'witnesses' => ['array'],
            'witnesses.*.name' => ['nullable', 'string', 'max:255'],
            'witnesses.*.address' => ['nullable', 'string', 'max:500'],
            'competentEvidence' => ['required', 'array', 'min:1'],
            'competentEvidence.*.person_name' => ['required', 'string', 'max:255'],
            'competentEvidence.*.id_type' => ['required', 'string', 'max:100'],
            'competentEvidence.*.id_number' => ['required', 'string', 'max:100'],
        ]);

        $fees = $this->feesEditable
            ? (float) ($validated['fees'] ?? 0)
            : (float) ($this->notaryRequest->attorneyNotarialRegistry?->fees ?? 0);

        $registryService->saveDraft($this->notaryRequest, $user, [
            'title' => $validated['title'],
            'notarial_act_type' => $validated['notarialActType'],
            'fees' => $fees,
            'official_receipt_no' => $this->orEditable ? ($validated['officialReceiptNo'] ?? null) : null,
            'parties' => $validated['parties'],
            'witnesses' => $validated['witnesses'] ?? [],
            'competent_evidence' => $validated['competentEvidence'],
        ]);

        session()->flash('status', __('Register entry saved. Upload your seal and create the official book entry when ready.'));
        $this->redirect(route('notary.requests.show', ['notaryRequest' => $this->notaryRequest, 'tab' => 'closing']), navigate: true);
    }

    public function with(): array
    {
        $registryService = app(AttorneyNotarialRegistryService::class);

        return [
            'notarialActTypes' => $registryService->notarialActTypes(),
            'registryService' => $registryService,
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-[1400px] flex-col gap-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <flux:button
                variant="ghost"
                size="sm"
                :href="route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing'])"
                wire:navigate
                icon="arrow-left"
                class="mb-3"
            >
                {{ __('Back to case') }}
            </flux:button>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
                {{ __('Notarial Register Entry (9 Required Fields)') }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Case: :title — complete the register row after payment. Enter the O.R. number and confirm captured signer signatures.', ['title' => $notaryRequest->title]) }}
            </p>
            @if ($prefilledSignerCount > 0 || $verifiedIdentityCount > 0)
                <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">
                    {{ __('Prefilled from :signers signer(s) and :verified verified ID record(s).', [
                        'signers' => $prefilledSignerCount,
                        'verified' => $verifiedIdentityCount,
                    ]) }}
                </p>
            @endif
        </div>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="flex flex-col gap-0">
        @include('livewire.notary.partials.notarial-register-entry-table', [
            'readOnly' => false,
            'notaryRequest' => $notaryRequest,
            'title' => $title,
            'notarialActType' => $notarialActType,
            'fees' => $fees,
            'officialReceiptNo' => $officialReceiptNo,
            'parties' => $parties,
            'witnesses' => $witnesses,
            'competentEvidence' => $competentEvidence,
            'previewEntryNumber' => $previewEntryNumber,
            'signatureImagePath' => $signatureImagePath,
            'credentialId' => $credentialId,
            'registryService' => $registryService,
            'notarialActTypes' => $notarialActTypes,
            'signerSignatures' => $signerSignatures,
            'orEditable' => $orEditable,
            'feesEditable' => $feesEditable,
        ])

        <div class="ui-panel flex flex-col gap-4 border-t border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900/50 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Total entries for this case: :count', ['count' => 1]) }}
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="ghost" :href="route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing'])" wire:navigate type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Save entry') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
