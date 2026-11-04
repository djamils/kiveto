---
title: 'Production images and deploy files'
slug: 'production-image'
created: '2026-11-04'
status: 'implemented'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Docker BuildKit', 'PHP 8.5.12 FPM (Debian bookworm)', 'Nginx 1.30', 'Docker Compose v2', 'Symfony 7.4', 'Doctrine DBAL 4']
files_to_modify: ['Dockerfile (new)', '.dockerignore (new)', '.env', '.env.prod (new)', 'config/packages/doctrine.yaml', 'docker/production/** (new)', 'deploy/compose.production.yaml (new)', 'deploy/compose.sandbox.yaml (new)', 'deploy/compose.local.yaml (new)', 'src/Shared/Infrastructure/Persistence/Doctrine/TlsConnection{Middleware,Driver}.php (new)', 'tests/**']
code_patterns: ['multi-stage build, build stage on $BUILDPLATFORM', 'images pinned by digest', 'Doctrine driver middleware']
test_patterns: ['unit with a mocked DBAL driver', 'local image build and boot of the production Compose file']
---

# Tech-Spec: Production images and deploy files

**Created:** 2026-11-04

## Overview

### Problem Statement

The only image is the dev one (`docker/php-fpm/Dockerfile`: Xdebug, Composer, code bind-mounted). The deployment (guide, phases 10 to 12) builds two ARM64 images once per commit, pushes them to ECR and starts them by digest from `deploy/compose.sandbox.yaml` (STAGING, DEMO) and `deploy/compose.production.yaml`, with the secrets written at deploy time to `.env.runtime` and `./jwt`.

### Solution

A root `Dockerfile` with three targets — `php`, `nginx`, `sandbox` — and the two Compose files of phase 10, plus a workstation override to boot the production file locally. TLS to RDS through a Doctrine middleware switched on by `DATABASE_SSL_CA`, set in production only. The dev `compose.yaml`, its images and the `make` targets are untouched.

### Scope

**In Scope:**
- `php`: PHP 8.5.12-fpm (bookworm, as the dev image: same ICU), extensions `bcmath intl pcntl pdo_mysql zip` (OPcache built in), `php.ini-production` + `kiveto.ini`, FPM pool, healthcheck script, RDS CA bundle, `composer install --no-dev --classmap-authoritative`, `assets:install`, `importmap:install`, `tailwind:build --minify`, `asset-map:compile` (the steps of `make assets`), `cache:warmup`, preloading, `storage/pharma-registry/`, runs as `www-data`.
- `nginx`: the compiled `public/`, the token-masking log format of the dev configuration, every host on 8080.
- `sandbox`: `php` + the dev dependencies and `fixtures/`, with `kiveto-seed` (Foundry stories then `app:search:reindex`, STAGING and DEMO only).
- `deploy/compose.production.yaml`, `deploy/compose.sandbox.yaml`, `deploy/compose.local.yaml`.
- `DATABASE_SSL_CA` and `TlsConnectionMiddleware`; `.env.prod`: no dev JWT passphrase, public URLs derived from `KIVETO_DOMAIN`.

**Out of Scope:**
- The GitHub workflows (`ci.yml`, `staging.yml`, `deploy-demo.yml`, `promote.yml`), `deploy.sh`, the edge Nginx, Terraform: deployment guide, other repository or later work.
- An S3 adapter behind `FileStorageInterface`: `var/storage` stays on the `files` volume for the alpha (decision of the user, to be confirmed).
- A healthcheck of the workers: a stopped worker is restarted by Docker.

## Context for Development

### Codebase Patterns

- `make assets` = `tailwind:build --minify`, `rm -rf public/assets`, `asset-map:compile`; Composer's auto-scripts add `assets:install` and `importmap:install`.
- Symfony 7.4 keeps the `cache.app` pools (translation catalogues included) in `var/share` (`APP_SHARE_DIR`); PHP sessions use the native handler (php.ini `session.save_path`).
- The official image's `docker.conf` sets `clear_env = no` and `access.log = /proc/self/fd/2`: FPM would log every URL, tokens included.
- Foundry (`zenstruck/foundry`) is `require-dev` and its bundle is registered in `dev` and `test` only.
- Doctrine DBAL 4.4, DoctrineBundle 2.18 (`#[AsMiddleware]`); PHP 8.5 deprecates `PDO::MYSQL_ATTR_*` in favour of `Pdo\Mysql::ATTR_*`.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `Dockerfile` | `runtime`, `build`, `php`, `sandbox`, `nginx` stages |
| `docker/production/php/{kiveto.ini,zz-kiveto.conf,php-fpm-healthcheck}` | PHP, FPM pool, healthcheck |
| `docker/production/nginx/default.conf` | Nginx of the `nginx` image |
| `docker/production/sandbox/kiveto-seed` | Seeding of STAGING and DEMO |
| `deploy/compose.{production,sandbox,local}.yaml` | Deploy files and local check |
| `src/Shared/Infrastructure/Persistence/Doctrine/TlsConnectionMiddleware.php` | TLS to RDS |

