<?php

namespace App\Models;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Mail\EmailOtpVerificationMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty(['first_name', 'middle_name', 'last_name', 'suffix'])) {
                $fullName = $user->buildFullName();

                if ($fullName !== '') {
                    $user->name = $fullName;
                }
            }
        });

        static::creating(function (User $user): void {
            if ($user->role === UserRole::SuperAdmin) {
                return;
            }

            if ($user->organization_id !== null) {
                return;
            }

            $organization = Organization::query()->create([
                'name' => ($user->name !== '' ? $user->name : 'New').' Organization',
                'slug' => Str::slug($user->name !== '' ? $user->name : 'organization').'-'.Str::lower(Str::random(8)),
                'plan' => 'free',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            if (! is_int($organization->id)) {
                throw new RuntimeException('Failed to create organization for user.');
            }

            $user->organization_id = $organization->id;
            $user->organization_role ??= OrganizationRole::Admin;
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'role',
        'workspace',
        'organization_role',
        'onboarding_step',
        'ekyc_status',
        'email_otp',
        'email_otp_expires_at',
        'mobile_number',
        'mobile_verified_at',
        'profile_photo_path',
        'address',
        'nationality',
        'date_of_birth',
        'government_id_type',
        'government_id_number',
        'kyc_id_type',
        'kyc_file_path',
        'kyc_verified_at',
        'selfie_verified_at',
        'gps_permission_granted_at',
        'signature_image_path',
        'signature_initials',
        'signature_type',
        'mfa_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'two_factor_onboarding_completed_at',
        'deactivated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organization_role' => OrganizationRole::class,
            'email_verified_at' => 'datetime',
            'ekyc_status' => EkycStatus::class,
            'onboarding_step' => OnboardingStep::class,
            'password' => 'hashed',
            'role' => UserRole::class,
            'workspace' => UserWorkspace::class,
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_onboarding_completed_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'kyc_verified_at' => 'datetime',
            'selfie_verified_at' => 'datetime',
            'gps_permission_granted_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function isPlatformOperator(): bool
    {
        return $this->isSuperAdmin();
    }

    public function needsTwoFactorOnboarding(): bool
    {
        return $this->two_factor_onboarding_completed_at === null;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_step === OnboardingStep::Completed
            && $this->mfa_enabled;
    }

    /**
     * Send the email verification notification (6-digit OTP, not a signed link).
     */
    public function sendEmailVerificationNotification(): void
    {
        $otp = sprintf('%06d', random_int(0, 999999));

        $this->forceFill([
            'email_otp' => $otp,
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($this)->send(new EmailOtpVerificationMail($otp));
    }

    /**
     * Named route for the authenticated user's current onboarding screen.
     */
    public function onboardingRouteName(): string
    {
        if ($this->mobile_verified_at === null && $this->onboarding_step === OnboardingStep::MobileVerification) {
            return 'onboarding.mobile';
        }

        if ($this->onboarding_step === OnboardingStep::Completed && ! $this->mfa_enabled) {
            return 'onboarding.mfa';
        }

        return match ($this->onboarding_step) {
            OnboardingStep::EmailVerification => 'onboarding.email.verify',
            OnboardingStep::MobileVerification => 'onboarding.mobile',
            OnboardingStep::Kyc => 'onboarding.kyc',
            OnboardingStep::Mfa => 'onboarding.mfa',
            default => 'onboarding.email.verify',
        };
    }

    public function hasVerifiedEkyc(): bool
    {
        return $this->ekyc_status === EkycStatus::Verified;
    }

    public function buildFullName(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value): string => trim($value))
            ->implode(' ');
    }

    public function resolvedFirstName(): string
    {
        if (filled($this->first_name)) {
            return trim((string) $this->first_name);
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return $parts[0] ?? '';
    }

    public function resolvedMiddleName(): string
    {
        if (filled($this->middle_name)) {
            return trim((string) $this->middle_name);
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if (count($parts) <= 2) {
            return '';
        }

        array_shift($parts);
        array_pop($parts);

        return implode(' ', $parts);
    }

    public function resolvedLastName(): string
    {
        if (filled($this->last_name)) {
            return trim((string) $this->last_name);
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if ($parts === []) {
            return '';
        }

        return $parts[count($parts) - 1];
    }

    public function displayFirstName(): string
    {
        return $this->resolvedFirstName();
    }

    public function displayLastName(): string
    {
        return $this->resolvedLastName();
    }

    public function homeRouteName(): string
    {
        if ($this->role === UserRole::Client) {
            return $this->workspace === UserWorkspace::Enotary
                ? 'settings.trust-profile'
                : 'documents.index';
        }

        return match ($this->role) {
            UserRole::Notary => 'notary.dashboard',
            UserRole::SuperAdmin => 'dashboard',
            UserRole::NotaryAdmin => 'admin.enotary.dashboard',
        };
    }

    public function canManageNotaryRequestPortal(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true);
    }

    public function isEnotaryPortalSigner(): bool
    {
        return $this->role === UserRole::Client
            && $this->workspace === UserWorkspace::Enotary;
    }

    public function isNotarySignerOn(NotaryRequest $notaryRequest): bool
    {
        if (! $this->isEnotaryPortalSigner()) {
            return false;
        }

        return $notaryRequest->signers()
            ->whereRaw('LOWER(email) = ?', [strtolower($this->email)])
            ->exists();
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->organization_role === OrganizationRole::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isNotaryAdmin(): bool
    {
        return $this->role === UserRole::NotaryAdmin;
    }

    public function isNotary(): bool
    {
        return $this->role === UserRole::Notary;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function canAccessWorkspaceTools(): bool
    {
        return $this->canAccessSigningWorkspace() || $this->canAccessEnotaryWorkspace();
    }

    public function canAccessSigningWorkspace(): bool
    {
        if (in_array($this->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true)) {
            return true;
        }

        if ($this->role !== UserRole::Client) {
            return false;
        }

        return $this->workspace === null || $this->workspace === UserWorkspace::Signing;
    }

    public function canAccessEnotaryWorkspace(): bool
    {
        if (in_array($this->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin, UserRole::Notary], true)) {
            return true;
        }

        if ($this->role !== UserRole::Client) {
            return false;
        }

        return $this->workspace === null || $this->workspace === UserWorkspace::Enotary;
    }

    public function canLoginViaSigningPortal(): bool
    {
        return $this->canAccessSigningWorkspace();
    }

    public function canLoginViaEnotaryPortal(): bool
    {
        return $this->canAccessEnotaryWorkspace();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function intendedHomeUrl(): string
    {
        return route($this->homeRouteName(), absolute: false);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<NotaryRequest, $this>
     */
    public function notaryRequests(): HasMany
    {
        return $this->hasMany(NotaryRequest::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_user_id')->latest('created_at');
    }

    /**
     * @return HasMany<EInvoice, $this>
     */
    public function eInvoices(): HasMany
    {
        return $this->hasMany(EInvoice::class, 'buyer_email', 'email')->latest('created_at');
    }

    /**
     * @return HasMany<NotaryRequest, $this>
     */
    public function assignedNotaryRequests(): HasMany
    {
        return $this->hasMany(NotaryRequest::class, 'notary_user_id');
    }

    /**
     * @return HasOne<NotaryCredential, $this>
     */
    public function notaryCredential(): HasOne
    {
        return $this->hasOne(NotaryCredential::class)->latestOfMany();
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class)->orderBy('name');
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('name');
    }

    /**
     * @return HasMany<AppNotification, $this>
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest('created_at');
    }

    /**
     * @return HasMany<Otp, $this>
     */
    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class)->latest('created_at');
    }

    /**
     * @return HasMany<MobileOtp, $this>
     */
    public function mobileOtps(): HasMany
    {
        return $this->hasMany(MobileOtp::class)->latest('created_at');
    }

    /**
     * @return HasOne<EkycRecord, $this>
     */
    public function ekycRecord(): HasOne
    {
        return $this->hasOne(EkycRecord::class)->latestOfMany();
    }

    /**
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class)->latest('last_used_at');
    }

    /**
     * @return HasMany<OnboardingAuditLog, $this>
     */
    public function onboardingAuditLogs(): HasMany
    {
        return $this->hasMany(OnboardingAuditLog::class)->latest('created_at');
    }
}
