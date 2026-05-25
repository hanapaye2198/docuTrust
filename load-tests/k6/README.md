# k6 Load Tests

This folder contains repo-local `k6` scripts for staging and pre-production load testing.

## Safety

- Run these scripts against staging or another isolated environment.
- Do not aim webhook burst tests at production vendor endpoints.
- Public signing routes in this app are rate-limited per token and IP. Distribute traffic across multiple test tokens or IPs if you want realistic concurrency.

## Prerequisites

- Install `k6`: https://grafana.com/docs/k6/latest/set-up/install-k6/
- Export the environment variables required by the script you want to run.

## Scripts

- `public-signing.js`
  Exercises public signing pages, optional unlock, PDF fetch, trust authorization poll, and field-signature submission.
- `authenticated-documents.js`
  Exercises authenticated dashboard and document read paths using an existing Laravel session cookie.
- `webhook-burst.js`
  Sends signed webhook bursts to GatewayHub or Sumsub handlers.

## Common environment variables

- `BASE_URL`
  Base app URL, for example `https://staging.docutrust.example`
- `K6_STAGES`
  Optional JSON array for `stages`, for example `[{"duration":"2m","target":10},{"duration":"5m","target":30},{"duration":"1m","target":0}]`

## Public signing example

```powershell
$env:BASE_URL = "https://staging.docutrust.example"
$env:SIGN_TOKEN = "replace-with-real-access-token"
$env:SIGN_PASSWORD = "optional-document-password"
$env:SIGN_FIELD_ID = "123"
$env:SIGN_FIELD_VALUE = "Jane Tester"
$env:SIGNATURE_IMAGE_DATA_URL = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB..."
k6 run load-tests/k6/public-signing.js
```

Notes:

- `SIGN_FIELD_ID`, `SIGN_FIELD_VALUE`, and `SIGNATURE_IMAGE_DATA_URL` are only needed if you want to exercise `POST /sign/{token}/signature`.
- `TRUST_SESSION_ID` can be supplied to exercise `GET /sign/{token}/trust/authorize/{session}`.

## Authenticated document example

```powershell
$env:BASE_URL = "https://staging.docutrust.example"
$env:LARAVEL_SESSION = "replace-with-session-cookie"
$env:XSRF_TOKEN = "replace-with-xsrf-cookie-if-needed"
$env:DOCUMENT_ID = "42"
$env:SIGNER_ID = "101"
k6 run load-tests/k6/authenticated-documents.js
```

## Webhook burst examples

GatewayHub:

```powershell
$env:BASE_URL = "https://staging.docutrust.example"
$env:WEBHOOK_PROVIDER = "gatewayhub"
$env:GATEWAYHUB_WEBHOOK_SECRET = "replace-with-secret"
k6 run load-tests/k6/webhook-burst.js
```

Sumsub:

```powershell
$env:BASE_URL = "https://staging.docutrust.example"
$env:WEBHOOK_PROVIDER = "sumsub"
$env:SUMSUB_WEBHOOK_SECRET = "replace-with-secret"
k6 run load-tests/k6/webhook-burst.js
```

## What to watch during runs

- nginx status, 499s, 502s, 504s
- PHP-FPM busy workers and request duration
- Redis queue depth by lane
- MySQL slow queries and CPU
- Laravel failed jobs
- time from signer completion to final PDF and certificate availability
