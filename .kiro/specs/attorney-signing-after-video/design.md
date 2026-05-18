# Design: Attorney Signing After Video Conference

## Architecture

The attorney signing flow reuses the existing `DocumentPrepareController` and `SignDocumentController` infrastructure. The key challenge is that after all signers complete, the document is in `completed` status — we need to transition it back to allow the attorney to place fields and sign.

## Flow

```
Video Session Completed
    ↓
Attorney clicks "Prepare Attorney Fields" (show page)
    ↓
Document transitions: completed → pending (attorney signing phase)
Attorney added as DocumentSigner (AccountVerified, order 999)
    ↓
Attorney opens prepare page (sees PDF with client signatures)
Places their signature fields
Saves fields
    ↓
Redirected to signing page (notary.sign.account.show)
    ↓
Attorney draws/captures signature
    ↓
Document completes again (now with both client + attorney signatures)
    ↓
"Apply Digital Seal" becomes available
```

## Key Design Decisions

### 1. Document PDF Source for Attorney Prepare Page
- When the attorney opens the prepare page after clients have signed, the PDF shown must include client signatures
- Use `prepared_pdf_path` or `final_pdf_path` (whichever was generated after client signing completed)
- The `DocumentPrepareController::show()` already uses `$document->sourcePdfPath()` which resolves the correct path

### 2. Document Status Transition
- After all clients sign → document is `completed`
- When attorney clicks "Prepare Attorney Fields" → document transitions to `pending` 
- This allows the prepare page to work (it currently requires `draft` status)
- **Change**: Allow prepare page access for documents in `pending` status when the user is the attorney on an eNOTARY document
- After attorney signs → document returns to `completed`

### 3. Reusing Existing Infrastructure
- `DocumentPrepareController::show()` — already handles eNOTARY attorney access
- `DocumentPrepareController::store()` — saves fields (no status change needed)
- `SignDocumentController::showAuthenticated()` — handles account-verified signing
- `signAsAttorney()` method in show.blade.php — already adds attorney as DocumentSigner

### 4. Changes Required

#### `DocumentPrepareController::show()`
- Remove the `$document->status !== DocumentStatus::Draft` abort for eNOTARY documents where the attorney is preparing their own fields
- Allow `pending` or `completed` status when `notary_request_id` is set and user is the attorney

#### `show.blade.php` (Livewire)
- Rename "Sign as Attorney" to "Prepare Attorney Fields" 
- The `signAsAttorney()` method should:
  1. Add attorney as DocumentSigner (if not already)
  2. Transition document to `pending` status (so prepare page works)
  3. Redirect to the prepare page (not directly to signing)
- After fields are saved, the `store()` method redirects to the signing page

#### `DocumentPrepareController::store()` (for eNOTARY attorney)
- After saving attorney fields, redirect to the attorney's signing page instead of back to prepare
- This gives the flow: Prepare fields → Save → Signing page

## Component Interaction

```
show.blade.php                    DocumentPrepareController         SignDocumentController
     |                                      |                              |
     |-- "Prepare Attorney Fields" -->      |                              |
     |   (adds attorney as signer,          |                              |
     |    sets status to pending)           |                              |
     |                                      |                              |
     |-- redirect to prepare page --------> |                              |
     |                                      |-- show() renders PDF         |
     |                                      |   with client sigs           |
     |                                      |                              |
     |                                      |-- store() saves fields       |
     |                                      |-- redirect to sign --------> |
     |                                      |                              |-- showAuthenticated()
     |                                      |                              |-- attorney signs
     |                                      |                              |-- document → completed
     |                                      |                              |
     |<-- back to show page (completed) ----|<-----------------------------|
```
