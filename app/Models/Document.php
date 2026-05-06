<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    public const SIGNING_WORKFLOW_SEQUENTIAL = 'sequential';

    public const SIGNING_WORKFLOW_PARALLEL = 'parallel';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'file_path',
        'access_password_hash',
        'access_password_hint',
        'signing_workflow',
        'prepared_pdf_path',
        'final_pdf_path',
        'files',
        'certificate_path',
        'archive_storage_disk',
        'archive_document_path',
        'archive_certificate_path',
        'archived_at',
        'status',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'sent_at' => 'datetime',
            'archived_at' => 'datetime',
            'files' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<DocumentSigner, $this>
     */
    public function documentSigners(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }

    /**
     * Backward-compatible alias used by some controllers/views.
     *
     * @return HasMany<DocumentSigner, $this>
     */
    public function signers(): HasMany
    {
        return $this->documentSigners();
    }

    /**
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * @return HasMany<SignatureField, $this>
     */
    public function signatureFields(): HasMany
    {
        return $this->hasMany(SignatureField::class);
    }

    /**
     * @return HasMany<SignatureAuditEvent, $this>
     */
    public function signatureAuditEvents(): HasMany
    {
        return $this->hasMany(SignatureAuditEvent::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tag');
    }

    /**
     * @return HasOne<DocumentHash, $this>
     */
    public function documentHash(): HasOne
    {
        return $this->hasOne(DocumentHash::class);
    }

    /**
     * First PDF path: optional `files` JSON array, otherwise `file_path` when it is a PDF.
     */
    public function primaryPdfPath(): ?string
    {
        foreach ($this->files ?? [] as $path) {
            if (is_string($path) && str_ends_with(strtolower($path), '.pdf') && $path !== '') {
                return $path;
            }
        }

        if (is_string($this->file_path) && $this->file_path !== '' && str_ends_with(strtolower($this->file_path), '.pdf')) {
            return $this->file_path;
        }

        return null;
    }

    public function sourcePdfPath(): ?string
    {
        return $this->primaryPdfPath();
    }

    public function activeSigningPdfPath(): ?string
    {
        return $this->prepared_pdf_path ?: $this->sourcePdfPath();
    }

    public function previewPdfPath(): ?string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archive_document_path;
        }

        return $this->final_pdf_path ?: $this->prepared_pdf_path ?: $this->sourcePdfPath();
    }

    public function previewPdfDisk(): string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archiveDisk();
        }

        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function verifiablePdfPath(): ?string
    {
        if (is_string($this->final_pdf_path) && $this->final_pdf_path !== '' && str_ends_with(strtolower($this->final_pdf_path), '.pdf')) {
            return $this->final_pdf_path;
        }

        return null;
    }

    public function hasAccessPassword(): bool
    {
        return is_string($this->access_password_hash) && $this->access_password_hash !== '';
    }

    public function signingWorkflow(): string
    {
        $workflow = (string) ($this->signing_workflow ?? self::SIGNING_WORKFLOW_SEQUENTIAL);

        return in_array($workflow, [self::SIGNING_WORKFLOW_SEQUENTIAL, self::SIGNING_WORKFLOW_PARALLEL], true)
            ? $workflow
            : self::SIGNING_WORKFLOW_SEQUENTIAL;
    }

    public function usesSequentialSigningWorkflow(): bool
    {
        return $this->signingWorkflow() === self::SIGNING_WORKFLOW_SEQUENTIAL;
    }

    public function archiveDisk(): string
    {
        return (string) ($this->archive_storage_disk ?: config('filesystems.docutrust_archive_disk', config('filesystems.docutrust_disk', 'local')));
    }

    public function finalDownloadPath(): ?string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archive_document_path;
        }

        return $this->previewPdfPath();
    }

    public function hasArchivedFinalDocument(): bool
    {
        return is_string($this->archive_document_path) && $this->archive_document_path !== '';
    }

    public function hasDocumentSigners(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->isNotEmpty();
        }

        return $this->documentSigners()->exists();
    }

    public function hasSignatureFields(): bool
    {
        if ($this->relationLoaded('signatureFields')) {
            return $this->signatureFields->isNotEmpty();
        }

        return $this->signatureFields()->exists();
    }

    /**
     * @return EloquentCollection<int, DocumentSigner>
     */
    public function signersMissingFields(): EloquentCollection
    {
        if ($this->relationLoaded('documentSigners') && $this->relationLoaded('signatureFields')) {
            $fieldSignerIds = $this->signatureFields
                ->pluck('signer_id')
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            return $this->documentSigners
                ->filter(fn (DocumentSigner $signer) => ! in_array($signer->id, $fieldSignerIds, true))
                ->values();
        }

        return $this->documentSigners()
            ->whereDoesntHave('signatureFields')
            ->get();
    }

    public function canPrepareForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->hasDocumentSigners();
    }

    public function canSendForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft
            && $this->hasDocumentSigners()
            && $this->hasSignatureFields()
            && $this->signersMissingFields()->isEmpty()
            && $this->workflowConfigurationIsValid();
    }

    public function allSignersHaveSigned(): bool
    {
        if ($this->documentSigners->isEmpty()) {
            return false;
        }

        return $this->documentSigners->every(
            fn (DocumentSigner $signer) => $signer->status === DocumentSignerStatus::Signed
        );
    }

    public function workflowConfigurationIsValid(): bool
    {
        if (! $this->usesSequentialSigningWorkflow()) {
            return true;
        }

        $signers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners
            : $this->documentSigners()->get();

        if ($signers->isEmpty()) {
            return true;
        }

        $orders = $signers
            ->pluck('signing_order')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        if ($orders->count() !== $signers->count()) {
            return false;
        }

        return $orders->all() === range(1, $signers->count());
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if ($document->organization_id !== null) {
                return;
            }

            if ($document->user_id === null) {
                return;
            }

            $document->organization_id = User::query()->whereKey($document->user_id)->value('organization_id');
        });
    }
}
