---
title: 'Hosts configurable per deployment (KIVETO_DOMAIN)'
slug: 'deployment-hosts'
created: '2026-11-04'
status: 'implemented'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7.4', 'PHP 8.5', 'API Platform 4', 'Symfony Routing', 'Symfony Security', 'PHPUnit 12']
files_to_modify: ['src/Kernel.php', 'src/Shared/Infrastructure/Routing/KivetoDomainEnvVarProcessor.php (new)', 'config/services.yaml', 'config/routes/{api,backoffice,clinic,portal}.yaml', 'config/packages/framework.yaml', 'config/packages/security.yaml', 'src/System/Translation/Infrastructure/Resolver/HostnameAppScopeResolver.php', 'src/Presentation/Clinic/Controller/{Dashboard,Auth}/*Controller.php', 'src/Presentation/Backoffice/Controller/*Controller.php', '.env', '.env.dev', '.env.test', 'tests/**']
code_patterns: ['host-bound route imports', 'router request context parameter', 'custom env var processor', 'container parameters reused in security and framework config']
test_patterns: ['unit with data providers', 'WebTestCase on absolute URLs of several hosts']
---

# Tech-Spec: Hosts configurable per deployment (KIVETO_DOMAIN)

**Created:** 2026-11-04

## Overview

### Problem Statement

Kiveto is about to be deployed on kiveto.app: `clinic.`, `portal.` and `backoffice.kiveto.app` in production, `<host>.staging.kiveto.app` and `<host>.demo.kiveto.app` on the sandbox, from one image built once and promoted by digest. Every host is hard-coded to `*.kiveto.local`: in the four route imports of `config/routes/`, in 10 class-level `#[Route(host: …)]` attributes, in `framework.trusted_hosts`, in the firewalls and `access_control` of `security.yaml`, and in `HostnameAppScopeResolver`. Symfony refuses `%env()%` in routing configuration, and a container parameter there would be compiled into the route cache at image build time, i.e. baked for one environment.

### Solution

A `KIVETO_DOMAIN` variable names the domain of the deployment: `kiveto.local` (dev, tests), `staging.kiveto.app`, `demo.kiveto.app`, `kiveto.app`. The routes are bound to `<app>.{kiveto_domain}`, a host placeholder whose requirement is a static alternation of those four domains (`kiveto.domain_pattern`), so the compiled matcher is the same for every deployment. The Kernel adds `kiveto_domain` to the router's request context from the environment, read at runtime: URL generation needs no parameter and works in requests, workers and the CLI. The same static patterns guard `trusted_hosts`, the firewalls and the access rules. The scope resolver reads the variable. An unknown or missing value fails loudly instead of generating the URLs of another deployment.

### Scope

**In Scope:**
- `KIVETO_DOMAIN`, its validation (env var processor `kiveto_domain:`), its values in `.env.dev` and `.env.test`, its documentation in `.env`.
- Route imports, router context, trusted hosts, firewalls, access rules, translation scope resolver.
- Removal of the 10 class-level `host:` attributes, which the imports overwrite anyway.

**Out of Scope:**
- `hooks.kiveto.app` (already configurable: `NOTIFICATION_WEBHOOK_HOST`) and `mail.kiveto.app` (`NOTIFICATION_EMAIL_DOMAIN`).
- The public base URLs used in e-mails and links (`KIVETO_PUBLIC_BASE_URL`, `PORTAL_PUBLIC_BASE_URL`, `PORTAL_TERMS_URL`, `DEFAULT_URI`): already variables, set per environment by the deployment.
- The dev Nginx `server_name` and the `Makefile` host variables: dev only, unchanged.
- Pinning `trusted_hosts` to the single domain of the deployment: the edge Nginx of each machine only forwards its own hosts (deployment guide, phase 8), and the static patterns keep one cache for every deployment.

## Context for Development

### Codebase Patterns

- Routes are imported per application in `config/routes/{clinic,portal,backoffice,api}.yaml`; an import's `host:` overwrites the host of every route it loads (`HostTrait::addHost`), so the class-level `#[Route(path: '', host: …)]` attributes were dead configuration.
- `Symfony\Bundle\FrameworkBundle\Routing\Router::resolve()` throws « Using "%env(…)%" is not allowed in routing configuration ». Container parameters are allowed but resolved when the route collection is compiled into `var/cache/<env>/url_*_routes.php`.
- `UrlGenerator::generate()` merges the context parameters into the route variables (`array_replace($defaults, $context->getParameters(), $parameters)`); FrameworkBundle already sets `_locale` on `router.request_context` the same way.
- `framework.trusted_hosts` and the security `host:` options accept container parameters (resolved at compile time). `when@test` adds `kiveto.test`, `kiveto.local` and `example` hosts for the tests.
- `config/routing.yaml` sets `strict_requirements: null` in prod: a generated host that does not meet the requirement would not be refused at generation time, hence the validation of the variable itself.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `config/routes/*.yaml` | Host-bound route imports |
| `config/packages/security.yaml` | Firewalls and access rules per host |
| `config/packages/framework.yaml` | `trusted_hosts` |
| `config/services.yaml` (parameters) | `kiveto.domain_pattern`, `kiveto.host_pattern.*` |
| `src/Kernel.php` | Router context parameter |
| `src/Shared/Infrastructure/Routing/KivetoDomainEnvVarProcessor.php` | Validation of `KIVETO_DOMAIN` |
| `src/System/Translation/Infrastructure/Resolver/HostnameAppScopeResolver.php` | Scope from the host |

