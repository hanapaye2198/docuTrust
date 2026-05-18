# eNOTARY Signing Workflow Bugfix Design

## Overview

The eNOTARY module's signing workflow is incorrectly sharing code paths with the standard Documents module. Five defects allow clients to prepare signature fields (attorney-only), permit sending eNOTARY documents through the email-link flow (must use Step 7 ceremony), omit the attorney's written signature during digitalization, and expose a "Send" action on the notary request show page. The fix enforces architectural separation by adding eNOTARY guards at each boundary: redirect, access control, send prevention, signature capture, and UI gating.

## Glossary

- **Bug_Condition (C)**: A document is linked to a notary request (`notary_request_id IS NOT NULL`) and the system incorrectly allows standard Documents module operations on it
- **Property (P)**: eNOTARY documents are exclusively managed through the notarization ceremony workflow — field preparation restricted to the attorney, sending blocked, attorney signature captured during digitalization
- **Preservation**: All standard Documents module behavior for documents NOT linked to a notary request must remain unchanged
- **`DocumentPrepareController`**: Controller in `app/Http/Controllers/DocumentPrepareController.php` handling signature field placement and document sending
- **`SendDocumentForSignatureService`**: Service in `app/Services/SendDocumentForSignatureService.php` that transitions a document from Draft to Pending and dispatches signer invitations
- **`NotaryDigitalizationService`**: Service in `app/Services/NotaryDigitalizationService.php` that performs Step 7 — seal, QR, certificates, blockchain anchoring
- **`NotaryCredential`**: Model holding the attorney's commission details, seal image, and signature image
- **`notary_request_id`**: Foreign key on the `documents` table linking a document to a `NotaryRequest`
- **`notary_user_id`**: The assigned attorney's user ID on a `NotaryRequest`

## Bug Details

### Bug Condition

The bug manifests when a document is linked to a notary request (`notary_request_id` is set) and the system treats it identically to a standard document — allowing unrestricted field preparation, email-link sending, and omitting the attorney's signature during digitalization.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type {document: Document, user: User, action: String}
  OUTPUT: boolean

  LET isEnotaryDocument = input.document.notary_request_id IS NOT NULL

  CASE input.action OF
    "prepare_access":
      RETURN isEnotaryDocument
             AND input.user.id != input.document.notaryRequest.notary_user_id
             AND system allows access (no 403)

    "send_document":
      RETURN isEnotaryDocument
             AND system allows SendDocumentForSignatureService::send() to execute

    "digitalize":
      RETURN isEnotaryDocument
             AND NotaryDigitalizationService::digitalize() completes
             AND attorney written signature is NOT captured

    "show_send_action":
      RETURN isEnotaryDocument
             AND UI renders "Send" button for the document

    "client_redirect":
      RETURN isEnotaryDocument
             AND client is redirected to documents.prepare after creation
  END CASE
