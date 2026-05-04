# DocuTrust

Laravel 12 application with a Vite frontend and a separate Node-based blockchain sidecar for Polygon document anchoring.

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

## GitHub Secrets

Set these repository or environment secrets before enabling production deploys:

- `FLUX_USERNAME`
- `FLUX_LICENSE_KEY`
- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_SSH_KEY`
- `APP_BASE_PATH` with a value like `/var/www/docutrust`
- `PHP_BIN` with a value like `/usr/bin/php`

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

## Notes

- The workflow ships built frontend assets and PHP vendor dependencies as a release artifact.
- Root `node_modules` are not deployed; `blockchain-service/node_modules` are deployed because that service runs on the droplet.
- Production should use MySQL/MariaDB and Redis rather than SQLite.