### Technical Decisions

1. **Spike (done, 2026-11-04).** `host: 'portal.%env(KIVETO_DOMAIN)%'` fails: « Using "%env(KIVETO_DOMAIN)%" is not allowed in routing configuration ». With `portal.{kiveto_domain}`, a prod cache warmed with `KIVETO_DOMAIN=kiveto.app` then run with `KIVETO_DOMAIN=staging.kiveto.app` generates `http://clinic.staging.kiveto.app/dashboard` from the CLI, answers 200 on `portal.staging.kiveto.app/.well-known/assetlinks.json`, 401 on `portal.staging…/api/portal/v1/me`, 404 on `clinic.staging…/api/portal/v1/me`, 400 on an unknown host. One image serves every environment.
2. **Static domain list.** `kiveto.domain_pattern: 'kiveto\.local|staging\.kiveto\.app|demo\.kiveto\.app|kiveto\.app'` in `config/services.yaml`, the single source for the route requirement, `trusted_hosts` (`^(?:clinic|portal|backoffice)\.(?:…)(:\d+)?$`) and `kiveto.host_pattern.{clinic,portal,backoffice}` used by the firewalls and access rules. A new environment means a new alternative in this list.
3. **Router context from the environment.** `Kernel::process()` adds `setParameter('kiveto_domain', '%env(kiveto_domain:KIVETO_DOMAIN)%')` to `router.request_context`. The value is resolved at runtime, never dumped into the cache. Requests on another Kiveto domain still match (static requirement) but URLs are always generated for the deployment's own domain.
4. **Fail loudly.** The env var processor `kiveto_domain:` refuses any value outside `kiveto.domain_pattern` (anchored, `D` modifier: no trailing newline). `KIVETO_DOMAIN` has no default in `.env`: it is set in `.env.dev` and `.env.test`, and by the deployment (`.env.runtime`) elsewhere, so a deployment that forgets it fails at its first request (`EnvNotFoundException`) instead of serving links to `kiveto.local`. Consequence for the image build (item 4): `cache:warmup` instantiates the router, so it runs with any valid `KIVETO_DOMAIN`; the value is not baked.
5. **Scope resolver.** `HostnameAppScopeResolver(RequestStack, string $domain)` maps `clinic|portal|backoffice` + `.<domain>` to their scope; a host of another domain is `SHARED`.

## Implementation Plan

### Tasks

- [x] Task 1: `KivetoDomainEnvVarProcessor` (`src/Shared/Infrastructure/Routing/`) with its unit test.
- [x] Task 2: parameters `kiveto.domain_pattern` and `kiveto.host_pattern.*` in `config/services.yaml`; `Kernel::process()` sets the context parameter.
- [x] Task 3: the four route imports on `<app>.{kiveto_domain}` with the requirement; class-level `host:` attributes removed from the 10 controllers. `debug:router`: 362 routes before and after, same paths, hosts mapped one to one.
- [x] Task 4: `trusted_hosts`, firewalls and access rules on the static patterns.
- [x] Task 5: `HostnameAppScopeResolver` reads `KIVETO_DOMAIN`; unit test rewritten with every domain.
- [x] Task 6: `.env` documentation, `KIVETO_DOMAIN=kiveto.local` in `.env.dev` and `.env.test`.
- [x] Task 7: integration test `tests/Integration/Shared/Infrastructure/Routing/DeploymentHostsTest.php`.

### Acceptance Criteria

- [x] AC 1: Given `KIVETO_DOMAIN=kiveto.local`, when a route of the clinic or backoffice host is generated as an absolute URL, then its host is `clinic.kiveto.local` / `backoffice.kiveto.local`.
- [x] AC 2: Given the portal host of any of the four domains, when `GET /api/portal/v1/legal/terms/current` is sent, then it answers 200, and `GET /api/portal/v1/me` without a token answers 401 (the firewall follows the host).
- [x] AC 3: Given `clinic.staging.kiveto.app`, when a Portal API path is requested, then it answers 404.
- [x] AC 4: Given a host outside the Kiveto domains (`portal.kiveto.invalid`), when it is requested, then it answers 400 (untrusted host).
- [x] AC 5: Given a prod cache warmed with one domain, when the application runs with another, then URLs are generated for the second (spike, decision 1).
- [x] AC 6: Given `KIVETO_DOMAIN` unset, empty or not a Kiveto domain, when it is first read, then the application fails with an explicit message.
- [x] AC 7: The dev stack (`compose.yaml`, `make start`, `Host: portal.kiveto.local` sent by the mobile app) keeps working unchanged.

## Additional Context

### Dependencies

None. Item 4 (production image) relies on decision 4 for `cache:warmup`.

### Testing Strategy

Unit: the env var processor (every domain, every invalid value), the scope resolver (every domain, other hosts, no request). Integration: `DeploymentHostsTest` (generation, matching on the four portal hosts, firewall, wrong application host, untrusted host). The existing integration tests keep using `*.kiveto.local`. `make ci` green.

### Notes

- Values per environment: dev and tests `kiveto.local`, STAGING `staging.kiveto.app`, DEMO `demo.kiveto.app`, production `kiveto.app` (`/kiveto/<env>/KIVETO_DOMAIN` in Parameter Store).
- The routes still match a request carrying the host of another Kiveto domain; only the edge Nginx of each machine decides which hosts reach it.
