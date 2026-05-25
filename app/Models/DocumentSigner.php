<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use Database\Factories\DocumentSignerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSigner extends Model
{
    /** @use HasFactory<DocumentSignerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'role_name',
        'role_type',
        'name',
        'email',
        'signing_method',
        'user_id',
        'remote_credential_id',
        'access_token',
        'status',
        'signing_order',
        'allowed_pages',
        'signed_at',
        'expires_at',
        'signing_public_key',
        'signing_private_key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'signing_private_key',
    ];

    public function getRouteKeyName(): string
    {
        return 'access_token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentSignerStatus::class,
            'role_type' => TemplateRoleType::class,
            'signing_method' => SigningMethod::class,
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'signing_private_key' => 'encrypted',
            'allowed_pages' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class, 'signer_id');
    }

    /**
     * @return HasMany<SignerCertificate, $this>
     */
    public function signerCertificates(): HasMany
    {
        return $this->hasMany(SignerCertificate::class);
    }

    /**
     * @return HasMany<SignatureField, $this>
     */
    public function signatureFields(): HasMany
    {
        return $this->hasMany(SignatureField::class, 'signer_id');
    }

    /**
     * @return HasMany<TrustAuthorizationSession, $this>
     */
    public function trustAuthorizationSessions(): HasMany
    {
        return $this->hasMany(TrustAuthorizationSession::class, 'document_signer_id');
    }

    public function signingMethod(): SigningMethod
    {
        return $this->signing_method instanceof SigningMethod
            ? $this->signing_method
            : SigningMethod::EmailLink;
    }

    public function roleType(): TemplateRoleType
    {
        return $this->role_type instanceof TemplateRoleType
            ? $this->role_type
            : TemplateRoleType::Signer;
    }

    public function isSigner(): bool
    {
        return $this->roleType() === TemplateRoleType::Signer;
    }

    public function isApprover(): bool
    {
        return $this->roleType() === TemplateRoleType::Approver;
    }

    public function isRecipient(): bool
    {
        return $this->roleType() === TemplateRoleType::Recipient;
    }

    public function requiresAction(): bool
    {
        return ! $this->isRecipient();
    }

    public function hasCompletedAction(): bool
    {
        return $this->status instanceof DocumentSignerStatus
            ? $this->status->isCompleted()
            : false;
    }

    /**
     * Check if this signer is allowed to sign on the given page number.
     * A null allowed_pages value means all pages are allowed.
     */
    public function isAllowedOnPage(int $pageNumber): bool
    {
        $pages = $this->allowed_pages;

        if ($pages === null || $pages === []) {
            return true;
        }

        return in_array($pageNumber, $pages, true);
    }
}
