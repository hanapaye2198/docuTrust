<?php

namespace App\Livewire;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DocumentSignersManager extends Component
{
    private ?Document $resolvedDocumentCache = null;

    #[Locked]
    public int $documentId;

    public string $name = '';

    public string $email = '';

    public string $signingMethod = SigningMethod::EmailLink->value;

    public string $roleType = TemplateRoleType::Signer->value;

    public string $signingWorkflow = Document::SIGNING_WORKFLOW_SEQUENTIAL;

    public ?int $editingSignerId = null;

    public string $editingName = '';

    public string $editingEmail = '';

    public string $editingSigningMethod = SigningMethod::EmailLink->value;

    public string $editingRoleType = TemplateRoleType::Signer->value;

    /**
     * @var array<int, array{id: int, name: string, email: string}>
     */
    public array $contactSuggestions = [];

    public bool $suppressContactLookup = false;

    public function mount(int $documentId): void
    {
        $this->documentId = $documentId;
        $document = $this->resolveDocument();
        $this->authorize('update', $document);
        $this->signingWorkflow = $document->signingWorkflow();
    }

    public function addSigner(): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft) {
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'signingMethod' => ['required', 'in:'.$this->allowedSigningMethods()],
            'roleType' => ['required', 'in:'.$this->allowedRoleTypes()],
        ]);

        $normalizedEmail = strtolower($validated['email']);
        $signingMethod = SigningMethod::from((string) $validated['signingMethod']);
        $roleType = TemplateRoleType::from((string) $validated['roleType']);

        $exists = $document->documentSigners()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();

        if ($exists) {
            $this->addError('email', __('This email is already a participant for this document.'));

            return;
        }

        $nextOrder = (int) $document->documentSigners()->max('signing_order') + 1;
        $linkedUserId = $this->resolveSignerUserId($document, $normalizedEmail, $signingMethod, $roleType);
        if ($this->roleTypeRequiresLinkedUser($roleType, $signingMethod) && $linkedUserId === null) {
            $this->addError('signingMethod', __('This signer method requires an existing verified DocuTrust account in your organization.'));

            return;
        }

        $document->documentSigners()->create([
            'role_type' => $roleType,
            'name' => $validated['name'],
            'email' => $normalizedEmail,
            'signing_method' => $signingMethod,
            'user_id' => $linkedUserId,
            'access_token' => (string) Str::uuid(),
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => $this->signingWorkflow === Document::SIGNING_WORKFLOW_SEQUENTIAL
                ? ($nextOrder > 0 ? $nextOrder : 1)
                : null,
            'expires_at' => null,
        ]);

        $this->ensureContactForSigner($document->user_id, $validated['name'], $normalizedEmail);
        $this->forgetResolvedDocument();

        $this->reset('name', 'email');
        $this->signingMethod = SigningMethod::EmailLink->value;
        $this->roleType = TemplateRoleType::Signer->value;
        $this->contactSuggestions = [];
        $this->dispatch('document-updated');
    }

    public function saveSigningWorkflow(): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft) {
            return;
        }

        $validated = $this->validate([
            'signingWorkflow' => ['required', 'in:'.Document::SIGNING_WORKFLOW_SEQUENTIAL.','.Document::SIGNING_WORKFLOW_PARALLEL],
        ]);

        $workflow = (string) $validated['signingWorkflow'];

        $document->update(['signing_workflow' => $workflow]);

        if ($workflow === Document::SIGNING_WORKFLOW_PARALLEL) {
            $document->documentSigners()->update(['signing_order' => null]);
        } else {
            $this->normalizeSigningOrder($document);
        }

        $this->forgetResolvedDocument();
        $this->dispatch('document-updated');
        session()->flash('status', __('Signing workflow updated.'));
    }

    public function startEditingSigner(int $signerId): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft) {
            return;
        }

        if ($document->signingWorkflow() !== Document::SIGNING_WORKFLOW_SEQUENTIAL) {
            return;
        }

        $signer = $document->documentSigners()->whereKey($signerId)->firstOrFail();

        $this->editingSignerId = $signer->id;
        $this->editingName = $signer->name;
        $this->editingEmail = $signer->email;
        $this->editingSigningMethod = $signer->signingMethod()->value;
        $this->editingRoleType = $signer->roleType()->value;
    }

    public function cancelEditingSigner(): void
    {
        $this->reset('editingSignerId', 'editingName', 'editingEmail', 'editingSigningMethod', 'editingRoleType');
    }

    public function saveSignerEdits(): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft || $this->editingSignerId === null) {
            return;
        }

        $validated = $this->validate([
            'editingName' => ['required', 'string', 'max:255'],
            'editingEmail' => ['required', 'email', 'max:255'],
            'editingSigningMethod' => ['required', 'in:'.$this->allowedSigningMethods()],
            'editingRoleType' => ['required', 'in:'.$this->allowedRoleTypes()],
        ]);

        $normalizedEmail = strtolower($validated['editingEmail']);
        $signingMethod = SigningMethod::from((string) $validated['editingSigningMethod']);
        $roleType = TemplateRoleType::from((string) $validated['editingRoleType']);

        $exists = $document->documentSigners()
            ->whereKeyNot($this->editingSignerId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();

        if ($exists) {
            $this->addError('editingEmail', __('This email is already a participant for this document.'));

            return;
        }

        $signer = $document->documentSigners()->whereKey($this->editingSignerId)->firstOrFail();
        $linkedUserId = $this->resolveSignerUserId($document, $normalizedEmail, $signingMethod, $roleType);
        if ($this->roleTypeRequiresLinkedUser($roleType, $signingMethod) && $linkedUserId === null) {
            $this->addError('editingSigningMethod', __('This signer method requires an existing verified DocuTrust account in your organization.'));

            return;
        }

        $signer->update([
            'role_type' => $roleType,
            'name' => $validated['editingName'],
            'email' => $normalizedEmail,
            'signing_method' => $signingMethod,
            'user_id' => $linkedUserId,
        ]);

        $this->ensureContactForSigner($document->user_id, $validated['editingName'], $normalizedEmail);
        $this->forgetResolvedDocument();
        $this->reset('editingSignerId', 'editingName', 'editingEmail', 'editingSigningMethod', 'editingRoleType');
        $this->dispatch('document-updated');
    }

    public function updatedName(): void
    {
        if ($this->suppressContactLookup) {
            return;
        }

        $this->refreshContactSuggestions();
    }

    public function updatedEmail(): void
    {
        if ($this->suppressContactLookup) {
            return;
        }

        $this->refreshContactSuggestions();
    }

    public function selectContact(int $contactId): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        $this->suppressContactLookup = true;

        try {
            $contact = Contact::query()
                ->where('user_id', $document->user_id)
                ->findOrFail($contactId);

            $this->contactSuggestions = [];
            $this->name = $contact->name;
            $this->email = $contact->email;
        } finally {
            $this->suppressContactLookup = false;
        }
    }

    /**
     * Persist a contact when the signer email is not already saved.
     */
    protected function ensureContactForSigner(int $userId, string $name, string $email): void
    {
        $normalized = strtolower($email);

        $exists = Contact::query()
            ->where('user_id', $userId)
            ->where('email', $normalized)
            ->exists();

        if ($exists) {
            return;
        }

        Contact::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'email' => $normalized,
            'phone' => null,
            'company' => null,
        ]);
    }

    protected function refreshContactSuggestions(): void
    {
        $term = trim($this->name) !== '' ? trim($this->name) : trim($this->email);

        if (strlen($term) < 2) {
            $this->contactSuggestions = [];

            return;
        }

        $document = $this->resolveDocument();

        $like = '%'.$term.'%';

        $this->contactSuggestions = Contact::query()
            ->where('user_id', $document->user_id)
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ])
            ->all();
    }

    public function removeSigner(int $signerId): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft) {
            return;
        }

        $signer = $document->documentSigners()->whereKey($signerId)->firstOrFail();
        $signer->delete();
        $this->normalizeSigningOrder($document);
        $this->forgetResolvedDocument();

        $this->dispatch('document-updated');
    }

    public function moveSignerUp(int $signerId): void
    {
        $this->moveSigner($signerId, -1);
    }

    public function moveSignerDown(int $signerId): void
    {
        $this->moveSigner($signerId, 1);
    }

    public function render(): View
    {
        $document = $this->resolveDocument()->load([
            'documentSigners' => fn ($query) => $query->orderBy('signing_order')->orderBy('id'),
        ]);

        return view('livewire.document-signers-manager', [
            'document' => $document,
        ]);
    }

    /**
     * Resolve the current document for this component.
     */
    protected function resolveDocument(): Document
    {
        return $this->resolvedDocumentCache
            ??= Document::query()->findOrFail($this->documentId);
    }

    protected function forgetResolvedDocument(): void
    {
        $this->resolvedDocumentCache = null;
    }

    protected function moveSigner(int $signerId, int $direction): void
    {
        $document = $this->resolveDocument();
        $this->authorize('update', $document);

        if ($document->status !== DocumentStatus::Draft) {
            return;
        }

        $signers = $document->documentSigners()
            ->orderBy('signing_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $signers->search(fn (DocumentSigner $signer): bool => $signer->id === $signerId);
        if (! is_int($index)) {
            return;
        }

        $swapIndex = $index + $direction;
        if ($swapIndex < 0 || $swapIndex >= $signers->count()) {
            return;
        }

        $current = $signers[$index];
        $swap = $signers[$swapIndex];

        $currentOrder = $current->signing_order;
        $current->update(['signing_order' => $swap->signing_order]);
        $swap->update(['signing_order' => $currentOrder]);

        $this->normalizeSigningOrder($document);
        $this->forgetResolvedDocument();
        $this->dispatch('document-updated');
    }

    protected function normalizeSigningOrder(Document $document): void
    {
        if ($document->signingWorkflow() !== Document::SIGNING_WORKFLOW_SEQUENTIAL) {
            $document->documentSigners()->update(['signing_order' => null]);

            return;
        }

        $document->documentSigners()
            ->orderBy('signing_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (DocumentSigner $signer, int $index): void {
                $signer->update(['signing_order' => $index + 1]);
            });
    }

    private function allowedSigningMethods(): string
    {
        return collect(SigningMethod::cases())->pluck('value')->implode(',');
    }

    private function allowedRoleTypes(): string
    {
        return collect(TemplateRoleType::activeCases())->pluck('value')->implode(',');
    }

    private function resolveSignerUserId(Document $document, string $email, SigningMethod $signingMethod, TemplateRoleType $roleType): ?int
    {
        if (! $this->roleTypeRequiresLinkedUser($roleType, $signingMethod)) {
            return null;
        }

        return User::query()
            ->where('organization_id', $document->organization_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->whereNotNull('email_verified_at')
            ->value('id');
    }

    private function roleTypeRequiresLinkedUser(TemplateRoleType $roleType, SigningMethod $signingMethod): bool
    {
        return $signingMethod === SigningMethod::AccountVerified
            && $roleType !== TemplateRoleType::Recipient;
    }
}
