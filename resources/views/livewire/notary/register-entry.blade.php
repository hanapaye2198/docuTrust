<?php

use App\Enums\UserRole;
use App\Models\AttorneyNotarialRegistry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Services\AttorneyNotarialRegistryService;
use App\Services\GatewayHubService;
use App\Services\NotarialCertificateService;
use App\Services\NotarialRegisterService;
use App\Services\NotaryNotificationService;
use App\Services\NotaryPaymentService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySealService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryRequest $notaryRequest;

    public string $pageNumber = '';

    public string $bookNumber = '';

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);
        abort_unless($notaryRequest->notary_user_id === $user->id, 403);

        abort_unless(
            app(NotaryRequestWorkflowService::class)->canCreateRegisterEntry($notaryRequest),
            403,
            __('Register entry can only be created after attorney signing, payment, and attorney seal completion.')
        );

        $notaryRequest->loadMissing('attorneyNotarialRegistry');

        if (! $notaryRequest->attorneyNotarialRegistry instanceof AttorneyNotarialRegistry) {
            session()->flash('status', __('Save the notarial register entry first, then return here to create the official book record.'));
            $this->redirect(route('notary.attorney-registry', $notaryRequest), navigate: true);

            return;
        }

        $this->notaryRequest = $notaryRequest;
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $draft = $this->notaryRequest->attorneyNotarialRegistry;
        abort_unless($draft instanceof AttorneyNotarialRegistry, 404);

        $this->validate([
            'pageNumber' => ['nullable', 'integer', 'min:1'],
            'bookNumber' => ['nullable', 'string', 'max:32'],
        ]);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($credential === null || ! $credential->isActive()) {
            $this->addError('save', __('You must have an active notary commission to create register entries. Please update your credentials.'));

            return;
        }

        $document = $this->notaryRequest->documents()->first();

        try {
            $entry = app(NotarialRegisterService::class)->createEntry(
                $this->notaryRequest,
                $credential,
                [
                    'document_title' => (string) $draft->title,
                    'document_description' => $draft->description,
                    'parties' => is_array($draft->parties) ? $draft->parties : [],
                    'witnesses' => is_array($draft->witnesses) ? $draft->witnesses : [],
                    'competent_evidence' => is_array($draft->competent_evidence) ? $draft->competent_evidence : [],
                    'notarial_act_type' => (string) $draft->notarial_act_type,
                    'fees' => (float) $draft->fees,
                    'official_receipt_number' => $draft->official_receipt_no,
                    'page_number' => $this->pageNumber !== '' ? (int) $this->pageNumber : null,
                    'book_number' => trim($this->bookNumber) !== '' ? trim($this->bookNumber) : null,
                ],
                $document,
            );

            app(NotarySealService::class)->generateVerificationQrCode($entry);
            app(NotarialCertificateService::class)->generate($entry);

            $statusMessage = __('Official register entry :number created successfully.', [
                'number' => str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT),
            ]);

            $paymentNote = $this->attemptAutoCreatePayment();
            if ($paymentNote !== null) {
                $statusMessage .= ' '.$paymentNote;
            }

            session()->flash('status', $statusMessage);

            $this->redirect(route('notary.requests.show', ['notaryRequest' => $this->notaryRequest, 'tab' => 'closing']), navigate: true);
        } catch (\RuntimeException $e) {
            $this->addError('save', $e->getMessage());
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $registryService = app(AttorneyNotarialRegistryService::class);
        $draftState = $registryService->draftStateForRequest($this->notaryRequest, $user);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return [
            'draft' => $this->notaryRequest->attorneyNotarialRegistry,
            'title' => $draftState['title'],
            'notarialActType' => $draftState['notarial_act_type'],
            'fees' => $draftState['fees'],
            'officialReceiptNo' => $draftState['official_receipt_no'],
            'parties' => $draftState['parties'],
            'witnesses' => $draftState['witnesses'],
            'competentEvidence' => $draftState['competent_evidence'],
            'previewEntryNumber' => $draftState['preview_entry_number'],
            'signatureImagePath' => $draftState['signature_image_path'],
            'registryService' => $registryService,
            'notarialActTypes' => $registryService->notarialActTypes(),
            'credentialId' => $credential?->id,
            'existingEntries' => $this->notaryRequest->registerEntries()->with('notaryCredential')->get(),
            'readOnly' => true,
            'signerSignatures' => $draftState['signer_signatures'],
            'orEditable' => $draftState['or_editable'],
            'feesEditable' => false,
        ];
    }

    private function attemptAutoCreatePayment(): ?string
    {
        $draft = $this->notaryRequest->attorneyNotarialRegistry;
        if (! $draft instanceof AttorneyNotarialRegistry || (float) $draft->fees <= 0) {
            return null;
        }

        $workflow = app(NotaryRequestWorkflowService::class);
        $request = $this->notaryRequest->fresh(['registerEntries', 'payments', 'attorneyNotarialRegistry']);

        if ($workflow->hasSettledPayment($request)) {
            return null;
        }

        if ($request->payments->contains(fn ($payment): bool => $payment->status === \App\Enums\PaymentStatus::Pending)) {
            return null;
        }

        try {
            $gateways = app(GatewayHubService::class)->enabledGateways();
            $gatewayCode = collect($gateways)
                ->pluck('code')
                ->first(fn ($code) => is_string($code) && $code !== '');

            if (! is_string($gatewayCode) || $gatewayCode === '') {
                return __('No payment gateway is currently available. Create the payment later from the case page.');
            }

            $payment = app(NotaryPaymentService::class)->createGatewayPayment(
                $request,
                $gatewayCode,
                Auth::id(),
            );

            app(NotaryNotificationService::class)->notifyPaymentReady(
                $this->notaryRequest->fresh(['requester', 'notary']),
                $payment,
            );

            return __('A payment link is now ready for the client.');
        } catch (\Throwable $exception) {
            report($exception);

            return __('The register entry was saved, but the payment link could not be created automatically. Create it from the case page.');
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-[1400px] flex-col gap-6">
    <header>
        <flux:button
            variant="ghost"
            size="sm"
            :href="route('notary.attorney-registry', $notaryRequest)"
            wire:navigate
            icon="arrow-left"
            class="mb-3"
        >
            {{ __('Edit register entry') }}
        </flux:button>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
            {{ __('Confirm official register entry') }}
        </h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Review the saved register row below. Creating the official entry assigns the entry number and notarization timestamp.') }}
        </p>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('save')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @if ($existingEntries->isNotEmpty())
        <div class="ui-panel p-5">
            <flux:heading size="sm" class="mb-3">{{ __('Already recorded in the notarial book') }}</flux:heading>
            <div class="space-y-2">
                @foreach ($existingEntries as $existing)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm dark:border-emerald-900/40 dark:bg-emerald-950/30">
                        {{ __('Entry :number — :title', [
                            'number' => str_pad((string) $existing->entry_number, 3, '0', STR_PAD_LEFT),
                            'title' => $existing->document_title,
                        ]) }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('livewire.notary.partials.notarial-register-entry-table', [
        'readOnly' => true,
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

    <div class="ui-panel p-5 sm:p-6">
        <flux:heading size="sm" class="mb-4">{{ __('Register book location (optional)') }}</flux:heading>
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Page number') }}</flux:label>
                <flux:input wire:model="pageNumber" type="number" min="1" placeholder="1" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Book number') }}</flux:label>
                <flux:input wire:model="bookNumber" type="text" placeholder="I" />
            </flux:field>
            <div class="sm:col-span-2 flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Create official register entry') }}</span>
                    <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing'])" wire:navigate type="button">
                    {{ __('Back to case') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
