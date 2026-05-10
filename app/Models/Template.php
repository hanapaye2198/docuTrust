<?php

namespace App\Models;

use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'files',
        'document_workflow',
        'email_subject',
        'email_message',
        'signing_method',
        'audit_enabled',
        'audit_settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'files' => 'array',
            'document_workflow' => 'boolean',
            'audit_enabled' => 'boolean',
            'audit_settings' => 'array',
            'signing_method' => TemplateSigningMethod::class,
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
     * @return HasMany<TemplateSigner, $this>
     */
    public function templateSigners(): HasMany
    {
        return $this->hasMany(TemplateSigner::class)
            ->orderByRaw('CASE WHEN signing_order IS NULL THEN 999999 ELSE signing_order END')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TemplateField, $this>
     */
    public function templateFields(): HasMany
    {
        return $this->hasMany(TemplateField::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'template_tag');
    }

    /**
     * @return Collection<int, TemplateSigner>
     */
    public function signerRoles()
    {
        return $this->templateSigners()
            ->where('role_type', TemplateRoleType::Signer)
            ->get();
    }

    /**
     * First PDF path in `files` for preview / field placement.
     */
    public function primaryPdfPath(): ?string
    {
        foreach ($this->files ?? [] as $path) {
            if (is_string($path) && str_ends_with(strtolower($path), '.pdf')) {
                return $path;
            }
        }

        return null;
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

    protected static function booted(): void
    {
        static::creating(function (Template $template): void {
            if ($template->organization_id !== null || $template->user_id === null) {
                return;
            }

            $template->organization_id = User::query()->whereKey($template->user_id)->value('organization_id');
        });
    }
}
