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
2. Copy `deploy/systemd/docutrust-queue.service` and `deploy/systemd/docutrust-blockchain.service` into `/etc/systemd/system/`.
3. Create `/var/www/docutrust/shared/.env` from `deploy/production.env.example`.
4. Enable and start the services with `systemctl daemon-reload` followed by `systemctl enable --now docutrust-queue docutrust-blockchain`.

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
