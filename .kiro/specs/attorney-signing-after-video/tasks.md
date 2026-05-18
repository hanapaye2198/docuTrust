# Implementation Tasks

- [ ] 1. Allow prepare page access for completed eNOTARY documents (attorney only)
  - File: `app/Http/Controllers/DocumentPrepareController.php`, method: `show()`
  - Change the `if ($document->status !== DocumentStatus::Draft) { abort(403); }` check
  - For eNOTARY documents where the user is the assigned attorney, allow `pending` and `completed` status
  - Keep the `Draft`-only restriction for non-eNOTARY documents
  - _Requirements: 1.1, 1.2, 3.1_

- [ ] 2. Update `signAsAttorney()` to redirect to prepare page instead of signing page
  - File: `resources/views/livewire/notary-requests/show.blade.php`, method: `signAsAttorney()`
  - Instead of redirecting directly to `notary.sign.account.show`, redirect to `notary.documents.prepare`
  - Add the attorney as a `DocumentSigner` (AccountVerified, order 999) if not already added
  - Transition document status from `completed` to `pending` so the prepare page allows field placement
  - _Requirements: 1.1, 2.1, 3.1, 3.2, 4.1_

- [ ] 3. Update `DocumentPrepareController::store()` to redirect attorney to signing page after saving fields
  - File: `app/Http/Controllers/DocumentPrepareController.php`, method: `store()`
  - After saving fields for an eNOTARY document where the attorney is the user:
    - Find the attorney's `DocumentSigner` record
    - Redirect to `route('notary.sign.account.show', $attorneySigner->id)`
  - This gives the flow: Place fields → Save → Sign
  - _Requirements: 2.1, 2.2_

- [ ] 4. Update the prepare page PDF source to show client-signed document
  - File: `app/Http/Controllers/DocumentPrepareController.php`, method: `show()`
  - For eNOTARY documents in `pending`/`completed` status, ensure the PDF URL uses the signed version
  - The `resolveStreamUrl()` already streams the source PDF — verify it uses `prepared_pdf_path` or `final_pdf_path` when available
  - _Requirements: 1.4, 5.1, 5.2_

- [ ] 5. Rename UI button from "Sign as Attorney" to "Prepare Attorney Fields"
  - File: `resources/views/livewire/notary-requests/show.blade.php`
  - Change the button label in the document card section (completed documents, after video session)
  - Keep the same `wire:click="signAsAttorney({{ $document->id }})"` action
  - _Requirements: 4.1, 4.2_

- [ ] 6. Verify the signing flow works end-to-end
  - After attorney saves fields and is redirected to signing page, verify:
    - The signing page shows the document with client signatures + attorney's empty fields
    - Attorney can draw/capture their signature
    - After signing, document returns to `completed` status
    - The notary request show page reflects "Attorney signed" state
  - _Requirements: 2.2, 2.3, 3.3, 4.3_
