# DocuTrust

Laravel 12 application with a Vite frontend and a separate Node-based blockchain sidecar for Polygon document anchoring.

## Blockchain Setup

DocuTrust anchors completed document hashes to Polygon through a backend signing wallet. The current integration is server-side only: Laravel calls the local blockchain sidecar, and the sidecar signs Polygon transactions with the configured wallet.

For this deployment model:

- `POLYGON_RPC_URL` should point to your Polygon RPC provider.
- `POLYGON_PRIVATE_KEY` should be the private key of the backend Polygon wallet used for anchoring transactions.
- A dedicated wallet is recommended. If you are using a Coins.ph Polygon wallet for custody, use that wallet's Polygon private key here.
- `DOCUMENT_NOTARY_ADDRESS` should be the deployed `DocumentNotary` contract address.
- `POLYGON_NETWORK` is used as an environment label in service health responses and logs, for example `amoy` or `mainnet`.

The blockchain sidecar must have enough POL/MATIC balance in the configured wallet to pay gas for anchor transactions.

## CI/CD

This repo now includes:

- `.github/workflows/ci.yml` for style checks, asset build, and the Laravel test suite
- `.github/workflows/deploy.yml` for production release packaging and droplet deployment
- `scripts/deploy.sh` for atomic release deployment on the host
- `scripts/post-deploy.sh` for migrations, cache warmup, and service restarts
- `deploy/systemd/*` and `deploy/nginx/*` as host templates

## Production Droplet Layout

Expected layout on the server:

```text
/var/www/docutrust
  /current
  /releases/<git-sha>
  /shared/.env
  /shared/storage
```

The deployment script unpacks each release into `releases/<git-sha>`, links shared state, runs Laravel post-deploy steps, and repoints `current`.

`scripts/deploy.sh` normalizes `shared/storage` and `bootstrap/cache` ownership before and after Artisan runs. This prevents deploy-owned log/cache files from causing production 500 responses when PHP-FPM writes as `www-data`. Run deployments as root or with passwordless `sudo` available to the deploy user so the script can apply ownership.

The post-deploy path runs:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## GitHub Secrets

Set these repository or environment secrets before enabling production deploys:

- `FLUX_USERNAME`
- `FLUX_LICENSE_KEY`
- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_SSH_KEY`
- `APP_BASE_PATH` with a value like `/var/www/docutrust`
- `PHP_BIN` with a value like `/usr/bin/php`
- `WEB_USER` optionally, with the PHP-FPM user. Defaults to `www-data`.

## Host Setup

Install and configure:

- Nginx
- PHP 8.4 with FPM and required Laravel extensions
- Node.js 22
- Composer
- Redis
- MySQL/MariaDB or a managed database

Then:

1. Copy `deploy/nginx/docutrust.conf` into your Nginx sites config and reload Nginx.
2. Copy `deploy/systemd/docutrust-queue@.service`, `deploy/systemd/docutrust-blockchain.service`, and `deploy/systemd/docutrust-reverb.service` into `/etc/systemd/system/`.
3. Create `/var/www/docutrust/shared/.env` from `deploy/production.env.example`.
4. Optionally create `/etc/default/docutrust-queue` from `deploy/systemd/docutrust-queue.env.example` to override worker flags such as `QUEUE_TIMEOUT`, `QUEUE_MEMORY`, or `QUEUE_EXTRA_ARGS`.
5. Enable queue workers by lane, then start the blockchain sidecar and Reverb:

```bash
systemctl daemon-reload
systemctl enable --now \
  docutrust-queue@default \
  docutrust-queue@documents \
  docutrust-queue@notifications \
  docutrust-queue@einvoices \
  docutrust-blockchain \
  docutrust-reverb
