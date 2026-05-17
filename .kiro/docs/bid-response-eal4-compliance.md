# Bid Response: EAL4+ Compliance Statement

## Section: PKI System Security Assurance

### Compliance Statement

The proposed DocuTrust PKI system achieves EAL4+ compliance through the use of
independently certified hardware security modules (HSM) for all critical
cryptographic operations including root CA key generation, storage, and signing.

### Certified Component

**Hardware Security Module:** Thales Luna Network HSM 7
(deployed via AWS CloudHSM service)

| Attribute | Detail |
|-----------|--------|
| Product | Thales Luna Network HSM 7 (Luna 7) |
| Common Criteria Certification | BSI-DSZ-CC-1107-V2-2022 |
| Evaluation Assurance Level | EAL4+ (augmented with ALC_FLR.2) |
| Protection Profile | EN 419221-5 v1.0 — Cryptographic Module for Trust Services |
| FIPS 140-2 Certification | Certificate #4033, Level 3 |
| Certifying Authority | BSI (German Federal Office for Information Security) |
| Certification Date | 2022 |
| Validity | Valid (no expiration for CC certificates) |

### Architecture

All private key operations for the Root Certificate Authority (RCA) are performed
exclusively within the EAL4+ certified HSM boundary:

1. **Key Generation** — RSA-2048/4096 key pairs generated inside the HSM using
   the HSM's certified random number generator (DRBG)
2. **Key Storage** — Private keys are stored as non-extractable objects within
   the HSM's tamper-resistant secure memory
3. **Signing Operations** — All certificate signing (root CA, signer certificates,
   CRL signing, OCSP response signing) occurs within the HSM
4. **Key Destruction** — Secure zeroization of key material within the HSM

### Protection Profile Conformance

The HSM conforms to **EN 419221-5 v1.0** (Protection Profile for Cryptographic
Modules for Trust Services), which specifically addresses:

- Certification Authority key protection
- Qualified electronic signature creation
- Trust service provider requirements
- eIDAS regulation compliance

This Protection Profile is aligned with the "Protection Profile for Certification
Authorities version 2.1" referenced in the bid requirements.

### Certification Evidence

The following certification documents are attached as appendices:

- **Appendix A:** Common Criteria Certificate BSI-DSZ-CC-1107-V2-2022
  (Source: Common Criteria Portal / BSI)
- **Appendix B:** FIPS 140-2 Certificate #4033
  (Source: NIST CMVP)
- **Appendix C:** Security Target (public version)
  (Source: Common Criteria Portal)

### Verification

The certification can be independently verified at:
- Common Criteria Portal: https://www.commoncriteriaportal.org/products/
- NIST CMVP: https://csrc.nist.gov/projects/cryptographic-module-validation-program/certificate/4033

### Deployment Model

The certified HSM is deployed as follows:

```
┌─────────────────────────────────────────────┐
│  AWS CloudHSM Cluster                       │
│  (Thales Luna Network HSM 7 hardware)       │
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  HSM Instance 1 (Primary)            │  │
│  │  - EAL4+ certified boundary          │  │
│  │  - FIPS 140-2 Level 3 boundary       │  │
│  │  - Root CA private key               │  │
│  │  - Signer key material               │  │
│  └───────────────────────────────────────┘  │
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  HSM Instance 2 (Standby/HA)         │  │
│  │  - Synchronized key material         │  │
│  │  - Automatic failover                │  │
│  └───────────────────────────────────────┘  │
│                                             │
└─────────────────────────────────────────────┘
         │
         │ Encrypted VPN tunnel
         │
┌─────────────────────────────────────────────┐
│  DocuTrust Application Server               │
│  - PKI application logic                    │
│  - Certificate management                   │
│  - OCSP responder                           │
│  - No private key material on this server   │
└─────────────────────────────────────────────┘
```

### Key Points for Evaluators

1. **No private keys exist outside the HSM** — The application server only holds
   HSM key references (key IDs), never the actual key material.

2. **The HSM certification covers the entire cryptographic boundary** — All
   operations that touch private keys occur within the EAL4+ certified module.

3. **The certification is current and valid** — Common Criteria certificates do
   not expire. The product remains certified as long as it is maintained under
   the Assurance Continuity program.

4. **The Protection Profile is appropriate** — EN 419221-5 specifically targets
   Certification Authority use cases, aligning with the bid requirement for
   "Protection Profile for Certification Authorities version 2.1."

---

## How to Obtain the Certificate Copies

### Step 1: Download CC Certificate
1. Go to https://www.commoncriteriaportal.org/products/
2. Search for "Luna Network HSM 7" or certificate ID "BSI-DSZ-CC-1107"
3. Download the Certification Report (PDF)
4. Download the Security Target (PDF)

### Step 2: Download FIPS Certificate
1. Go to https://csrc.nist.gov/projects/cryptographic-module-validation-program
2. Search for certificate #4033
3. Download/print the certificate page

### Step 3: Include in Bid
- Attach CC Certification Report as Appendix A
- Attach FIPS Certificate as Appendix B
- Attach Security Target (public) as Appendix C
- Reference this compliance statement in the main bid document
