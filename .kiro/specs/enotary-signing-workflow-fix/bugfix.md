# Bugfix Requirements Document

## Introduction

The eNOTARY module's signing workflow has four interrelated defects that violate the business rules for remote online notarization. These bugs allow unauthorized field preparation by clients, use the wrong signing mechanism (email-link instead of in-session notarization), fail to capture the attorney's digital fingerprint during notarization, and lack proper account restrictions on the "Apply digital seal" action. Together, these undermine the legal integrity of the notarization process and break the separation between the eNOTARY module and the normal Documents module.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a client user navigates to `documents.prepare` for a document linked to a NotaryRequest (`notary_request_id IS NOT NULL`) THEN the system allows the client to place signature fields on the eNOTARY document

1.2 WHEN a NotaryAdmin or any organization member navigates to `documents.prepare` for a document linked to a NotaryRequest THEN the system allows them to place signature fields on the eNOTARY document

1.3 WHEN a user clicks "Send for signature" on a document linked to a NotaryRequest THEN the system invokes `SendDocumentForSignatureService` and sends email-link based signing invitations (the normal Documents module flow)

1.4 WHEN the attorney triggers `digitalizeRequest()` (digital notarization) THEN the system does not record which attorney account performed the notarization — no fingerprint/seal identity is captured linking the action to the specific attorney's `NotaryCredential`

1.5 WHEN a NotaryAdmin user or any non-attorney user with organization access views the notary request workspace THEN the system displays the "Apply digital seal" / digitalization action without restricting it to the assigned attorney account

1.6 WHEN the `finalize` policy is evaluated THEN the system grants access to `SuperAdmin` and `NotaryAdmin` roles but does NOT grant access to the assigned attorney (`notary_user_id`) for the digitalization step that precedes finalization

### Expected Behavior (Correct)

2.1 WHEN a client user attempts to access `documents.prepare` for a document linked to a NotaryRequest THEN the system SHALL deny access and redirect with an appropriate error message indicating only the attorney can prepare fields for eNOTARY documents

2.2 WHEN any user other than the assigned attorney (`notary_user_id`) attempts to access `documents.prepare` for a document linked to a NotaryRequest THEN the system SHALL deny access, allowing only the assigned attorney to place signature fields

2.3 WHEN a document is linked to a NotaryRequest THEN the system SHALL NOT offer the "Send for signature" action (email-link flow) and SHALL instead require signing to occur during Step 7 (Digital Notarization) within the notarization ceremony context

2.4 WHEN the attorney triggers digital notarization (`digitalize`) THEN the system SHALL capture and record the attorney's digital fingerprint by storing the attorney's `NotaryCredential` identity (commission number, user ID, credential ID, and timestamp) as part of the digitalization journal entry and process metadata

2.5 WHEN a non-attorney user (including NotaryAdmin) attempts to trigger the "Apply digital seal" / digitalization action THEN the system SHALL deny the action, restricting it exclusively to the assigned attorney account (`notary_user_id` with `notary` role)

2.6 WHEN the assigned attorney triggers digitalization THEN the system SHALL verify that the authenticated user matches `notary_user_id` on the NotaryRequest before proceeding with seal application

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user accesses `documents.prepare` for a document that is NOT linked to any NotaryRequest (`notary_request_id IS NULL`) THEN the system SHALL CONTINUE TO allow field preparation following the normal Documents module authorization rules

3.2 WHEN a user sends a document for signature that is NOT linked to any NotaryRequest THEN the system SHALL CONTINUE TO use `SendDocumentForSignatureService` with the email-link signing flow as normal

3.3 WHEN the attorney (assigned `notary_user_id`) accesses `documents.prepare` for a document linked to their NotaryRequest THEN the system SHALL CONTINUE TO allow field preparation (this is the correct authorized user)

3.4 WHEN a NotaryAdmin triggers `finalizeRequest()` (finalization, not digitalization) on an attorney-approved request THEN the system SHALL CONTINUE TO allow finalization as currently implemented

3.5 WHEN the `NotaryDigitalizationService::digitalize()` method is called THEN the system SHALL CONTINUE TO apply the notary seal, generate QR codes, create certificates, anchor to blockchain, and create journal entries as currently implemented

3.6 WHEN a client creates a notary request and uploads documents THEN the system SHALL CONTINUE TO allow document upload during request creation without requiring attorney involvement at that stage
