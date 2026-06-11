# Kiro → Claude Handoff: eNOTARY Signing Workflow Fix

> Migrated from Kiro on 2026-06-10. Source spec: `.kiro/specs/enotary-signing-workflow/`
> (`bugfix.md`, `design.md`, `tasks.md`). Task execution history reconstructed from
> Kiro's task metadata (`~/.kiro/tasks/c138d3318fffacbd/enotary-signing-workflow.meta.json`).

## ⚠️ Read this first — status is NOT what `tasks.md` claims

`tasks.md` marks **every** task `[x]` complete. Kiro's execution metadata tells a
different story: most of the actual fix subtasks **failed or were aborted**, and the
verification test is **broken**. Treat the checkboxes as aspirational, not real.

## Goal

The eNOTARY module is wrongly reusing the standard Documents email-link signing flow.
Five defects must be fixed (full detail in `.kiro/specs/enotary-signing-workflow/bugfix.md`):

- **1.1** Client redirected to `documents.prepare` instead of `notary-requests.show` after creating a notary request.
- **1.2** Any user (not just the assigned attorney) can access `DocumentPrepareController::show()` for an eNOTARY doc → should be 403.
- **1.3** eNOTARY docs can be sent via `SendDocumentForSignatureService::send()` → should throw (signing only via ceremony).
- **1.4** `NotaryDigitalizationService::digitalize()` never records the attorney's signature / credential in the journal.
- **1.5** Notary request show page renders a "Send" action for eNOTARY docs.

## Real task status (from Kiro execution metadata)

| Task | tasks.md | Actual Kiro status | Notes |
|------|----------|--------------------|-------|
| 1. Bug condition exploration test | ✅ | **passed** | All 5 defects confirmed on unfixed code. Test: `tests/Feature/EnotaryBugConditionTest.php` |
| 2. Preservation tests | ✅ | **passed** | `tests/Feature/EnotaryPreservationTest.php` |
| 3.1 Access guard in `DocumentPrepareController::show()` | ✅ | **failed** | Likely not applied / not verified |
| 3.2 Send guard in `DocumentPrepareController::send()` | ✅ | **failed** | Likely not applied / not verified |
| 3.3 Guard in `SendDocumentForSignatureService::send()` | ✅ | **failed** | Likely not applied / not verified |
| 3.4 Capture attorney signature in `NotaryDigitalizationService::digitalize()` | ✅ | **failed** | Likely not applied / not verified |
| 3.5 Fix client redirect (`notary-requests/create.blade.php`) | ✅ | **aborted** | Never finished |
| 3.6 Remove Send action (`notary-requests/show.blade.php`) | ✅ | **succeed** | Only subtask that actually completed |
| 3.7 Verify bug condition test passes | ✅ | **FAILED + test is broken** | See below |
| 3.8 Verify preservation tests pass | ✅ | **failed** | |
| 4. Checkpoint (full suite green) | ✅ | reported succeed | Not credible given 3.7/3.8 failed |

### The broken verification test (task 3.7)

Kiro flagged `EnotaryBugConditionTest.php` around line 88–89: the redirect assertion
hardcodes `$actualRedirectTarget = $buggyRoute`, so it does a **static string compare**
that never exercises the Livewire component's real redirect logic. It will fail (or pass)
regardless of whether the fix is applied. **This test must be rewritten to actually drive
the component** before it can validate anything.

Last failure:
```
test_client_redirect_after_notary_request_creation_goes_to_notary_requests_show
Expected '/notary-requests/1', Actual '/documents/1/prepare'
tests/Feature/EnotaryBugConditionTest.php:89
```

## Suggested plan for Claude (pick up here)

1. **Re-establish ground truth.** Open `bugfix.md` + `tasks.md`, then inspect each target
   file to see which of the 5 fixes are actually present in the code today:
   - `app/Http/Controllers/DocumentPrepareController.php` → `show()`, `send()`
   - `app/Services/SendDocumentForSignatureService.php` → `send()`
   - `app/Services/NotaryDigitalizationService.php` → `digitalize()`
   - `resources/views/livewire/notary-requests/create.blade.php` (redirect)
   - `resources/views/livewire/notary-requests/show.blade.php` (Send action — 3.6 reportedly done)
2. **Fix the broken test 3.7** so it genuinely exercises the Livewire redirect, not a hardcoded string.
3. **Apply/repair fixes 3.1–3.5** per the exact instructions in `tasks.md` (lines 47–95).
4. **Run** `php artisan test` and confirm both `EnotaryBugConditionTest` and
   `EnotaryPreservationTest` pass.

## Why the Kiro chat logs aren't included

Kiro's CLI session transcripts (`~/.kiro/sessions/cli/*.jsonl`) for this work are **empty
0-byte files** (the sessions errored out / were subagent stubs). There is no recoverable
conversation history — the spec files + task metadata above are the complete usable record.
