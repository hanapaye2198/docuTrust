<?php

namespace App\Livewire;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DocumentSignersManager extends Component
{
    #[Locked]
    public int $documentId;

    public string $name = '';

    public string $email = '';

    /**
     * @var array<int, array{id: int, name: string, email: string}>
     */
    public array $contactSuggestions = [];

    public bool $suppressContactLookup = false;

    public function mount(int $documentId): void
    {
        $this->documentId = $documentId;
        $this->authorize('update', $this->resolveDocument());
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
        ]);

        $exists = $document->documentSigners()
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            $this->addError('email', __('This email is already a signer for this document.'));

            return;
        }

        $nextOrder = (int) $document->documentSigners()->max('signing_order') + 1;

        $document->documentSigners()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'access_token' => (string) Str::uuid(),
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => $nextOrder > 0 ? $nextOrder : 1,
            'expires_at' => null,
        ]);

        $this->ensureContactForSigner($document->user_id, $validated['name'], $validated['email']);

        $this->reset('name', 'email');
        $this->contactSuggestions = [];
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

        $this->dispatch('document-updated');
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
        return Document::query()->findOrFail($this->documentId);
    }
}
