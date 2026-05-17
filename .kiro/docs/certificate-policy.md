# Certificate Policy (CP) - docuTrust e-Signature System

## 1. Introduction

### 1.1 Document Information

- **Title:** Certificate Policy for docuTrust e-Signature System
- **Version:** 1.0
- **Date:** May 2026
- **Author:** DocuTrust Security Team
- **Classification:** Internal

### 1.2 Overview

This Certificate Policy defines the rules and procedures for the issuance, management, and revocation of digital certificates in the docuTrust PKI infrastructure.

## 2. Definitions

| Term | Definition |
|------|------------|
| CA | Certificate Authority |
| HSM | Hardware Security Module |
| PKI | Public Key Infrastructure |
| X.509 | ITU-T standard for public key certificates |
| CRL | Certificate Revocation List |
| OCSP | Online Certificate Status Protocol |
| CSR | Certificate Signing Request |
| DN | Distinguished Name |
| SAN | Subject Alternative Name |

## 3. Policy Administration

### 3.1 Policy Administrator

- **Name:** DocuTrust Security Team
- **Contact:** security@docutrust.com
- **Responsibilities:**
  - Policy maintenance
  - Policy interpretation
  - Policy compliance monitoring

### 3.2 Certificate Manager

- **Name:** DocuTrust Operations Team
- **Contact:** operations@docutrust.com
- **Responsibilities:**
  - Certificate issuance
  - Certificate revocation
  - Certificate management

## 4. HSM Requirements

### 4.1 Hardware Security Module

- **Type:** FIPS 140-2 Level 3 certified
- **Vendor:** Thales Luna / AWS CloudHSM / Utimaco
- **Features:**
  - Non-extractable private keys
  - Hardware-based key generation
  - Tamper-resistant storage
  - Secure cryptographic operations

### 4.2 Key Management

- **Key Generation:** HSM-based, never exposed outside HSM
- **Key Storage:** Secure HSM storage
- **Key Rotation:** Annual rotation for signing keys
- **Key Destruction:** Secure destruction when no longer needed

## 5. Certificate Lifecycle

### 5.1 Certificate Issuance

#### 5.1.1 Certificate Request

1. Applicant generates key pair (in HSM)
2. Applicant creates CSR with DN
3. Applicant submits CSR to CA

#### 5.1.2 Certificate Validation

1. CA validates applicant identity
2. CA validates request authenticity
3. CA validates key ownership

#### 5.1.3 Certificate Issuance

1. CA generates certificate
2. CA signs certificate with CA key
3. CA stores certificate in database
4. CA issues certificate to applicant

### 5.2 Certificate Usage

#### 5.2.1 Certificate Purposes

- Digital signatures
- Document signing
- Authentication

#### 5.2.2 Certificate Limitations

- Not for encryption
- Not for code signing
- Not for email protection

### 5.3 Certificate Renewal

1. Applicant requests renewal
2. CA validates applicant identity
3. CA issues new certificate
4. Old certificate revoked

### 5.4 Certificate Revocation

#### 5.4.1 Revocation Reasons

1. Key compromise
2. Certificate misuse
3. CA error
4. Organization dissolution

#### 5.4.2 Revocation Process

1. CA receives revocation request
2. CA validates request
3. CA revokes certificate
4. CA updates CRL
5. CA publishes CRL

## 6. Certificate Formats

### 6.1 X.509 Certificate

- **Version:** 3
- **Serial Number:** 20 bytes, unique per CA
- **Signature Algorithm:** SHA-256 with RSA
- **Validity Period:** 
  - Root CA: 10 years
  - Signer Certificate: 825 days
- **Subject:** DN with CN, O, OU, C
- **Issuer:** DN of CA
- **Public Key:** RSA-2048
- **Extensions:**
  - Basic Constraints
  - Key Usage
  - Extended Key Usage
  - Subject Key Identifier
  - Authority Key Identifier

### 6.2 CRL Format

- **Version:** 2
- **Signature Algorithm:** SHA-256 with RSA
- **Issuer:** DN of CA
- **Last Update:** Current time
- **Next Update:** 7 days from last update
- **Revoked Certificates:** List of revoked certificates

## 7. Key Management

### 7.1 Key Generation

- **Algorithm:** RSA
- **Key Size:** 2048 bits minimum
- **Randomness:** Cryptographically secure RNG
- **Location:** HSM

### 7.2 Key Storage

- **Primary Storage:** HSM
- **Backup Storage:** Encrypted backup
- **Access Control:** Role-based access

### 7.3 Key Rotation

- **Root CA Key:** Every 10 years
- **Signer Key:** Every 825 days
- **Key Archive:** 7 years after expiration

### 7.4 Key Destruction

- **Method:** Secure deletion
- **Verification:** Key removal verification
- **Documentation:** Destruction log

## 8. Audit and Logging

### 8.1 Audit Events

- Certificate issuance
- Certificate revocation
- Key generation
- Key usage
- Configuration changes
- Access attempts

### 8.2 Log Retention

- **Audit Logs:** 7 years
- **Access Logs:** 1 year
- **Error Logs:** 6 months

### 8.3 Log Protection

- **Integrity:** Signed logs
- **Access Control:** Role-based access
- **Backup:** Regular backups

## 9. Compliance

### 9.1 Standards Compliance

- **X.509:** ITU-T X.509 v3
- **PKIX:** RFC 5280
- **FIPS:** FIPS 140-2 Level 3
- **CSC:** Certification Service Provider Standards

### 9.2 Certification

- **EAL4+:** Common Criteria EAL4+
- **FIPS:** FIPS 140-2 Level 3
- **ISO:** ISO/IEC 27001

## 10. Changes to Policy

### 10.1 Policy Updates

- **Review:** Annual review
- **Approval:** Security team approval
- **Notification:** Stakeholder notification

### 10.2 Transition

- **Grace Period:** 30 days
- **Migration:** Backward compatibility
- **Documentation:** Change documentation

## 11. Contact Information

- **Security Team:** security@docutrust.com
- **Operations Team:** operations@docutrust.com
- **Support Team:** support@docutrust.com

## 12. References

- ITU-T X.509 v3
- RFC 5280 (PKIX)
- FIPS 140-2/3
- Common Criteria EAL4+
- CSC Standards
- ISO/IEC 27001