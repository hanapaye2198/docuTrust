<?php

namespace App\Services\Compliance;

use App\Models\CertificateAuthority;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignerCertificate;
use App\Support\SignatureFeatures;
use App\Support\TrustLevel;
use Illuminate\Support\Facades\Schema;

class SignatureComplianceService
{
    /**
     * @return array{
     *   assessed_at: string,
     *   phase: string,
     *   overall_score: int,
     *   trust_level: array<string, mixed>,
     *   categories: array<int, array<string, mixed>>,
     *   standards_supported: list<string>,
     *   standards_missing: list<string>,
     *   recommendations: list<string>
     * }
     */
    public function assess(): array
    {
        $categories = [
            $this->assessElectronicSignature(),
            $this->assessDigitalSignature(),
            $this->assessPkiInfrastructure(),
            $this->assessCertificateManagement(),
            $this->assessTimestamping(),
            $this->assessRevocation(),
            $this->assessIdentityVerification(),
            $this->assessBlockchainIntegrity(),
            $this->assessAuditLogging(),
            $this->assessPdfSignatureCompliance(),
            $this->assessNotaryCompliance(),
            $this->assessHsmIntegration(),
            $this->assessAwsKms(),
            $this->assessPkcs11(),
        ];

        $scored = array_values(array_filter(
            $categories,
            fn (array $category): bool => ($category['status'] ?? '') !== 'DISABLED'
                && isset($category['score_percentage'])
        ));

        $overallScore = $scored === []
            ? 0
            : (int) round(array_sum(array_column($scored, 'score_percentage')) / count($scored));

        $trustLevel = TrustLevel::evaluate();
        $recommendations = $this->collectRecommendations($categories);

        return [
            'assessed_at' => now()->toIso8601String(),
            'phase' => (string) config('signature.compliance.phase', 'early_production'),
            'overall_score' => $overallScore,
            'trust_level' => $trustLevel,
            'categories' => $categories,
            'standards_supported' => $this->supportedStandards(),
            'standards_missing' => $this->missingStandards(),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessElectronicSignature(): array
    {
        $hasFields = Schema::hasTable('signature_fields');
        $score = $hasFields ? 90 : 40;

        return $this->category(
            key: 'electronic_signature',
            title: __('Electronic Signature'),
            status: $hasFields ? 'READY' : 'PARTIAL',
            score: $score,
            missing: $hasFields ? [] : [__('Signature field placement tables')],
            recommendations: $hasFields
                ? [__('Maintain Fabric.js field placement UX and signer token workflows.')]
                : [__('Deploy signature field schema.')]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessDigitalSignature(): array
    {
        $hasCryptoColumns = Schema::hasTable('signatures')
            && Schema::hasColumn('signatures', 'signature_value')
            && Schema::hasColumn('signatures', 'signature_hash');

        $sealedCount = $hasCryptoColumns
            ? Signature::query()->whereNotNull('signature_value')->count()
            : 0;

        $status = $hasCryptoColumns && $sealedCount > 0 ? 'READY' : ($hasCryptoColumns ? 'PARTIAL' : 'MISSING');
        $score = match ($status) {
            'READY' => 85,
            'PARTIAL' => 55,
            default => 10,
        };

        return $this->category(
            key: 'digital_signature',
            title: __('Digital Signature'),
            status: $status,
            score: $score,
            missing: $status === 'READY'
                ? []
                : [__('Detached RSA-SHA256 seals on completed documents')],
            recommendations: [
                __('Completion sealing via SignerSealProviderManager is active; upgrade to PAdES when ready.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessPkiInfrastructure(): array
    {
        $hasCa = Schema::hasTable('certificate_authorities')
            && CertificateAuthority::query()->where('status', 'active')->exists();

        $status = $hasCa ? 'PARTIAL' : 'MISSING';
        $score = $hasCa ? 60 : 15;

        return $this->category(
            key: 'pki_infrastructure',
            title: __('PKI Infrastructure'),
            status: $status,
            score: $score,
            missing: $hasCa
                ? [__('Accredited national CA / ICA integration')]
                : [__('Certificate authority records')],
            recommendations: [
                __('Internal app-managed CA is suitable for MVP; plan ICA upgrade via digital_certificates placeholders.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessCertificateManagement(): array
    {
        $hasSignerCerts = Schema::hasTable('signer_certificates');
        $activeCerts = $hasSignerCerts
            ? SignerCertificate::query()->where('status', 'active')->count()
            : 0;

        $hasPlaceholder = Schema::hasTable('digital_certificates');

        $status = $hasSignerCerts && $activeCerts > 0 ? 'PARTIAL' : 'MISSING';
        $score = $hasSignerCerts && $activeCerts > 0 ? 70 : 25;

        $missing = [];
        if (! $hasPlaceholder) {
            $missing[] = __('digital_certificates placeholder table');
        }
        if ($activeCerts === 0) {
            $missing[] = __('Active signer certificates');
        }

        return $this->category(
            key: 'certificate_management',
            title: __('Certificate Management'),
            status: $status,
            score: $score,
            missing: $missing,
            recommendations: [
                __('Use signer_certificates for live signing; migrate to digital_certificates for long-lived user certs later.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessTimestamping(): array
    {
        $remoteTs = (bool) config('services.remote_signing.csc.timestamp_enabled', false);
        $hasPlaceholder = Schema::hasTable('timestamp_authorities');

        $status = $remoteTs ? 'PARTIAL' : 'MISSING';
        $score = $remoteTs ? 50 : 20;

        return $this->category(
            key: 'timestamping',
            title: __('Timestamping'),
            status: $status,
            score: $score,
            missing: $remoteTs
                ? [__('Owned accredited TSA')]
                : [__('RFC3161 timestamp on app_managed path'), __('timestamp_authorities registry population')],
            recommendations: $hasPlaceholder
                ? [__('Enable REMOTE_SIGNING_CSC_TIMESTAMP_ENABLED when TSP timestamp is available.')]
                : [__('Run compliance migrations for timestamp_authorities.')]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessRevocation(): array
    {
        $hasRevocationColumns = Schema::hasTable('signer_certificates')
            && Schema::hasColumn('signer_certificates', 'revoked_at');

        $ocspRoutes = SignatureFeatures::ocspRoutesEnabled();
        $hasOcspLogs = Schema::hasTable('ocsp_logs');

        $status = $hasRevocationColumns ? 'PARTIAL' : 'MISSING';
        $score = $hasRevocationColumns ? ($ocspRoutes ? 55 : 45) : 15;

        $missing = [];
        if (! $ocspRoutes) {
            $missing[] = __('OCSP responder routes (disabled in early production)');
        }
        if (! $hasOcspLogs) {
            $missing[] = __('ocsp_logs table');
        }

        return $this->category(
            key: 'revocation',
            title: __('Revocation'),
            status: $status,
            score: $score,
            missing: $missing,
            recommendations: [
                __('Enable SIGNATURE_OCSP_ROUTES_ENABLED when operating a public OCSP responder.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessIdentityVerification(): array
    {
        $hasEkyc = Schema::hasTable('ekyc_records');
        $hasTrustProfile = Schema::hasColumn('users', 'ekyc_status');
        $hasNotary = Schema::hasTable('notary_requests');

        $ready = $hasEkyc && $hasTrustProfile && $hasNotary;
        $status = $ready ? 'PARTIAL' : 'MISSING';
        $score = $ready ? 75 : 35;

        return $this->category(
            key: 'identity_verification',
            title: __('Identity Verification'),
            status: $status,
            score: $score,
            missing: $ready
                ? [__('Per-field strong binding for all email-link signers')]
                : [__('eKYC and notary identity modules')],
            recommendations: [
                __('Bind signer evidence records to eKYC status snapshots at completion.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessBlockchainIntegrity(): array
    {
        $configured = (string) config('services.blockchain.base_url', '') !== '';
        $hasHashes = Schema::hasTable('document_hashes');

        $status = $configured && $hasHashes ? 'READY' : ($hasHashes ? 'PARTIAL' : 'MISSING');
        $score = match ($status) {
            'READY' => 80,
            'PARTIAL' => 45,
            default => 10,
        };

        return $this->category(
            key: 'blockchain_integrity',
            title: __('Blockchain Integrity'),
            status: $status,
            score: $score,
            missing: $configured ? [] : [__('BLOCKCHAIN_SERVICE_URL configuration')],
            recommendations: [
                __('Blockchain anchoring supplements but does not replace qualified timestamps.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessAuditLogging(): array
    {
        $hasAudit = Schema::hasTable('signature_audit_events');
        $hasEvidence = Schema::hasTable('signature_evidence_records');
        $eventCount = $hasAudit ? SignatureAuditEvent::query()->count() : 0;

        $status = $hasAudit && $hasEvidence ? 'PARTIAL' : ($hasAudit ? 'PARTIAL' : 'MISSING');
        $score = $hasAudit && $hasEvidence ? 78 : ($hasAudit ? 65 : 20);

        return $this->category(
            key: 'audit_logging',
            title: __('Audit Logging'),
            status: $status,
            score: $score,
            missing: array_filter([
                $hasEvidence ? null : __('Unified signature_evidence_records'),
                __('Immutable/WORM log store'),
                $eventCount > 0 ? null : __('Audit events from live signing'),
            ]),
            recommendations: [
                __('Evidence records are created on document completion; extend with device metadata when available.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessPdfSignatureCompliance(): array
    {
        $pades = (bool) config('signature.features.pades.enabled', false);

        return $this->category(
            key: 'pdf_signature_compliance',
            title: __('PDF Signature Compliance'),
            status: $pades ? 'PARTIAL' : 'MISSING',
            score: $pades ? 30 : 15,
            missing: [
                __('PAdES embedded PDF signatures'),
                __('Adobe-compatible signature dictionaries'),
            ],
            recommendations: [
                __('Current final PDF uses visual FPDI stamping; enable FuturePKISignatureDriver PAdES when ready.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessNotaryCompliance(): array
    {
        $hasNotary = Schema::hasTable('notary_requests')
            && Schema::hasTable('notarial_register_entries');

        return $this->category(
            key: 'notary_compliance',
            title: __('Notary Compliance'),
            status: $hasNotary ? 'PARTIAL' : 'MISSING',
            score: $hasNotary ? 72 : 10,
            missing: $hasNotary ? [__('Jurisdiction-specific legal accreditation review')] : [__('eNOTARY module tables')],
            recommendations: [
                __('Continue notarial register, session verification checklist, and seal generation workflows.'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessHsmIntegration(): array
    {
        return $this->disabledHardwareCategory(
            key: 'hsm_integration',
            title: __('HSM Integration'),
            feature: 'hsm',
            note: __('Intentionally disabled for early production until AWS CloudHSM or equivalent is procured.'),
            enableHint: 'SIGNATURE_HSM_ENABLED=true',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessAwsKms(): array
    {
        return $this->disabledHardwareCategory(
            key: 'aws_kms',
            title: __('AWS KMS'),
            feature: 'aws_kms',
            note: __('Key management via AWS KMS is not enabled.'),
            enableHint: 'SIGNATURE_AWS_KMS_ENABLED=true',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assessPkcs11(): array
    {
        return $this->disabledHardwareCategory(
            key: 'pkcs11',
            title: __('PKCS#11'),
            feature: 'pkcs11',
            note: __('PKCS#11 hardware providers are not enabled.'),
            enableHint: 'SIGNATURE_PKCS11_ENABLED=true',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledHardwareCategory(
        string $key,
        string $title,
        string $feature,
        string $note,
        string $enableHint,
    ): array {
        $enabled = match ($feature) {
            'hsm' => SignatureFeatures::hsmEnabled(),
            'aws_kms' => SignatureFeatures::awsKmsEnabled(),
            'pkcs11' => SignatureFeatures::pkcs11Enabled(),
            default => false,
        };

        if ($enabled) {
            return $this->category(
                key: $key,
                title: $title,
                status: 'PARTIAL',
                score: 25,
                missing: [__('Production configuration and seal provider wiring')],
                recommendations: [__('Complete HSM/KMS integration and rotate off database-stored private keys.')],
            );
        }

        return [
            'key' => $key,
            'title' => $title,
            'status' => 'DISABLED',
            'score_percentage' => null,
            'missing_requirements' => [
                __('AWS CloudHSM cluster or PKCS#11 HSM'),
            ],
            'implementation_recommendations' => [
                $note,
                __('Enable with :hint when infrastructure is ready.', ['hint' => $enableHint]),
            ],
            'note' => $note,
        ];
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $recommendations
     * @return array<string, mixed>
     */
    private function category(
        string $key,
        string $title,
        string $status,
        int $score,
        array $missing,
        array $recommendations,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'status' => $status,
            'score_percentage' => max(0, min(100, $score)),
            'missing_requirements' => array_values($missing),
            'implementation_recommendations' => array_values($recommendations),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return list<string>
     */
    private function collectRecommendations(array $categories): array
    {
        $items = [];

        foreach ($categories as $category) {
            if (($category['status'] ?? '') === 'DISABLED') {
                continue;
            }

            foreach ($category['implementation_recommendations'] ?? [] as $recommendation) {
                if (is_string($recommendation) && $recommendation !== '' && ! in_array($recommendation, $items, true)) {
                    $items[] = $recommendation;
                }
            }
        }

        return array_slice($items, 0, 12);
    }

    /**
     * @return list<string>
     */
    private function supportedStandards(): array
    {
        $standards = ['SHA-256', 'RSA-SHA256', 'X.509 (app-managed)'];

        if ((string) config('docutrust.pki.signing_backend') === 'remote_managed') {
            $standards[] = 'CSC API (remote_managed)';
        }

        if ((bool) config('services.remote_signing.csc.timestamp_enabled', false)) {
            $standards[] = 'RFC3161 (remote provider)';
        }

        if ((string) config('services.blockchain.base_url', '') !== '') {
            $standards[] = 'Blockchain integrity anchor';
        }

        return $standards;
    }

    /**
     * @return list<string>
     */
    private function missingStandards(): array
    {
        $missing = ['PAdES', 'Qualified eIDAS', 'Accredited TSP'];

        if (! SignatureFeatures::hsmEnabled()) {
            $missing[] = 'FIPS 140-2 HSM default signing path';
        }

        if (! (bool) config('signature.features.pades.enabled', false)) {
            $missing[] = 'Embedded PDF signature dictionaries';
        }

        return $missing;
    }
}
