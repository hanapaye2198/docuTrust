<?php

namespace Tests\Unit;

use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureEvidenceRecord;
use App\Models\SignerCertificate;
use PHPUnit\Framework\TestCase;

class PadesCscMetadataTest extends TestCase
{
    public function test_pades_signature_metadata_is_fillable_and_cast(): void
    {
        $signature = new Signature;

        foreach ([
            'pades_profile',
            'cms_signature',
            'byte_range',
            'digest_algorithm',
            'signing_time',
            'tsa_timestamp',
            'tsa_url',
            'ltv_applied',
            'ltv_dss_path',
            'csc_credential_id',
            'csc_transaction_id',
            'validation_status',
            'validated_at',
        ] as $column) {
            $this->assertContains($column, $signature->getFillable());
        }

        $this->assertSame('array', $signature->getCasts()['byte_range']);
        $this->assertSame('boolean', $signature->getCasts()['ltv_applied']);
        $this->assertSame('datetime', $signature->getCasts()['signing_time']);
        $this->assertSame('datetime', $signature->getCasts()['validated_at']);
    }

    public function test_csc_certificate_metadata_is_fillable_and_cast(): void
    {
        $certificate = new SignerCertificate;

        foreach ([
            'csc_credential_id',
            'certificate_chain',
            'valid_until',
            'key_algorithm',
            'key_size',
            'ocsp_url',
            'crl_url',
            'ocsp_staple',
            'ocsp_checked_at',
            'revocation_status',
        ] as $column) {
            $this->assertContains($column, $certificate->getFillable());
        }

        $this->assertSame('array', $certificate->getCasts()['certificate_chain']);
        $this->assertSame('datetime', $certificate->getCasts()['valid_from']);
        $this->assertSame('datetime', $certificate->getCasts()['valid_until']);
        $this->assertSame('datetime', $certificate->getCasts()['ocsp_checked_at']);
    }

    public function test_document_signer_csc_metadata_is_fillable_and_cast(): void
    {
        $signer = new DocumentSigner;

        foreach ([
            'csc_access_token',
            'csc_token_expires_at',
            'csc_signing_completed',
            'csc_signing_completed_at',
        ] as $column) {
            $this->assertContains($column, $signer->getFillable());
        }

        $this->assertSame('encrypted', $signer->getCasts()['csc_access_token']);
        $this->assertSame('datetime', $signer->getCasts()['csc_token_expires_at']);
        $this->assertSame('boolean', $signer->getCasts()['csc_signing_completed']);
        $this->assertSame('datetime', $signer->getCasts()['csc_signing_completed_at']);
    }

    public function test_signature_evidence_pades_metadata_is_fillable_and_cast(): void
    {
        $evidenceRecord = new SignatureEvidenceRecord;

        foreach ([
            'pades_profile',
            'cms_signature_hash',
            'tsr_hash',
            'ltv_applied',
            'csc_provider',
            'csc_transaction_id',
            'validation_snapshot',
        ] as $column) {
            $this->assertContains($column, $evidenceRecord->getFillable());
        }

        $this->assertSame('boolean', $evidenceRecord->getCasts()['ltv_applied']);
        $this->assertSame('array', $evidenceRecord->getCasts()['validation_snapshot']);
    }
}