```

Use additional instances of the same lane when one queue needs more throughput, for example `docutrust-queue@documents-1` plus `docutrust-queue@documents-2`. The template strips a trailing numeric suffix, so both instances still consume the `documents` Laravel queue.

The legacy `deploy/systemd/docutrust-queue.service` file is still included as a compatibility fallback, but it is not the recommended production setup because it mixes all queue workloads into one worker pool.

## Queue Worker Sizing

Start with separate workers per lane, then scale by observed queue depth and job duration.

Recommended starting layout for a small production deployment:

- `docutrust-queue@default`: 1 worker, `QUEUE_TIMEOUT=90`, `QUEUE_MEMORY=256`
- `docutrust-queue@documents-1`: 1 worker, `QUEUE_TIMEOUT=300`, `QUEUE_MEMORY=512`
- `docutrust-queue@notifications`: 1 worker, `QUEUE_TIMEOUT=120`, `QUEUE_MEMORY=256`
- `docutrust-queue@einvoices`: 1 worker, `QUEUE_TIMEOUT=300`, `QUEUE_MEMORY=256`

Recommended starting layout for moderate traffic:

- `docutrust-queue@default`: 1 worker
- `docutrust-queue@documents-1` and `docutrust-queue@documents-2`: 2 workers total
- `docutrust-queue@notifications-1` and `docutrust-queue@notifications-2`: 2 workers total
- `docutrust-queue@einvoices`: 1 worker

Lane guidance:

- `default`: keep this small. It should carry miscellaneous short jobs, not bulk document work.
- `documents`: give this the highest timeout and more memory. PDF stamping, sealing, archive generation, and certificate work are the most likely long-running jobs.
- `notifications`: scale this for burst fan-out after document completion or reminder campaigns. Latency matters more than memory.
- `einvoices`: isolate this from user-facing jobs because vendor latency and retries can stall the lane.

If you need per-lane overrides, create systemd drop-ins instead of changing the shared env file for everything:

```bash
systemctl edit docutrust-queue@documents-1
```

Example drop-in:

```ini
[Service]
Environment=QUEUE_TIMEOUT=300
Environment=QUEUE_MEMORY=768
Environment=QUEUE_EXTRA_ARGS=--backoff=10
```

Example templates are included in:

- `deploy/systemd/docutrust-queue-documents.override.example.conf`
- `deploy/systemd/docutrust-queue-notifications.override.example.conf`
- `deploy/systemd/docutrust-queue-einvoices.override.example.conf`

Apply one like this:

```bash
mkdir -p /etc/systemd/system/docutrust-queue@documents-1.service.d
cp deploy/systemd/docutrust-queue-documents.override.example.conf \
  /etc/systemd/system/docutrust-queue@documents-1.service.d/override.conf
systemctl daemon-reload
systemctl restart docutrust-queue@documents-1
```

Operational targets worth watching:

- `documents`: queue depth should return near zero after signing bursts
- `notifications`: p95 delivery lag should stay low even during document completion spikes
- `einvoices`: retries should not starve other lanes
- all lanes: failed jobs should stay at zero during baseline load tests

## Load Test Rollout

Use the queue split and `k6` scripts together as a staged rollout instead of jumping straight to peak traffic.

1. Baseline
Run one worker per lane and a light `k6` profile against staging. Confirm no failed jobs and that each queue drains back to idle.

2. Documents stress
Increase only `documents` concurrency first. Watch final PDF and certificate lag, memory growth, and queue depth on the `documents` lane.

3. Notification burst
Run completion-heavy flows or reminder bursts. Scale `notifications` independently if delivery lag rises while web latency stays healthy.

4. Vendor isolation
Exercise `einvoices` and webhook burst tests separately from user-facing flows. This confirms slow vendor calls do not interfere with signing and document completion.

5. Soak
Hold moderate traffic for at least 1 to 2 hours. Look for queue buildup, worker restarts, Redis pressure, and DB slow-query drift.

## Operator Checks

Two small scripts are included for staging and production verification:

- `scripts/check-queue-workers.sh`
  Shows `systemctl status` for the main queue lanes and blockchain sidecar.
- `scripts/check-queue-depth.sh`
  Reads Redis queue depth for `default`, `documents`, `notifications`, and `einvoices`.
- `scripts/check-queue-workers.ps1`
  PowerShell variant of the worker status check.
- `scripts/check-queue-depth.ps1`
  PowerShell variant of the Redis queue depth check.

Examples:

```bash
bash scripts/check-queue-workers.sh
bash scripts/check-queue-depth.sh
```

If your deployment path differs from `/var/www/docutrust`, set `APP_BASE_PATH`:

```bash
APP_BASE_PATH=/srv/docutrust bash scripts/check-queue-depth.sh
```

PowerShell examples:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\check-queue-workers.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\check-queue-depth.ps1 -AppBasePath /srv/docutrust
```

## Root CA Key Storage

For production, prefer storing the root CA private key outside the database.

- Set `DOCUTRUST_ROOT_CA_PRIVATE_KEY_PATH` to a filesystem path readable only by the application user.
- New root CA keys will be written to that file and the database will store the marker `external://root-ca`.
- For an existing deployment that still has the root CA private key stored in the database, run:

```bash
php artisan docutrust:move-root-ca-key
```

- Use `php artisan docutrust:move-root-ca-key --force` only if you intentionally need to overwrite an existing external key file.

## CSC-Style Remote Signing

For cloud signing, prefer `DOCUTRUST_SIGNING_BACKEND=remote_managed` in production.

- `REMOTE_SIGNING_API_MODE=csc` configures the remote adapter for a CSC-style `signatures/signHash` flow.
- Each signer should have a provider credential ID available through `document_signers.remote_credential_id`. A global fallback can be set with `REMOTE_SIGNING_DEFAULT_CREDENTIAL_ID`, but signer-specific credentials are preferred.
- The remote adapter signs the final document hash and stores the returned certificate chain plus provider evidence on the signature record.
- App-managed signing remains available for internal or development use, but it is not the target architecture for a CSC-aligned deployment.

## Notes

- The workflow ships built frontend assets and PHP vendor dependencies as a release artifact.
- Root `node_modules` are not deployed; `blockchain-service/node_modules` are deployed because that service runs on the droplet.
- Production should use MySQL/MariaDB and Redis rather than SQLite.