### Technical Decisions

1. **Base images pinned by tag and digest** (`php:8.5.12-fpm-bookworm`, `composer:2.11.1`, `nginx:1.30.6-alpine`, `gotenberg/gotenberg:8.39.0`, latest on 2026-11-04). Bookworm like the dev image, so the tests and production share the ICU data.
2. **Build stage on `$BUILDPLATFORM`.** Vendors, Tailwind and AssetMapper produce CPU-independent files: they run natively (the Tailwind binary is not emulated), only the `runtime` stage and `cache:warmup` run for the target platform.
3. **One image for every deployment.** `cache:warmup` runs with `KIVETO_DOMAIN=kiveto.app`, read at runtime only (item 1); `APP_ENV=prod` and `APP_DEBUG=0` are set in the image; everything else comes from `.env.runtime`.
4. **No secret in an image.** `.dockerignore` excludes `config/jwt`, `.env.local`, `.env.dev`, `.env.test`, `tests/` (and its test keypair), `.git`. `.env.prod` blanks `JWT_PASSPHRASE`: on every `APP_ENV=prod` deployment the committed dev passphrase never applies, the deployment provides its own. It also derives `DEFAULT_URI`, `KIVETO_PUBLIC_BASE_URL`, `PORTAL_PUBLIC_BASE_URL` and `PORTAL_TERMS_URL` from `KIVETO_DOMAIN` (`https://clinic.${KIVETO_DOMAIN}`…): a deployment that does not set them never links to the `kiveto.local` defaults of `.env`; setting them still wins.
5. **Volumes.** `files` on `var/storage` (documents, exports, attachments: EBS snapshots and a nightly S3 copy for the alpha) and `share` on `var/share` (cache pools and sessions), both shared by PHP-FPM and the workers: a translation edited in the backoffice invalidates the catalogue the workers read, as the bind mount does in dev, and sessions survive a deployment. Both directories exist in the image, owned by `www-data`, so a new volume inherits that owner.
6. **Runs as `www-data`, code owned by root.** PHP-FPM, the workers and the console run as `www-data`, which owns only what the application writes: `var/` and `storage/` (copied with that owner, so the 104 MB registry is not duplicated by a `chown` layer). The code cannot be rewritten from a compromised process. The deploy script `chown`s `./jwt` to 33:33.
7. **PHP-FPM.** `PHP_FPM_MAX_CHILDREN` (10 in the image and production, 4 on the sandbox), dynamic pool, `request_terminate_timeout = 65s` (`max_execution_time` ignores time blocked on I/O: an unreachable database would hold workers); FPM's access log off (`/dev/null`): Nginx logs the requests with the tokens masked. OPcache without timestamp checks, the warmed container preloaded.
8. **Healthchecks in Compose, not in the image** (the workers run the image without FPM): `php-fpm-healthcheck` sends `GET /health` through FastCGI (`cgi-fcgi`), Nginx `wget http://localhost:8080/health`; Nginx starts once PHP is healthy.
9. **Nginx** resolves `php` through Docker's DNS at request time (a recreated container is found again), serves `/assets/` as immutable, refuses any PHP file but `index.php`, accepts 20 MB bodies.
10. **Workers.** The commands of the dev `compose.yaml` (`async`; `async_external async_external_notification async_external_batch`; the 9 scheduler receivers, 59 min) with `-d memory_limit=256M`, `--memory-limit=160M`, a container limit of 256 MB. They stop between two messages: `pcntl` lets `messenger:consume` handle the signal, `stop_signal: SIGTERM` replaces the image's `SIGQUIT` (PHP-FPM's graceful stop), 30 s grace. Without both, a deploy killed a worker mid-message and the Doctrine row waited for `redeliver_timeout` (1 h) before running again.
11. **`.env.runtime` read raw** (`env_file: {path, format: raw}`): one `KEY=value` per line, no quotes, no `$` interpolation, no ` #` comment, so any generated secret reaches PHP unchanged (checked locally with `a$b'c"d #e`). **The deploy script must write unquoted values** (deployment guide, phase 8: `"\(.Name | split("/") | last)=\(.Value)"`, without the single quotes); a value cannot span lines (the JWT keys are files).
12. **TLS to RDS, production only.** `DATABASE_SSL_CA` (empty by default) set to `/etc/ssl/certs/rds-global-bundle.pem`: `TlsConnectionMiddleware` (both connections) adds `Pdo\Mysql::ATTR_SSL_CA` and `ATTR_SSL_VERIFY_SERVER_CERT`; a missing file fails at connection time. Not keyed on `APP_ENV`, which is `prod` on the sandbox too, whose MySQL container has no RDS certificate.
13. **Doctrine: native lazy objects (fix found by the first build).** On master the application could not boot with `APP_ENV=prod` at all: the required cache warmer generates a lazy-ghost proxy for every entity, and the entities are `final` (« Cannot generate lazy ghost: class …\BackofficeUserEntity is final »). Dev and tests generate proxies on demand only, so it never showed. `orm.enable_native_lazy_objects: true` (PHP 8.4+, ORM 3.5.8, DoctrineBundle 2.18) replaces `enable_lazy_ghost_objects` and the proxy options (`auto_generate_proxy_classes`, `proxy_dir`, now ignored, removed): no proxy class at all, final entities allowed, the deprecated `LazyGhostTrait` no longer used. It changes how Doctrine builds lazy objects in every environment: the whole suite runs on it.
14. **Seeding.** `sandbox` target + `kiveto-seed` (refuses to run unless `KIVETO_DEPLOYMENT` is `staging` or `demo`), started through the `seed` service of `compose.sandbox.yaml` (profile `seed`, image `SANDBOX_IMAGE`). The pharmaceutical registry stays a separate one-off (`app:pharmaceutical-registry:bootstrap`, sources in the image).

