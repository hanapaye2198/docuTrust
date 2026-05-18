# eNOTARY Module — Architecture & Business Rules

## Module Separation

The platform has **two separate modules** that must remain independent:

1. **eNOTARY Module** — Remote online notarization (RON) for documents requiring notarial acts (acknowledgment, jurat, affidavit, oath, certification). This is the full notarization workflow with identity verification, video session, and notarial register.

2. **Documents Module (Normal/Legal Documents)** — Standard e-signature workflow for legal documents that do NOT require notarization. This module handles document upload, signer assignment, field placement, and digital signing without the notary verification steps.

These are **separate features**. Do not mix their workflows, routes, or UI. A document can be linked to a notary request, but the signing workflow differs.

---

## eNOTARY Workflow (Attorney-Driven)

### Flow Overview

| Step | Actor | Action |
|------|-------|--------|
| 1 | Client | Creates notary request (case info, notarial act type, assigns attorney) |
| 2 | Attorney | Uploads documents, assigns signers, prepares signature fields |
| 3 | Attorney | Sends document to signers for signing (via email-link flow) |
| 4 | Signers | Sign the document (standard signing flow) |
| 5 | Attorney | Schedules video conference (only available after ALL signers have signed) |
| 6 | Attorney | Conducts video session, verifies signer identity on camera |
| 7 | Attorney | Signs their part of the document (after identity verification) |
| 8 | System | Digitalization: seal, QR, certificates, blockchain anchoring |
| 9 | NotaryAdmin | Finalizes notarization |

---

## eNOTARY Signing Rules

### Who uploads documents?
- **The ATTORNEY uploads the documents** for notarization from the notary request show page.
- The client creates the request (case info only) — they do NOT upload documents.
- The attorney can also upload documents during request creation if they initiate the case themselves.

### Who assigns signers?
- **The ATTORNEY assigns signers** — either during request creation or from the show page.
- The client who requested notarization is typically one of the signers.
- NotarySigner records are synced to DocumentSigner records when the attorney prepares fields.

### Who prepares signature fields?
- **Only the attorney (notary role)** can prepare/place signature fields on eNOTARY documents.
- The client cannot place fields on notary documents.
- This happens after the attorney uploads the document and before sending to signers.

### Who sends the document for signing?
- **Only the attorney** can send eNOTARY documents to signers.
- The standard `SendDocumentForSignatureService` email-link flow IS used for eNOTARY documents.
- Signers receive signing links via email, same as normal documents.

### Who can sign?
- **Any signer** (parties to the document) can sign the signature fields placed by the attorney.
- Signers are the parties listed in the notary request (NotarySigner records).
- The signing captures their signature on the assigned fields via the standard signing flow.

### When does the video conference happen?
- **After ALL signers have completed signing** their parts.
- The attorney can only schedule a video session once all DocumentSigner records show signed/approved status.
- During the video session, the attorney verifies the real identity of the signers on camera.

### Attorney signature
- The attorney signs **after** the video conference and identity verification.
- The attorney must **sign in written form** (drawn/handwritten signature capture).
- The attorney is added as a DocumentSigner with `AccountVerified` signing method.
- The "Sign as Attorney" action is only available after a video session is completed.
- The system also captures the attorney's digital fingerprint (credential identity) during digitalization.

### Digitalization
- Happens **after** the attorney has signed all documents.
- The `digitalizeRequest()` action verifies the attorney has signed before proceeding.
- Applies: notary seal, QR code, certificates, blockchain anchoring, attorney credential fingerprint.

### Account restriction for notarial signing
- **Only the attorney account** (the user assigned as `notary_user_id` on the NotaryRequest) can:
  - Upload documents to the request
  - Prepare signature fields
  - Send documents to signers
  - Sign as attorney
  - Trigger digital notarization (seal, QR, cert)
- This is enforced by role checks and `notary_user_id` comparison.

---

## Summary of Responsibilities

| Action | Who |
|--------|-----|
| Create notary request (case info) | Client or Attorney |
| Upload documents | Attorney (notary role) only |
| Assign signers | Attorney (notary role) only |
| Prepare/place signature fields | Attorney (notary role) only |
| Send document to signers | Attorney (notary role) only |
| Sign signature fields | Signers (parties) via email-link |
| Schedule video conference | Attorney (after all signers signed) |
| Verify identity on video | Attorney |
| Sign as attorney (written signature) | Attorney (after video session completed) |
| Trigger digital notarization (seal, QR, cert) | Attorney (after signing) |
| Finalize notarization | NotaryAdmin (after attorney approval) |

---

## Implementation Notes

- `create.blade.php`: Client only provides case info (title, type, remarks, assign notary). Document upload and signers sections are shown only for attorney role.
- `DocumentPrepareController::show()`: Only the assigned attorney can access the prepare page for eNOTARY documents (403 for others).
- `SendDocumentForSignatureService::send()`: Works normally for eNOTARY documents — no guard blocking them.
- `show.blade.php` (Livewire): Attorney can send documents, sign as attorney, and trigger digitalization. Video scheduling requires all signers to have signed.
- `NotaryDigitalizationService::digitalize()`: Records attorney signature and credential fingerprint in journal.
- The attorney's fingerprint is recorded as part of the `NotaryDigitalizationService::digitalize()` process, linked to the attorney's `NotaryCredential`.
