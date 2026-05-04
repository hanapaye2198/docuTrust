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

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'file_path',
        'prepared_pdf_path',
        'final_pdf_path',
        'files',
        'certificate_path',
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
        return $this->final_pdf_path ?: $this->prepared_pdf_path ?: $this->sourcePdfPath();
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
            && $this->signersMissingFields()->isEmpty();
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