## Implementation Plan

### Tasks

- [x] Task 1: `Dockerfile`, `.dockerignore`, `docker/production/**`.
- [x] Task 2: `deploy/compose.production.yaml`, `deploy/compose.sandbox.yaml`, `deploy/compose.local.yaml`.
- [x] Task 3: `DATABASE_SSL_CA` in `.env`, `TlsConnectionMiddleware` and `TlsConnectionDriver` with their unit test; `.env.prod`.
- [x] Task 3b: `config/packages/doctrine.yaml`: native lazy objects (decision 13).
- [x] Task 4: local build (`php`, `nginx`, `sandbox`; amd64 and arm64) and boot of the production file against a local MySQL.

### Acceptance Criteria

- [x] AC 1: Given the repository, when `docker buildx build --platform linux/amd64,linux/arm64 --target php` (then `nginx`, `sandbox`) runs, then the images build, without Xdebug, Composer (except `sandbox`), dev dependencies (except `sandbox`), JWT key or local env file.
- [x] AC 2: Given `deploy/compose.production.yaml` and `deploy/compose.local.yaml`, a local MySQL, `APP_ENV=prod`, `KIVETO_DOMAIN=kiveto.local`, `KIVETO_DEPLOYMENT=staging`, when the project starts with `--wait`, then every service is running or healthy and `GET /health` with `Host: portal.kiveto.local` answers 200.
- [x] AC 3: Given `DATABASE_SSL_CA` empty, then the driver is unchanged; set, then every connection carries the CA and the server verification; an unreadable file is refused.
- [x] AC 4: The dev `compose.yaml` and the `make` targets keep working unchanged.
- [x] AC 5: Given `APP_ENV=prod`, when `cache:warmup` runs, then it succeeds (it failed on master).

## Additional Context

### Dependencies

Items 1 to 3 at runtime (`KIVETO_DOMAIN`, `KIVETO_DEPLOYMENT`, `/health`): merge them first. The build itself does not need them.

### Testing Strategy

Unit: `TlsConnectionMiddlewareTest`. Local: multi-platform build of the three targets, boot of `compose.production.yaml` + `compose.local.yaml` with items 1 to 4 together, `/health` and `/health/ready` on the portal host, migrations through `compose run --rm php`. `make ci` green.

### Notes

New variables per environment:

| Variable | dev, tests | STAGING | DEMO | production |
| --- | --- | --- | --- | --- |
| `DATABASE_SSL_CA` | empty | empty | empty | `/etc/ssl/certs/rds-global-bundle.pem` |
| `PHP_FPM_MAX_CHILDREN` | (dev image) | 4 (compose) | 4 (compose) | 10 (compose) |
| `SANDBOX_IMAGE` | — | for `seed` only | for `seed` only | — |

- The CI workflow must build and push the `sandbox` target only when seeding (tag `<sha>-sandbox`, for instance).
- Reviewed independently on 2026-11-04; fixed: `pcntl` and `SIGTERM` for the workers, raw `.env.runtime`, `request_terminate_timeout`, public URLs from `KIVETO_DOMAIN`, code owned by root, Nginx healthcheck on `127.0.0.1` (`localhost` resolved to `::1`). Left open: STAGING and DEMO share `kiveto-data`, so a DEMO container can reach STAGING's FastCGI port (one data network per environment, guide phase 8); `awslogs` needs its log groups created beforehand (Terraform); the RDS bundle is fetched without `--checksum` (AWS rotates it).
- The edge Nginx may rate-limit `/health/ready`, which queries the database.