END FUNCTION
```

### Examples

- **Client redirect**: Client creates notary request, uploads `contract.pdf` → system redirects to `/documents/42/prepare` instead of `/notary-requests/7` (defect 1.1)
- **Unauthorized field placement**: Client (user_id=5) accesses `/documents/42/prepare` where document has `notary_request_id=7` and `notary_user_id=3` → system allows access (defect 1.2)
- **Email-link send**: User clicks "Send" on notary request show page → `SendDocumentForSignatureService::send()` executes, transitions document to Pending, sends email links (defect 1.3)
- **Missing attorney signature**: `NotaryDigitalizationService::digitalize()` completes → seal applied, QR generated, but no attorney written signature captured and no credential fingerprint recorded (defect 1.4)
- **Send button visible**: Notary request show page renders "Send" action next to linked documents (defect 1.5)

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Documents with `notary_request_id = NULL` continue to use `DocumentPrepareController` without any eNOTARY restrictions
- Documents with `notary_request_id = NULL` continue to be sendable via `SendDocumentForSignatureService`
- Client can still upload documents during notary request creation (Step 1)
- Attorney (notary role) creating a notary request continues to redirect to the notary request show page
- `NotaryDigitalizationService::digitalize()` continues to apply seal, generate QR codes, generate certificates, anchor to blockchain, and timestamp
- `DocumentPolicy::update()` continues to work for standard documents without eNOTARY logic interfering

**Scope:**
All inputs where `document.notary_request_id IS NULL` should be completely unaffected by this fix. This includes:
- Standard document preparation and field placement
- Standard document sending via email-link flow
- Template-based document creation
- Document viewing, downloading, and certificate generation

## Hypothesized Root Cause

Based on the bug description and code analysis, the root causes are:

1. **Missing eNOTARY Guard in DocumentPrepareController::show()**: The `show()` method only calls `$this->authorize('update', $document)` which delegates to `DocumentPolicy::update()`. This policy checks organization membership but has no awareness of `notary_request_id` or attorney role. Any user who can view the document can prepare fields.

2. **Missing eNOTARY Guard in SendDocumentForSignatureService::send()**: The service validates document status, participants, and fields but never checks whether the document is linked to a notary request. There is no conditional that prevents eNOTARY documents from entering the email-link flow.

3. **Incorrect Client Redirect in Notary Request Create**: The Livewire `create.blade.php` component redirects the client to `documents.prepare` after creating the request and document. It should redirect to the notary request show page instead.

4. **Missing Attorney Signature Capture in NotaryDigitalizationService::digitalize()**: The `digitalize()` method processes artifacts, blockchain, QR, seal, and certificates but never invokes signature capture for the attorney. The `NotaryCredential` model has a `signature_image_path` field but it is not used during digitalization to apply the attorney's written signature to documents.

5. **UI Showing Send Action on Notary Request Show Page**: The `show.blade.php` Livewire component has a `sendLinkedDocument()` method and renders a "Send" button without checking whether the document is an eNOTARY document (which it always is on this page).

## Correctness Properties

Property 1: Bug Condition - eNOTARY Document Access Control

_For any_ request to `DocumentPrepareController::show()` where the document has `notary_request_id IS NOT NULL` and the authenticated user is NOT the assigned attorney (`notary_user_id`), the fixed controller SHALL return a 403 Forbidden response, preventing unauthorized field placement.

**Validates: Requirements 2.2**

Property 2: Bug Condition - eNOTARY Document Send Prevention

_For any_ call to `SendDocumentForSignatureService::send()` where the document has `notary_request_id IS NOT NULL`, the fixed service SHALL throw a RuntimeException with a message indicating eNOTARY documents must be signed during the notarization ceremony.

**Validates: Requirements 2.3**

Property 3: Bug Condition - Attorney Signature Capture During Digitalization

_For any_ execution of `NotaryDigitalizationService::digitalize()` where the notary request has an assigned attorney with a valid `NotaryCredential`, the fixed service SHALL capture the attorney's written signature and record the attorney's credential identity (digital fingerprint) as part of the notarization artifacts.

**Validates: Requirements 2.4**

Property 4: Preservation - Standard Document Workflow Unchanged

_For any_ document where `notary_request_id IS NULL`, the fixed code SHALL produce exactly the same behavior as the original code for `DocumentPrepareController::show()`, `DocumentPrepareController::send()`, and `SendDocumentForSignatureService::send()`, preserving all existing standard document workflow functionality.

**Validates: Requirements 3.1, 3.2, 3.6**

Property 5: Preservation - Digitalization Existing Behavior Unchanged

_For any_ execution of `NotaryDigitalizationService::digitalize()`, the fixed service SHALL continue to apply the notary seal, generate QR codes, generate certificates, anchor to blockchain, and timestamp documents exactly as before, with the attorney signature capture being an addition rather than a replacement.

**Validates: Requirements 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `app/Http/Controllers/DocumentPrepareController.php`

**Function**: `show()`

**Specific Changes**:
1. **Add eNOTARY access guard**: After the existing `$this->authorize('update', $document)` call, check if `$document->notary_request_id !== null`. If so, load the notary request and verify `auth()->id() === $document->notaryRequest->notary_user_id`. If not the attorney, `abort(403)`.

**Function**: `send()`

**Specific Changes**:
2. **Add eNOTARY send guard**: Before calling `$sender->send($document)`, check if `$document->notary_request_id !== null`. If so, redirect back with an error message stating eNOTARY documents cannot be sent via the standard flow.

---

**File**: `app/Services/SendDocumentForSignatureService.php`

**Function**: `send()`

**Specific Changes**:
3. **Add eNOTARY guard at service level**: At the top of the `send()` method (after the refresh/load), add a check: if `$document->notary_request_id !== null`, throw a `RuntimeException` with message indicating eNOTARY documents must be signed during the notarization ceremony. This provides defense-in-depth beyond the controller guard.

---

**File**: `app/Services/NotaryDigitalizationService.php`

**Function**: `digitalize()`

**Specific Changes**:
4. **Capture attorney's written signature**: After applying the notary seal (Step 3), add a new step that:
   - Resolves the attorney's `NotaryCredential` (already done via `resolveCredential()`)
   - Reads the `signature_image_path` from the credential
   - Applies the attorney's written signature to each document (similar to how the seal is applied)
   - Records the attorney's credential identity (commission number, user ID) in the journal entry's `legal_assertions`

5. **Record credential fingerprint**: Update the `NotaryJournal` entry created at the end of `digitalize()` to include `attorney_credential_id`, `attorney_commission_number`, and `attorney_signature_applied` in the `legal_assertions` array.

---

**File**: `resources/views/livewire/notary-requests/create.blade.php`

**Specific Changes**:
6. **Fix client redirect**: Change the post-creation redirect for client users from `route('documents.prepare', $document)` to the notary request show page route with a success message indicating the request was created and the attorney will prepare signature fields.

---

**File**: `resources/views/livewire/notary-requests/show.blade.php`

**Specific Changes**:
7. **Remove Send action for eNOTARY documents**: Remove or conditionally hide the "Send" button/action for documents linked to the current notary request. Since all documents on this page are eNOTARY documents, the `sendLinkedDocument()` method should either be removed or guarded. The UI should not offer the standard send flow — signing is handled exclusively through the `digitalizeRequest()` action (Step 7).

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write feature tests that exercise each defective code path with eNOTARY documents. Run these tests on the UNFIXED code to observe that they pass (demonstrating the bug exists — the system incorrectly allows the actions).

**Test Cases**:
1. **Client Redirect Test**: Create a notary request as a client user, verify the response redirects to `documents.prepare` (will pass on unfixed code, demonstrating defect 1.1)
2. **Unauthorized Prepare Access Test**: Authenticate as a non-attorney user, GET `documents.prepare` for a document with `notary_request_id` set — verify 200 response (will pass on unfixed code, demonstrating defect 1.2)
3. **eNOTARY Document Send Test**: Call `SendDocumentForSignatureService::send()` on a document with `notary_request_id` set — verify no exception thrown (will pass on unfixed code, demonstrating defect 1.3)
4. **Missing Attorney Signature Test**: Call `NotaryDigitalizationService::digitalize()` and inspect the journal entry — verify no attorney signature data recorded (will pass on unfixed code, demonstrating defect 1.4)

**Expected Counterexamples**:
- Non-attorney users can access the prepare page for eNOTARY documents (200 instead of 403)
- `SendDocumentForSignatureService::send()` succeeds for eNOTARY documents (no exception)
- Digitalization completes without attorney signature capture (journal lacks credential fingerprint)

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  CASE input.action OF
    "prepare_access":
      response := DocumentPrepareController::show(input.document)
      ASSERT response.status == 403

    "send_document":
      ASSERT SendDocumentForSignatureService::send(input.document) THROWS RuntimeException
      ASSERT exception.message CONTAINS "notarization ceremony"

    "digitalize":
      result := NotaryDigitalizationService::digitalize(input.notaryRequest)
      journal := latestJournal(result)
      ASSERT journal.legal_assertions.attorney_signature_applied == true
      ASSERT journal.legal_assertions.attorney_credential_id IS NOT NULL

    "client_redirect":
      response := createNotaryRequest(input)
      ASSERT response.redirectsTo(notary-requests.show)
  END CASE
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  CASE input.action OF
    "prepare_access":
      ASSERT DocumentPrepareController_fixed::show(input.document)
             == DocumentPrepareController_original::show(input.document)

    "send_document":
      ASSERT SendDocumentForSignatureService_fixed::send(input.document)
             == SendDocumentForSignatureService_original::send(input.document)

    "digitalize":
      // Existing steps still execute identically
      ASSERT seal_applied AND qr_generated AND certificates_generated
             AND blockchain_anchored AND timestamp_applied
  END CASE
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many document configurations (various statuses, participant counts, field layouts) automatically
- It catches edge cases where the eNOTARY guard might accidentally trigger on standard documents
- It provides strong guarantees that the standard workflow is unchanged across the input domain

**Test Plan**: Observe behavior on UNFIXED code first for standard documents (no `notary_request_id`), then write property-based tests capturing that behavior.

**Test Cases**:
1. **Standard Document Prepare Access**: Verify that documents with `notary_request_id = NULL` continue to allow any authorized user to access `documents.prepare` — test with various user roles and organization memberships
2. **Standard Document Send**: Verify that documents with `notary_request_id = NULL` continue to be sendable via `SendDocumentForSignatureService` — test with various document states and participant configurations
3. **Digitalization Existing Steps**: Verify that `digitalize()` continues to produce seal, QR, certificates, blockchain proof, and journal entries with the same structure (plus the new attorney signature fields)

### Unit Tests

- Test `DocumentPrepareController::show()` returns 403 for non-attorney users on eNOTARY documents
- Test `DocumentPrepareController::show()` returns 200 for the assigned attorney on eNOTARY documents
- Test `DocumentPrepareController::show()` returns 200 for any authorized user on standard documents
- Test `SendDocumentForSignatureService::send()` throws RuntimeException for eNOTARY documents
- Test `SendDocumentForSignatureService::send()` succeeds for standard documents (existing behavior)
- Test `NotaryDigitalizationService::digitalize()` records attorney signature and credential fingerprint
- Test client redirect goes to notary request show page after creation
- Test "Send" action is not rendered on notary request show page

### Property-Based Tests

- Generate random documents with/without `notary_request_id` and random users, verify access control is correctly partitioned (403 for non-attorney on eNOTARY, 200 for attorney, 200 for authorized users on standard)
- Generate random document configurations (status, participants, fields) with `notary_request_id = NULL`, verify `SendDocumentForSignatureService::send()` behavior is identical to unfixed code
- Generate random notary requests with various credential states, verify `digitalize()` always captures attorney signature when credential exists and gracefully handles missing credentials

### Integration Tests

- Test full notary request creation flow as client: upload document → verify redirect to show page → verify document is in Draft status with `notary_request_id` set
- Test attorney accessing prepare page for their assigned document → place fields → verify fields saved
- Test attempting to send eNOTARY document from both controller and service level → verify blocked
- Test full digitalization flow → verify attorney signature captured alongside seal, QR, and certificates
- Test standard document flow end-to-end (create → prepare → send) → verify completely unaffected by eNOTARY guards
