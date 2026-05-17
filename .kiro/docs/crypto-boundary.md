# Cryptographic Module Boundary - docuTrust e-Signature System

## 1. Overview

### 1.1 Purpose

This document defines the cryptographic module boundary for the docuTrust e-signature system as required for FIPS 140-2/3 validation and Common Criteria EAL4+ evaluation.

### 1.2 Scope

This document covers the cryptographic module that implements:
- Digital signature creation and verification
- Certificate generation and validation
- Key generation and management
- Cryptographic hashing

## 2. Cryptographic Module Boundary

### 2.1 Physical Boundary

```
+--------------------------------------------------+
|  docuTrust PKI Cryptographic Module              |
|                                                  |
|  +--------------------------------------------+  |
|  |  HSM (Hardware Security Module)            |  |
|  |  - Key Generation                          |  |
|  |  - Key Storage                             |  |
|  |  - Signing Operations                      |  |
|  |  - Verification Operations                 |  |
|  +--------------------------------------------+  |
|                                                  |
|  +--------------------------------------------+  |
|  |  Software Cryptographic Services           |  |
|  |  - PkiSignatureService                     |  |
|  |  - CertificateAuthorityService             |  |
|  |  - HsmKeyManager                           |  |
|  |  - HsmPkiSignatureService                  |  |
|  |  - HsmCertificateAuthorityService          |  |
|  +--------------------------------------------+  |
|                                                  |
|  +--------------------------------------------+  |
|  |  HSM Service Interface                     |  |
|  |  - HsmService (interface)                  |  |
|  |  - ThalesHsmService                        |  |
|  |  - AwsCloudHsmService                      |  |
|  |  - UtimacoHsmService                       |  |
|  |  - MockHsmService                          |  |
|  +--------------------------------------------+  |
|                                                  |
+--------------------------------------------------+
```

### 2.2 Logical Boundary

```
┌─────────────────────────────────────────────────┐
│  Cryptographic Module Boundary                  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Input:                                         │
│  - Hash values (SHA-256)                        │
│  - Private key references (HSM)                 │
│  - Public key references (HSM)                  │
│  - Certificate requests                         │
│                                                 │
│  Processing:                                    │
│  - RSA signing (SHA-256)                        │
│  - RSA verification (SHA-256)                   │
│  - Key generation (RSA-2048/4096)               │
│  - Certificate generation                       │
│  - Certificate validation                       │
│  - Hash computation (SHA-256)                   │
│                                                 │
│  Output:                                        │
│  - Digital signatures                           │
│  - Certificate data                             │
│  - Verification results                         │
│  - Key fingerprints                             │
│                                                 │
└─────────────────────────────────────────────────┘
```

## 3. Cryptographic Services

### 3.1 Signature Services

| Service | Function | Algorithm | Key Size |
|---------|----------|-----------|----------|
| PkiSignatureService | Sign/Verify | RSA-SHA256 | 2048/4096 |
| HsmPkiSignatureService | Sign/Verify (HSM) | RSA-SHA256 | 2048/4096 |

### 3.2 Certificate Services

| Service | Function | Algorithm | Key Size |
|---------|----------|-----------|----------|
| CertificateAuthorityService | CA Operations | RSA-SHA256 | 2048/4096 |
| HsmCertificateAuthorityService | CA Operations (HSM) | RSA-SHA256 | 2048/4096 |

### 3.3 Key Management Services

| Service | Function | Algorithm | Key Size |
|---------|----------|-----------|----------|
| HsmKeyManager | Key Management | RSA | 2048/4096 |

## 4. Cryptographic Algorithms

### 4.1 Approved Algorithms

| Algorithm | Type | Mode | Key Size | Status |
|-----------|------|------|----------|--------|
| RSA | Asymmetric | PKCS#1 v1.5 | 2048/4096 | Approved |
| SHA-256 | Hash | N/A | N/A | Approved |
| SHA-384 | Hash | N/A | N/A | Approved |
| SHA-512 | Hash | N/A | N/A | Approved |

### 4.2 Key Generation

- **Algorithm:** RSA
- **Key Size:** 2048 bits (minimum), 4096 bits (recommended)
- **Random Source:** HSM cryptographically secure RNG
- **Validation:** Key pair validation after generation

