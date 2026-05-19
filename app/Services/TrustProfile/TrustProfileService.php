<?php

namespace App\Services\TrustProfile;

use App\Enums\EkycStatus;
use App\Enums\UserRole;
use App\Models\DocumentSigner;
use App\Models\OnboardingAuditLog;
use App\Models\SignatureAuditEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrustProfileService
{
    /**
     * @return array{
     *     trust_score: int,
     *     completion_percent: int,
     *     trust_tier: string,
     *     badge: string,
     *     badge_label: string,
     *     role_label: string,
     *     enotary_ready: bool,
     *     enotary_ready_count: int,
     *     enotary_total: int
     * }
     */
    public function summary(User $user): array
    {
        $checks = $this->enotaryReadinessChecks($user);
        $trustScore = $this->trustScore($user);

        return [
            'trust_score' => $trustScore,
            'completion_percent' => $this->completionPercent($user),
            'trust_tier' => $this->trustTier($trustScore),
            'badge' => $this->verificationBadge($user),
            'badge_label' => $this->verificationBadgeLabel($this->verificationBadge($user)),
            'role_label' => $this->roleLabel($user),
            'enotary_ready' => $this->isEnotaryReady($user),
            'enotary_ready_count' => collect($checks)->where('met', true)->count(),
            'enotary_total' => count($checks),
        ];
    }

    /**
     * @return list<array{key: string, label: string, completed: bool, weight: int}>
     */
    public function completionFields(User $user): array
    {
        return [
            ['key' => 'photo', 'label' => __('Profile photo'), 'completed' => filled($user->profile_photo_path), 'weight' => 8],
            ['key' => 'name', 'label' => __('Legal name'), 'completed' => filled($user->first_name) && filled($user->last_name), 'weight' => 10],
            ['key' => 'email', 'label' => __('Email verified'), 'completed' => $user->hasVerifiedEmail(), 'weight' => 12],
            ['key' => 'mobile', 'label' => __('Mobile verified'), 'completed' => $user->mobile_verified_at !== null, 'weight' => 12],
            ['key' => 'address', 'label' => __('Address'), 'completed' => filled($user->address), 'weight' => 10],
            ['key' => 'nationality', 'label' => __('Nationality'), 'completed' => filled($user->nationality), 'weight' => 8],
            ['key' => 'dob', 'label' => __('Date of birth'), 'completed' => $user->date_of_birth !== null, 'weight' => 8],
            ['key' => 'gov_id', 'label' => __('Government ID'), 'completed' => filled($user->government_id_type) && filled($user->government_id_number), 'weight' => 12],
            ['key' => 'mfa', 'label' => __('Multi-factor auth'), 'completed' => (bool) $user->mfa_enabled, 'weight' => 10],
            ['key' => 'signature', 'label' => __('Signature on file'), 'completed' => filled($user->signature_image_path), 'weight' => 10],
        ];
    }

    public function completionPercent(User $user): int
    {
        $fields = $this->completionFields($user);
        $totalWeight = array_sum(array_column($fields, 'weight'));
        $completedWeight = collect($fields)
            ->filter(fn (array $field): bool => $field['completed'])
            ->sum('weight');

        if ($totalWeight === 0) {
            return 0;
        }

        return (int) round(($completedWeight / $totalWeight) * 100);
    }

    public function trustScore(User $user): int
    {
        $score = 0;

        if ($user->hasVerifiedEmail()) {
            $score += 20;
        }

        if ($user->mobile_verified_at !== null) {
            $score += 20;
        }

        if ($user->mfa_enabled) {
            $score += 20;
        }

        if ($user->ekyc_status === EkycStatus::Verified || $user->kyc_verified_at !== null) {
            $score += 25;
        }

        if ($this->hasCompletedLegalProfile($user)) {
            $score += 15;
        }

        return min(100, $score);
    }

    public function trustTier(int $score): string
    {
        return match (true) {
            $score >= 90 => 'platinum',
            $score >= 70 => 'gold',
            $score >= 50 => 'silver',
            default => 'bronze',
        };
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     status: string,
     *     status_label: string,
     *     action_route: string|null,
     *     action_label: string|null,
     *     icon: string
     * }>
     */
    public function verificationItems(User $user): array
    {
        $ekycRecord = $user->relationLoaded('ekycRecord') ? $user->ekycRecord : $user->ekycRecord()->first();

        $identityStatus = match ($user->ekyc_status) {
            EkycStatus::Verified => 'verified',
            EkycStatus::Rejected => 'rejected',
            EkycStatus::Pending => 'pending',
            default => filled($ekycRecord?->document_path) || filled($user->kyc_file_path) ? 'pending' : 'not_started',
        };

        $identityDescription = match ($identityStatus) {
            'verified' => $this->formatDocumentType($user->kyc_id_type ?? $ekycRecord?->document_type ?? $user->government_id_type),
            'rejected' => $ekycRecord?->rejection_reason ?: __('Identity could not be verified'),
            'pending' => __('ID submitted — awaiting review'),
            default => __('Upload a government-issued photo ID'),
        };

        $selfieStatus = match (true) {
            $user->selfie_verified_at !== null => 'verified',
            $identityStatus === 'verified' => 'pending',
            default => 'not_started',
        };

        $mfaStatus = $user->mfa_enabled && $user->two_factor_enabled ? 'verified' : ($user->two_factor_enabled ? 'pending' : 'not_started');

        return [
            [
                'key' => 'email',
                'title' => __('Email'),
                'description' => $user->hasVerifiedEmail()
                    ? __('Verified :date', ['date' => $user->email_verified_at?->format('M j, Y') ?? '—'])
                    : $user->email,
                'status' => $user->hasVerifiedEmail() ? 'verified' : 'action_required',
                'status_label' => $user->hasVerifiedEmail() ? __('Verified') : __('Unverified'),
                'action_route' => null,
                'action_label' => null,
                'wire_action' => $user->hasVerifiedEmail() ? null : 'resendEmailVerification',
                'icon' => 'envelope',
            ],
            [
                'key' => 'mobile',
                'title' => __('Mobile'),
                'description' => $user->mobile_verified_at !== null
                    ? __(':number · verified :date', [
                        'number' => $user->mobile_number,
                        'date' => $user->mobile_verified_at->format('M j, Y'),
                    ])
                    : ($user->mobile_number ?: __('No number on file')),
                'status' => $user->mobile_verified_at !== null ? 'verified' : ($user->mobile_number ? 'action_required' : 'not_started'),
                'status_label' => $user->mobile_verified_at !== null ? __('Verified') : __('Required'),
                'action_route' => $user->mobile_verified_at === null ? 'onboarding.mobile' : null,
                'action_label' => $user->mobile_verified_at === null ? __('Verify') : null,
                'wire_action' => null,
                'icon' => 'device-phone-mobile',
            ],
            [
                'key' => 'identity',
                'title' => __('Identity (eKYC & ID)'),
                'description' => $identityDescription,
                'status' => $identityStatus,
                'status_label' => match ($identityStatus) {
                    'verified' => __('Verified'),
                    'pending' => __('In review'),
                    'rejected' => __('Rejected'),
                    default => __('Not started'),
                },
                'action_route' => $identityStatus !== 'verified' ? 'onboarding.kyc' : null,
                'action_label' => in_array($identityStatus, ['not_started', 'rejected'], true) ? __('Submit ID') : ($identityStatus === 'pending' ? __('View status') : null),
                'wire_action' => null,
                'icon' => 'identification',
            ],
            [
                'key' => 'selfie',
                'title' => __('Selfie / liveness'),
                'description' => match ($selfieStatus) {
                    'verified' => __('Confirmed :date', ['date' => $user->selfie_verified_at?->format('M j, Y') ?? '—']),
                    'pending' => __('Complete during eKYC or a notary session'),
                    default => __('Required for eNOTARY'),
                },
                'status' => $selfieStatus,
                'status_label' => match ($selfieStatus) {
                    'verified' => __('Verified'),
                    'pending' => __('Pending'),
                    default => __('Required'),
                },
                'action_route' => $selfieStatus !== 'verified' ? 'onboarding.kyc' : null,
                'action_label' => $selfieStatus !== 'verified' ? __('Continue') : null,
                'wire_action' => null,
                'icon' => 'camera',
            ],
            [
                'key' => 'mfa',
                'title' => __('Two-factor authentication'),
                'description' => $user->mfa_enabled
                    ? __('TOTP active · :date', ['date' => $user->two_factor_confirmed_at?->format('M j, Y') ?? __('enabled')])
                    : __('Protect sign-in with an authenticator app'),
                'status' => $mfaStatus,
                'status_label' => $user->mfa_enabled ? __('Enabled') : __('Off'),
                'action_route' => 'settings.security',
                'action_label' => $user->mfa_enabled ? __('Manage') : __('Enable'),
                'wire_action' => null,
                'icon' => 'shield-check',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, met: bool, description: string}>
     */
    public function enotaryReadinessChecks(User $user): array
    {
        return [
            [
                'key' => 'identity',
                'label' => __('Verified identity'),
                'met' => $user->hasVerifiedEkyc() || $user->kyc_verified_at !== null,
                'description' => __('eKYC or manual ID verification complete'),
            ],
            [
                'key' => 'mobile',
                'label' => __('Verified mobile'),
                'met' => $user->mobile_verified_at !== null,
                'description' => __('OTP-confirmed phone number'),
            ],
            [
                'key' => 'legal_profile',
                'label' => __('Completed legal profile'),
                'met' => $this->hasCompletedLegalProfile($user),
                'description' => __('Address, nationality, DOB, and government ID on file'),
            ],
            [
                'key' => 'gps',
                'label' => __('GPS permission'),
                'met' => $user->gps_permission_granted_at !== null,
                'description' => __('Location services enabled for remote notarization'),
            ],
            [
                'key' => 'selfie',
                'label' => __('Selfie verification'),
                'met' => $user->selfie_verified_at !== null,
                'description' => __('Biometric match confirmed'),
            ],
        ];
    }

    public function isEnotaryReady(User $user): bool
    {
        return collect($this->enotaryReadinessChecks($user))->every(fn (array $check): bool => $check['met']);
    }

    public function hasCompletedLegalProfile(User $user): bool
    {
        return filled($user->address)
            && filled($user->nationality)
            && $user->date_of_birth !== null
            && filled($user->government_id_type)
            && filled($user->government_id_number);
    }

    /**
     * @return Collection<int, array{title: string, description: string, occurred_at: string, category: string}>
     */
    public function activityTimeline(User $user, int $limit = 15): Collection
    {
        $onboarding = OnboardingAuditLog::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (OnboardingAuditLog $log): array => [
                'title' => $this->formatAuditAction($log->action),
                'description' => $log->ip_address ? __('IP: :ip', ['ip' => $log->ip_address]) : '',
                'occurred_at' => $log->created_at?->toIso8601String() ?? '',
                'category' => 'verification',
            ]);

        $signerIds = DocumentSigner::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $signing = SignatureAuditEvent::query()
            ->whereIn('signer_id', $signerIds)
            ->with('document:id,title')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SignatureAuditEvent $event): array => [
                'title' => $this->formatSigningAction($event->action),
                'description' => $event->document?->title ?? __('Document signing'),
                'occurred_at' => $event->created_at?->toIso8601String() ?? '',
                'category' => 'signing',
            ]);

        return $onboarding
            ->concat($signing)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, ip_address: string|null, user_agent: string|null, last_activity: string, is_current: bool}>
     */
    public function activeSessions(User $user): Collection
    {
        $currentSessionId = session()->getId();

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->limit(10)
            ->get()
            ->map(fn (object $session): array => [
                'id' => (string) $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => now()->createFromTimestamp((int) $session->last_activity)->toDayDateTimeString(),
                'is_current' => $session->id === $currentSessionId,
            ]);
    }

    public function verificationBadge(User $user): string
    {
        if ($this->isEnotaryReady($user)) {
            return 'enotary_ready';
        }

        if ($user->hasVerifiedEkyc() && $user->mfa_enabled) {
            return 'trusted';
        }

        if ($user->hasVerifiedEmail() && $user->mobile_verified_at !== null) {
            return 'verified';
        }

        return 'basic';
    }

    public function verificationBadgeLabel(string $badge): string
    {
        return match ($badge) {
            'enotary_ready' => __('eNOTARY Ready'),
            'trusted' => __('Trusted'),
            'verified' => __('Verified'),
            default => __('Basic'),
        };
    }

    private function formatAuditAction(string $action): string
    {
        return str($action)->replace(['_', '.'], ' ')->title()->toString();
    }

    private function formatSigningAction(string $action): string
    {
        return match ($action) {
            SignatureAuditEvent::ACTION_SIGNED => __('Document signed'),
            SignatureAuditEvent::ACTION_COMPLETED => __('Signing completed'),
            SignatureAuditEvent::ACTION_PLACED => __('Signature placed'),
            default => str($action)->title()->toString(),
        };
    }

    private function roleLabel(User $user): string
    {
        return match ($user->role) {
            UserRole::Client => __('Client'),
            UserRole::Notary => __('Notary'),
            UserRole::NotaryAdmin => __('Notary administrator'),
            UserRole::SuperAdmin => __('Super administrator'),
            default => __('Member'),
        };
    }

    private function formatDocumentType(?string $type): string
    {
        if (! filled($type)) {
            return __('Government ID on file');
        }

        return str($type)->replace('_', ' ')->title()->toString();
    }
}
