# Requirements: Attorney Signing After Video Conference

## Overview
After the video conference is completed, the attorney must be able to prepare signature fields for their own signature on the **same document** that already contains the client's signatures, then sign it. The document with client signatures is the same document the attorney signs — not a separate one.

## Functional Requirements

### 1. Attorney Field Preparation (Post-Video)
- 1.1: After a video session is completed, the attorney can open the prepare page to place their own signature fields on the document
- 1.2: The document shown on the prepare page must be the **same document** that already has client signatures (the completed/signed document)
- 1.3: The attorney's fields are added alongside existing client signature fields (not replacing them)
- 1.4: The prepare page must show the document PDF with client signatures already rendered (using `prepared_pdf_path` or `final_pdf_path`)

### 2. Attorney Signing Flow
- 2.1: After saving attorney signature fields, the attorney is redirected to the signing page OR a signing link is displayed on the notary request show page
- 2.2: The attorney signs using the `AccountVerified` signing method (drawn/handwritten signature)
- 2.3: The attorney signs on the same document that has client signatures — the final PDF includes both client and attorney signatures

### 3. Document State Management
- 3.1: The document must be transitioned back to a signable state for the attorney (currently it's `completed` after all signers finish)
- 3.2: The attorney is added as a `DocumentSigner` with `signing_order: 999` and `AccountVerified` method
- 3.3: After the attorney signs, the document returns to `completed` status with both client + attorney signatures

### 4. UI Integration
- 4.1: On the notary request show page, after video session is completed, show a "Prepare Attorney Fields" button for each completed document
- 4.2: After fields are saved, show a "Sign as Attorney" link/button that takes the attorney to the signing page
- 4.3: The workflow step indicator should reflect the current state (fields prepared vs. signed)

## Non-Functional Requirements
- 5.1: The existing client signatures must NOT be affected when the attorney prepares their fields
- 5.2: The prepare page must use the signed PDF (with client signatures visible) as the background
- 5.3: The standard signing flow (`SignDocumentController`) is reused for the attorney's signing action
