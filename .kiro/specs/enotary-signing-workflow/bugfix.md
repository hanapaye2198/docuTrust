# Bugfix Requirements Document

## Introduction

The eNOTARY module's signing workflow is incorrectly mixing the standard Documents module email-link signing flow with the notarization ceremony. This causes multiple issues: clients are redirected to place signature fields (only the attorney should), documents can be "sent for signature" via the standard `SendDocumentForSignatureService` (eNOTARY signing must happen during Step 7 — Digital Notarization), the attorney's written signature is not captured during digitalization, and any user with document access can prepare fields on eNOTARY documents. These defects violate the architectural separation between the eNOTARY module and the normal Documents module.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a client creates a notary request with a document upload THEN the system redirects the client to `documents.prepare` to place signature fields on the eNOTARY document

1.2 WHEN a user accesses the `documents.prepare` route for a document linked to a notary request (`notary_request_id` is set) THEN the system allows any user with document update permission to place signature fields, regardless of whether they are the assigned attorney

1.3 WHEN a document is linked to a notary request THEN the system allows the document to be sent for signature via `SendDocumentForSignatureService` (email-link flow), bypassing the notarization ceremony

1.4 WHEN the `NotaryDigitalizationService::digitalize()` method executes Step 7 (Digital Notarization) THEN the system does not capture the attorney's written/drawn signature as part of the notarization process

1.5 WHEN the notary request show page displays linked documents THEN the system shows a "Send" action that triggers the standard email-link signing flow for eNOTARY documents

### Expected Behavior (Correct)

2.1 WHEN a client creates a notary request with a document upload THEN the system SHALL redirect the client to the notary request show page (not `documents.prepare`), with a message indicating the request was created and the attorney will prepare signature fields

2.2 WHEN a user accesses the `documents.prepare` route for a document linked to a notary request THEN the system SHALL only allow the assigned attorney (the user whose ID matches `notary_request_id.notary_user_id`) to access and place signature fields; all other users SHALL receive a 403 Forbidden response

2.3 WHEN a document is linked to a notary request (`notary_request_id` is not null) THEN the system SHALL prevent the document from being sent via `SendDocumentForSignatureService`, returning an error that eNOTARY documents must be signed during the notarization ceremony

2.4 WHEN the `NotaryDigitalizationService::digitalize()` method executes THEN the system SHALL capture the attorney's written/drawn signature (same capture method used for signers) and record the attorney's credential identity (digital fingerprint) as part of the notarization artifacts

2.5 WHEN the notary request show page displays linked documents THEN the system SHALL NOT show the standard "Send for signature" action for documents linked to a notary request; signing is handled exclusively through the Step 7 Digital Notarization ceremony

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a document is NOT linked to a notary request (`notary_request_id` is null) THEN the system SHALL CONTINUE TO allow any authorized user to access `documents.prepare` and place signature fields normally

3.2 WHEN a document is NOT linked to a notary request THEN the system SHALL CONTINUE TO allow the document to be sent for signature via `SendDocumentForSignatureService` using the standard email-link flow

3.3 WHEN a client creates a notary request THEN the system SHALL CONTINUE TO allow the client to upload documents during request creation (Step 1)

3.4 WHEN the attorney (notary role) creates a notary request THEN the system SHALL CONTINUE TO redirect to the notary request show page (existing behavior for notary users)

3.5 WHEN `NotaryDigitalizationService::digitalize()` executes for a notary request THEN the system SHALL CONTINUE TO apply the notary seal, generate QR codes, generate certificates, anchor to blockchain, and timestamp documents as it currently does

3.6 WHEN a document in the normal Documents module (no `notary_request_id`) is prepared and sent THEN the system SHALL CONTINUE TO use the existing `DocumentPrepareController` flow without any restrictions from the eNOTARY access control logic
