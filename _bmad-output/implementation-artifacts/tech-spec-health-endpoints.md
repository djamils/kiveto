---
title: 'Health endpoints (/health, /health/ready)'
slug: 'health-endpoints'
created: '2026-11-04'
status: 'implemented'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7.4', 'PHP 8.5', 'Doctrine DBAL', 'Symfony Messenger', 'PHPUnit 12']
files_to_modify: ['src/Shared/Infrastructure/Health/ReadinessProbe.php (new)', 'src/Presentation/Health/Controller/{Liveness,Readiness}Controller.php (new)', 'config/routes.yaml', 'config/routes/health.yaml (new)', 'config/services.yaml', 'config/packages/security.yaml', 'tests/**']
code_patterns: ['single-action controllers', 'host-less route import', 'security: false firewall', 'receiver locator indexed by transport name']
test_patterns: ['unit with SQLite in memory and fake transports', 'WebTestCase on every host']
---

# Tech-Spec: Health endpoints (/health, /health/ready)

**Created:** 2026-11-04

## Overview

### Problem Statement

The deployment (Compose healthchecks, `deploy.sh` smoke test `https://portal.<domain>/health`, external probe) needs to know whether an instance is alive and whether it can serve. Neither `/health` nor `/health/ready` exists, and every route is bound to an application host with its firewall and access rules.

### Solution

Two public endpoints on every host: `/health` (alive: the kernel answers, no database, no session) and `/health/ready` (the database answers and every queue the workers consume is readable). They sit behind a `security: false` firewall, in a host-less route import, and answer JSON with `Cache-Control: no-store`.

### Scope

**In Scope:**
- `GET|HEAD /health` → 200 `{"status":"ok"}`.
- `GET|HEAD /health/ready` → 200 `{"status":"ok","checks":{"database":"ok","messenger":"ok"}}`, else 503 with `"status":"unavailable"` and the failed check(s) as `"fail"`.
- `ReadinessProbe`: `SELECT 1` on the default connection, then `getMessageCount()` on each consumed Messenger transport.

**Out of Scope:**
- Gotenberg, Brevo, the external APIs: a third party down must not take the instance out of service.
- The PHP-FPM container healthcheck itself (item 4, which calls `/health` through FastCGI) and the external probe (deployment guide, phase 13).
- Rate limiting the probes: the edge Nginx can restrict `/health/ready` if it is ever abused.

## Context for Development

### Codebase Patterns

- Routes are imported per host (`config/routes/*.yaml`); a host-less import serves every host, including `localhost` inside the containers (trusted host `^localhost(:\d+)?$`).
- The `webhook` firewall (`security: false`) is the model for a path served on every host without a session.
- `RequireClinicSelectionSubscriber` redirects any clinic-host route to `/login` unless whitelisted or internal (name starting with `_`, like the profiler's and the webhooks').
- Every Messenger transport with a queue is a Doctrine transport on `shared__messenger_messages` (`async`, `async_external`, `async_external_notification`, `async_external_batch`, `failed`); the `scheduler_*` transports are generated in memory and `sync` has no queue.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Infrastructure/Health/ReadinessProbe.php` | Database and queues checks |
| `src/Presentation/Health/Controller/LivenessController.php` | `/health` |
| `src/Presentation/Health/Controller/ReadinessController.php` | `/health/ready` |
| `config/routes/health.yaml` | Host-less import |
| `config/packages/security.yaml` | `health` firewall |
| `config/services.yaml` | `health.messenger_transports` |

### Technical Decisions

1. **Route names `_health`, `_health_ready`.** Internal routes by the codebase's convention, so `RequireClinicSelectionSubscriber` (and any host subscriber following it) leaves them alone on `clinic.*`; imported first in `config/routes.yaml`.
2. **Firewall `health`, `security: false`, pattern `^/health(/ready)?$`.** No token, no session, no access rule, whatever the host.
3. **Probed transports listed explicitly** (`health.messenger_transports`): the receiver locator is indexed by transport name, and probing a scheduler transport would build its schedule for nothing. `HealthEndpointsTest::testEveryQueueOfMessengerIsProbed` fails when a countable transport is added to `messenger.yaml` without being listed.
4. **Cheap.** `/health` touches nothing. `/health/ready`: one `SELECT 1` and five indexed `COUNT` queries. The queues live in the same database: when it does not answer, they are not tried (both checks fail), so an unreachable database costs one connection timeout per probe, not six; a failing queue does not stop the others from being read.
5. **No detail in the body.** The answer names the checks only (`database`, `messenger`); the cause of a failure is logged (`error`, `check`, `exception`), since the endpoints are public.

## Implementation Plan

### Tasks

- [x] Task 1: `ReadinessProbe` with its unit test (SQLite in memory, counting fake queue, non-countable transport).
- [x] Task 2: the two controllers, `config/routes/health.yaml`, the import in `config/routes.yaml`, the controllers' tag and `health.messenger_transports` in `config/services.yaml`.
- [x] Task 3: the `health` firewall.
- [x] Task 4: `HealthEndpointsTest` (every host, no session, list of probed transports).

### Acceptance Criteria

- [x] AC 1: Given the clinic, portal or backoffice host, or `localhost:8080`, when `GET /health`, then 200 `{"status":"ok"}`, `Cache-Control: no-store`, no cookie.
- [x] AC 2: Given a reachable database and queues, when `GET /health/ready` on any of those hosts, then 200 with `database` and `messenger` `ok`, no cookie.
- [x] AC 3: Given an unreachable database, when the probe runs, then both checks are `fail` (503 from the controller), no queue is tried and the cause is logged; given an unreadable queue, then `messenger` is `fail`, the other queues are still read and the cause is logged.
- [x] AC 4: Given a transport with a queue missing from `health.messenger_transports`, then the integration test fails.

## Additional Context

### Dependencies

Item 4 uses `/health` for the Compose healthchecks and `deploy.sh`.

### Testing Strategy

Unit: `ReadinessProbeTest`. Integration: `HealthEndpointsTest`. `make ci` green.

### Notes

- `/health` is the liveness probe (container healthchecks, `deploy.sh`), `/health/ready` the readiness probe (external probe, after a deploy). A database outage turns `/health/ready` to 503 without failing the containers' healthchecks, so Docker does not restart healthy PHP containers in a loop.
