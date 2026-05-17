# CSC Compliance Implementation Plan

## Overview

This document outlines the implementation plan to achieve CSC (Certification Service Provider) compliance for the docuTrust e-signature system.

## CSC Requirements Summary

| Requirement | Status | Priority |
|-------------|--------|----------|
| EAL4+ Certified HSM | ⚠️ Planned | Critical |
| FIPS 140-2 Level 3 | ⚠️ Planned | Critical |
| Dedicated VGW | ⚠️ Planned | High |
| ECC-protected RAM | ⚠️ Planned | Medium |
| Redundant SSD/Power | ⚠️ Planned | Medium |
| X.509/CRL Support | ⚠️ Partial | High |
| PKIX-CMP | ⚠️ Planned | High |
| PKCS#7/10 | ⚠️ Planned | High |
| SCEP | ⚠️ Planned | Medium |
| PKI-aware App Interop | ⚠️ Limited | Medium |

## Implementation Phases

### Phase 1: HSM Integration & Core Crypto (Weeks 1-4)

**Goal:** Replace software-based crypto with FIPS 140-2 Level 3 HSM

#### Completed Files:
- `app/Contracts/HsmService.php` - HSM interface contract
- `app/Services/HsmKeyManager.php` - HSM-backed key management
- `app/Services/HsmPkiSignatureService.php` - HSM-backed signature service
- `app/Services/HsmCertificateAuthorityService.php` - HSM-backed CA service
- `app/Services/HsmSignerSealProvider.php` - HSM-backed signer sealing
- `app/Services/HsmHealthMonitor.php` - HSM health monitoring
- `app/Services/HsmAuditLogger.php` - HSM audit logging
- `app/Services/MockHsmService.php` - Mock HSM for development
- `app/Services/ThalesHsmService.php` - Thales Luna integration
- `app/Services/AwsCloudHsmService.php` - AWS CloudHSM integration
- `app/Services/UtimacoHsmService.php` - Utimaco integration
- `app/Services/HsmVirtualGateway.php` - Virtual gateway service
- `app/Http/Middleware/VirtualGateway.php` - VGW middleware
- `app/Http/Controllers/Api/HsmController.php` - HSM API controller
- `config/hsm.php` - HSM configuration
- `routes/hsm.php` - HSM API routes
- `database/migrations/2026_05_14_000001_add_hsm_key_id_to_document_signers.php`
- `database/migrations/2026_05_14_000002_create_hsm_key_audit_log_table.php`

#### Next Steps:
1. Deploy HSM hardware or cloud HSM service
2. Configure HSM connection parameters
3. Migrate existing keys to HSM
4. Update `config/docutrust.php` to use HSM backend
5. Run migration to add HSM key ID column

### Phase 2: Certificate Management Protocols (Weeks 5-8)

**Goal:** Implement PKIX-CMP, PKCS#7/10, SCEP

#### Completed Files:
- `app/Services/Pkcs10Request.php` - PKCS#10 CSR builder
- `app/Services/Pkcs7SignedData.php` - PKCS#7 signed data builder
- `app/Services/ScepService.php` - SCEP protocol implementation
- `app/Services/CmpService.php` - PKIX-CMP implementation
- `app/Services/CrlGenerator.php` - CRL generator

#### Next Steps:
1. Implement SCEP endpoints
2. Implement CMP endpoints
3. Implement CRL distribution
4. Test protocol interoperability

### Phase 3: Infrastructure (Weeks 9-10)

**Goal:** Deploy VGW and network segmentation

#### Completed Files:
- `app/Services/HsmVirtualGateway.php` - VGW service
- `app/Http/Middleware/VirtualGateway.php` - VGW middleware
- `app/Http/Controllers/Api/HsmController.php` - HSM API

#### Next Steps:
1. Deploy dedicated VGW instance
2. Configure network segmentation
3. Set up redundant infrastructure

### Phase 4: Documentation & Testing (Weeks 11-12)

**Goal:** Prepare for Common Criteria evaluation

#### Next Steps:
1. Create Security Target (ST) documentation
2. Create Certificate Policy (CP) documentation
3. Create crypto module boundary documentation
4. Write compliance test suite
5. Engage Common Criteria evaluation lab

## Configuration

### HSM Backend Configuration

Update `config/hsm.php`:

```php
'backend' => env('HSM_BACKEND', 'thales'), // or 'aws-cloudhsm', 'utimaco'

'thales' => [
    'partition_label' => env('THALES_PARTITION_LABEL', 'default'),
    'partition_password' => env('THALES_PARTITION_PASSWORD', ''),
],

'aws' => [
    'cluster_id' => env('AWS_CLOUDHSM_CLUSTER_ID', ''),
    'region' => env('AWS_CLOUDHSM_REGION', 'us-east-1'),
],

'utimaco' => [
    'slot_id' => env('UTIMACO_SLOT_ID', 0),
    'user_pin' => env('UTIMACO_USER_PIN', ''),
],
```

### PKI Configuration

Update `config/docutrust.php`:

```php
'pki' => [
    'key_size' => 2048, // Minimum 2048 bits for CSC
    'root_ca_valid_days' => 3650,
    'signer_valid_days' => 825,
],
```

## Testing

### HSM Integration Tests

```bash
php artisan test --filter=Hsm
```

### Protocol Tests

```bash
php artisan test --filter=Scep|Cmp|Pkcs
```

### Compliance Tests

```bash
php artisan test --filter=Csc
```

## Next Steps

1. **Deploy HSM** - Choose and deploy HSM backend
2. **Configure HSM** - Set up connection parameters
3. **Run Migration** - Execute database migrations
4. **Test Integration** - Verify HSM operations
5. **Implement Endpoints** - Create API endpoints for protocols
6. **Documentation** - Create required documentation
7. **Evaluation** - Engage Common Criteria lab

## References

- CSC Standards Document
- FIPS 140-2/3 Requirements
- Common Criteria EAL4+ Guidelines
- RFC 4210 (PKIX-CMP)
- RFC 8894 (SCEP)
- PKCS#7, PKCS#10 Standards