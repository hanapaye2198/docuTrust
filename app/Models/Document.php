<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\TemplateRoleType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const SIGNING_WORKFLOW_SEQUENTIAL = 'sequential';

    public const SIGNING_WORKFLOW_PARALLEL = 'parallel';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'notary_request_id',
        'user_id',
        'title',
        'file_path',
        'email_subject',
        'email_message',
        'audit_enabled',
        'audit_settings',
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
        'csc_signed',
        'pades_byte_range',
        'pades_cms_signature',
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
            'audit_enabled' => 'boolean',
            'audit_settings' => 'array',
            'csc_signed' => 'boolean',
            'pades_byte_range' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultAuditSettings(): array
    {
        return [
            'show_email' => true,
            'show_document_id' => true,
            'show_author' => true,
            'show_mobile' => false,
            'show_id_details' => false,
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
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
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

    public function isAuditTrailEnabled(): bool
    {
        return $this->audit_enabled ?? true;
    }

    /**
     * @return array<string, bool>
     */
    public function resolvedAuditSettings(): array
    {
        return array_merge(
            self::defaultAuditSettings(),
            is_array($this->audit_settings) ? $this->audit_settings : [],
        );
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

    public function hasSigningParticipants(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->contains(fn (DocumentSigner $signer) => $signer->isSigner());
        }

        return $this->documentSigners()->where('role_type', TemplateRoleType::Signer)->exists();
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
                ->filter(fn (DocumentSigner $signer) => $signer->isSigner() && ! in_array($signer->id, $fieldSignerIds, true))
                ->values();
        }

        return $this->documentSigners()
            ->where('role_type', TemplateRoleType::Signer)
            ->whereDoesntHave('signatureFields')
            ->get();
    }

    public function canPrepareForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->hasSigningParticipants();
    }

    public function canSendForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft
            && $this->hasSigningParticipants()
            && $this->hasSignatureFields()
            && $this->signersMissingFields()->isEmpty()
            && $this->workflowConfigurationIsValid();
    }

    public function allSignersHaveSigned(): bool
    {
        $signers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners->filter(fn (DocumentSigner $signer) => $signer->isSigner())->values()
            : $this->documentSigners()->where('role_type', TemplateRoleType::Signer)->get();

        if ($signers->isEmpty()) {
            return false;
        }

        return $signers->every(
            fn (DocumentSigner $signer) => $signer->status === DocumentSignerStatus::Signed
        );
    }

    public function allApproversHaveApproved(): bool
    {
        $approvers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners->filter(fn (DocumentSigner $signer) => $signer->isApprover())->values()
            : $this->documentSigners()->where('role_type', TemplateRoleType::Approver)->get();

        if ($approvers->isEmpty()) {
            return true;
        }

        return $approvers->every(
            fn (DocumentSigner $signer) => $signer->status === DocumentSignerStatus::Approved
        );
    }

    public function hasActionableParticipants(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->contains(fn (DocumentSigner $signer) => $signer->requiresAction());
        }

        return $this->documentSigners()->whereIn('role_type', [
            TemplateRoleType::Signer->value,
            TemplateRoleType::Approver->value,
        ])->exists();
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
            ->values();

        if ($orders->count() !== $signers->count()) {
            return false;
        }

        if ($orders->contains(fn (int $order): bool => $order < 1)) {
            return false;
        }

        return $orders->unique()->count() === $orders->count();
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