### 4.3 Hash Functions

- **SHA-256:** Primary hash algorithm
- **SHA-384:** Optional for enhanced security
- **SHA-512:** Optional for enhanced security

## 5. Security Parameters

### 5.1 Key Lengths

| Key Type | Minimum | Recommended | Maximum |
|----------|---------|-------------|---------|
| RSA | 2048 bits | 4096 bits | 4096 bits |
| SHA | 256 bits | 256 bits | 512 bits |

### 5.2 Certificate Validity

| Certificate Type | Validity Period |
|------------------|-----------------|
| Root CA | 10 years (3650 days) |
| Signer Certificate | 825 days |
| CRL | 7 days |

### 5.3 Session Parameters

- **Session Timeout:** 20 minutes
- **Idle Timeout:** 10 minutes
- **Maximum Sessions:** 100 per user

## 6. Physical Security

### 6.1 HSM Security

- **Tamper Resistance:** FIPS 140-2 Level 3
- **Tamper Evidence:** Yes
- **Zeroization:** Automatic on tamper detection
- **Physical Access:** Restricted to authorized personnel

### 6.2 Server Security

- **Access Control:** Role-based access control
- **Network Isolation:** Virtual network segmentation
- **Monitoring:** 24/7 monitoring
- **Backup:** Encrypted backups

## 7. Operational Environment

### 7.1 Operating System

- **OS:** Linux (Ubuntu/CentOS) or Windows Server
- **Version:** Current LTS release
- **Hardening:** CIS benchmarks applied

### 7.2 Runtime Environment

- **PHP Version:** 8.2+
- **OpenSSL Version:** 3.0+
- **Database:** MySQL 8.0+ / PostgreSQL 14+

### 7.3 Network Environment

- **Network Segmentation:** Yes
- **Firewall:** Yes
- **VPN:** Required for remote access
- **Encryption:** TLS 1.3

## 8. Key Management

### 8.1 Key Generation

1. HSM generates key pair
2. Public key exported
3. Private key stored in HSM
4. Key metadata stored in database

### 8.2 Key Storage

- **Primary:** HSM secure storage
- **Backup:** Encrypted backup storage
- **Access:** Role-based access control

### 8.3 Key Distribution

- **Public Keys:** Distributed via certificates
- **Private Keys:** Never distributed (HSM-only)

### 8.4 Key Destruction

1. Key marked for destruction
2. Key removed from HSM
3. Key metadata purged from database
4. Destruction logged

## 9. Audit and Logging

### 9.1 Audit Events

| Event | Log Level | Retention |
|-------|-----------|-----------|
| Key generation | Info | 7 years |
| Key signing | Info | 7 years |
| Key verification | Info | 7 years |
| Key destruction | Warning | 7 years |
| Access attempts | Warning | 1 year |
| Errors | Error | 6 months |

### 9.2 Log Protection

- **Integrity:** Signed logs
- **Access Control:** Role-based access
- **Backup:** Regular encrypted backups

## 10. Vulnerability Management

### 10.1 Vulnerability Assessment

- **Frequency:** Quarterly
- **Method:** Automated scanning + manual review
- **Scope:** All cryptographic components

### 10.2 Patch Management

- **Critical Patches:** Within 7 days
- **High Patches:** Within 30 days
- **Medium Patches:** Within 90 days

## 11. Compliance

### 11.1 Standards Compliance

| Standard | Version | Status |
|----------|---------|--------|
| FIPS 140-2/3 | Level 3 | In Progress |
| Common Criteria | EAL4+ | In Progress |
| CSC Standards | v2.1 | In Progress |
| ISO/IEC 27001 | 2022 | In Progress |

### 11.2 Validation Requirements

- **HSM:** FIPS 140-2 Level 3 certified
- **Software:** FIPS-enabled OpenSSL
- **Algorithms:** Approved algorithm list
- **Randomness:** NIST SP 800-90B compliant

## 12. References

- FIPS 140-2/3
- Common Criteria EAL4+
- CSC Standards
- NIST SP 800-90B
- RFC 5280 (PKIX)
- ISO/IEC 27001:2022