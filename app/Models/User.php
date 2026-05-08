<?php

namespace App\Models;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use RuntimeException;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
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
        'email',
        'password',
        'role',
        'organization_role',
        'onboarding_step',
        'ekyc_status',
        'email_otp',
        'email_otp_expires_at',
        'mobile_number',
        'mobile_verified_at',
        'kyc_id_type',
        'kyc_file_path',
        'kyc_verified_at',
        'mfa_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'two_factor_onboarding_completed_at',
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
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_onboarding_completed_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'mfa_enabled' => 'boolean',
        ];
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
     * Named route for the authenticated user's current onboarding screen.
     */
    public function onboardingRouteName(): string
    {
        if ($this->mobile_verified_at === null && $this->onboarding_step !== OnboardingStep::EmailVerification) {
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

    public function homeRouteName(): string
    {
        return match ($this->role) {
            UserRole::Signer => 'documents.index',
            UserRole::Notary => 'notary.dashboard',
            UserRole::Admin => 'dashboard',
        };
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->organization_role === OrganizationRole::Admin;
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
}
