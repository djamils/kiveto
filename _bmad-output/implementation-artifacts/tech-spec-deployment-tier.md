---
title: 'Deployment tier (KIVETO_DEPLOYMENT) and Portal test phones'
slug: 'deployment-tier'
created: '2026-11-04'
status: 'implemented'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7.4', 'PHP 8.5', 'Symfony DI env processors', 'Monolog', 'PHPUnit 12']
files_to_modify: ['src/Shared/Infrastructure/Deployment/Deployment.php (new)', 'src/System/Notification/Infrastructure/Delivery/DeliverySelector.php', 'src/System/Notification/Infrastructure/Webhook/BrevoRequestGuard.php', 'src/System/ElectronicInvoicing/Infrastructure/Platform/PlatformSelector.php', 'src/System/IdentityAccess/Application/Port/PortalTestPhonesInterface.php (new)', 'src/System/IdentityAccess/Infrastructure/Security/ConfiguredPortalTestPhones.php (new)', 'src/System/IdentityAccess/Application/Command/{RequestPortalOtp,ResendPortalOtp,RequestPortalActionOtp}/*Handler.php', 'config/services.yaml', 'config/packages/monolog.yaml', '.env', '.env.dev', '.env.test', 'src/**/README.md', 'tests/**']
code_patterns: ['enum env processor', 'runtime selector guard', 'Application port + configured Infrastructure adapter', 'dedicated Monolog channel']
test_patterns: ['unit with in-memory fakes (PortalFakes, AccountFakes)', 'data providers per deployment', 'PortalApiTestCase end-to-end sign-in']
---

# Tech-Spec: Deployment tier (KIVETO_DEPLOYMENT) and Portal test phones

**Created:** 2026-11-04

## Overview

### Problem Statement

STAGING and DEMO will run with `APP_ENV=prod`, like production. `DeliverySelector` (notifications) and `PlatformSelector` (e-invoicing) refuse their local simulators when `kernel.environment` is `prod`, so STAGING and DEMO could only send nothing — no Portal sign-in possible, the OTP never arrives — or send real SMS to the phones of synthetic data. `BrevoRequestGuard` keys its hardening on `kernel.environment` too. Testers, the owner app's device tests and the App Store / Google Play reviewers need to sign in to the Portal without receiving an SMS.

### Solution

A `KIVETO_DEPLOYMENT` variable (`dev`, `staging`, `demo`, `production`) says which deployment runs, independently of `APP_ENV`; it defaults to `production` (`.env`), and `.env.dev` / `.env.test` set `dev`. The simulator guards key on `production`; the webhook hardening keys on « every deployment but dev ». Test phone numbers (`PORTAL_TEST_PHONES`) get a fixed code (`PORTAL_TEST_OTP_CODE`) for every sign-in, invitation and action code, nothing is sent to them, and each use is logged on a channel written in production too.

### Scope

**In Scope:**
- `Deployment` enum, read with `%env(enum:…:KIVETO_DEPLOYMENT)%` (unknown value refused).
- Review of every `'prod' === $environment` check in `src/`: `DeliverySelector`, `PlatformSelector`, `BrevoRequestGuard` (the only three).
- Test phones: port, adapter, the three handlers that issue phone codes, Monolog channel, `.env` documentation, one test phone in `.env.test`.

**Out of Scope:**
- `when@prod` configuration blocks (logs in JSON on stderr, Doctrine caches, `strict_requirements`): they are about `APP_ENV`, right for STAGING and DEMO too.
- The API's rate limits (`limiter.portal_*`), `PORTAL_OTP_UNKNOWN_HOURLY_CAP` and the challenge's attempt limits: unchanged.
- `email_verification` codes: they prove an e-mail address and still go to it.
- Enforcing « one test phone at most in production »: a policy of the Parameter Store values, not of the code.

## Context for Development

### Codebase Patterns

- Selectors resolve their adapter when a port is first used, so a guard holds even if the variable changes after the image build (`DeliverySelector`, `PlatformSelector`).
- `BrevoRequestGuard` throws in its constructor (built when a webhook is parsed).
- OTP issuance: `RequestPortalOtpHandler` (login, invitation), `ResendPortalOtpHandler` (sms, email), `RequestPortalActionOtpHandler` (email_change, account_deletion), `StartPortalEmailChangeHandler` (email_verification, to the address). Codes come from `OtpCodeGeneratorInterface`; the stored value is the HMAC of the code; verification compares hashes. The SMS is requested through `NotificationRequesterInterface`.
- `UnknownNumberSmsGate` spends the unknown-number budget (network, device, global cap) before a login SMS to an unknown phone.
- Monolog in prod: `main` is `fingers_crossed` (action level `error`): a `notice` alone is never written.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Infrastructure/Deployment/Deployment.php` | The deployment tier |
| `src/System/Notification/Infrastructure/Delivery/DeliverySelector.php` | Notification simulator guard |
| `src/System/ElectronicInvoicing/Infrastructure/Platform/PlatformSelector.php` | E-invoicing simulator guard |
| `src/System/Notification/Infrastructure/Webhook/BrevoRequestGuard.php` | Webhook hardening |
| `src/System/IdentityAccess/Application/Port/PortalTestPhonesInterface.php` | Test phones port |
| `src/System/IdentityAccess/Infrastructure/Security/ConfiguredPortalTestPhones.php` | Env-configured adapter |
| `config/packages/monolog.yaml` | `portal_test_phone` channel |

### Technical Decisions

1. **Tier, not environment.** `KIVETO_DEPLOYMENT` is read through Symfony's `enum` env processor into `App\Shared\Infrastructure\Deployment\Deployment`: an unknown value fails when first read. `.env` sets `production` (fail-safe: a deployment that forgets the variable gets the strictest guards); `.env.dev` and `.env.test` set `dev`.
2. **Simulators: refused in production only.** `NOTIFICATION_DELIVERY=simulator` and `ELECTRONIC_INVOICING_PLATFORM=simulator` run on dev, STAGING and DEMO.
3. **Webhook hardening: on every deployment but dev** (review of the third check). `NOTIFICATION_WEBHOOK_SKIP_IP_CHECK` and the placeholder or empty `BREVO_WEBHOOK_TOKEN` are refused on STAGING, DEMO and production, as they are today with `APP_ENV=prod`: STAGING and DEMO are public and Brevo's IP ranges are shared by every Brevo customer. Keying it on `production` only would have relaxed STAGING and DEMO. Consequence: STAGING and DEMO need a random `BREVO_WEBHOOK_TOKEN` in Parameter Store. Reverting to `production` only is a one-line change per check.
4. **Test phones through an Application port.** `PortalTestPhonesInterface::fixedCodeFor(PhoneNumber, OtpPurpose): ?string`; the handlers use the fixed code instead of a generated one and send nothing when it is not null. Verification is untouched (same HMAC comparison, same attempts, same expiry).
5. **No budget spent.** A test phone's login request skips `UnknownNumberSmsGate`: no SMS leaves, so there is no SMS budget to protect, and an exhausted budget never locks the testers out. The coverage check (`MobileNumberPolicy`) still applies, before the fixed code is handed out; the API's per-phone, per-IP and per-device quotas are consumed by the Presentation layer as before.
6. **Every channel, every purpose but `email_verification`.** Login, invitation and action codes, requested or resent by SMS or by e-mail: fixed code, nothing delivered.
7. **Validation.** Numbers parsed with `PhoneNumber::fromString` (E.164, spaces allowed); once one number is listed, `PORTAL_TEST_OTP_CODE` must be exactly six digits, else `LogicException` when the adapter is built.
8. **Audit log.** Each request of a fixed code: `notice` « Fixed code requested for a Portal test phone; nothing is sent. » with `phone` and `purpose`, on channel `portal_test_phone`, which has its own prod handler (stderr, JSON, level `notice`) and is excluded from `main`.

## Implementation Plan

### Tasks

- [x] Task 1: `Deployment` enum; `DeliverySelector` and `PlatformSelector` take a `Deployment` (services.yaml: `%env(enum:…:KIVETO_DEPLOYMENT)%`) and refuse their simulator in production.
- [x] Task 2: `BrevoRequestGuard` takes the `Deployment` (autowired env) and refuses the dev tunnel flag and the placeholder secret outside dev.
- [x] Task 3: `.env` (`KIVETO_DEPLOYMENT=production`, `PORTAL_TEST_PHONES=`, `PORTAL_TEST_OTP_CODE=` documented), `.env.dev` and `.env.test` (`dev`; one test phone in tests).
- [x] Task 4: `PortalTestPhonesInterface`, `ConfiguredPortalTestPhones`, the three handlers, Monolog channel and handler.
- [x] Task 5: tests (selectors and guard per deployment, adapter, handlers, end-to-end sign-in) and READMEs (Notification, ElectronicInvoicing, Shared, IdentityAccess).

### Acceptance Criteria

- [x] AC 1: Given `KIVETO_DEPLOYMENT` dev, staging or demo, when the notification or e-invoicing simulator is selected, then it runs; given `production`, then it is refused (`LogicException`).
- [x] AC 2: Given staging, demo or production, when `NOTIFICATION_WEBHOOK_SKIP_IP_CHECK=1` or a placeholder / empty `BREVO_WEBHOOK_TOKEN`, then the webhook guard refuses to run; given dev, then both are allowed.
- [x] AC 3: Given `KIVETO_DEPLOYMENT` unset, then the deployment is production; given an unknown value, then reading it fails.
- [x] AC 4: Given a test phone, when a sign-in, invitation or action code is requested or resent (SMS or e-mail), then the challenge holds the fixed code, no SMS or e-mail is requested and no unknown-number budget is spent, even exhausted.
- [x] AC 5: Given a test phone outside the Portal coverage, when a sign-in code is requested, then `PhoneNotSupportedException` and no fixed code is handed out.
- [x] AC 6: Given the test phone of `.env.test` with an account, when it signs in through the API, then no SMS is requested, a wrong code is `otp_invalid`, and the fixed code authenticates.
- [x] AC 7: Given any other phone, then nothing changes (random code, SMS, budget).
- [x] AC 8: Given a listed number with a code that is not six digits, or a number that is not E.164, then the adapter refuses to be built.
- [x] AC 9: Each request of a fixed code is logged on `portal_test_phone`, written in prod.

## Additional Context

### Dependencies

None. Item 4 (production image) sets `KIVETO_DEPLOYMENT` in `.env.runtime`.

### Testing Strategy

Unit: `PlatformSelectorTest`, `DeliveryAdaptersTest`, `BrevoRequestGuardTest` per deployment; `ConfiguredPortalTestPhonesTest`; `PortalTestPhonesTest` (handlers, with `PortalFakes` as the test-phones double); the existing OTP tests construct the handlers with no test phone. Integration: `TestPhoneSignInTest`. `make ci` green.

### Notes

Values per environment:

| Variable | dev, tests | STAGING | DEMO | production |
| --- | --- | --- | --- | --- |
| `KIVETO_DEPLOYMENT` | `dev` | `staging` | `demo` | `production` |
| `NOTIFICATION_DELIVERY` | `simulator` | `simulator` | `simulator` | `brevo` |
| `ELECTRONIC_INVOICING_PLATFORM` | `simulator` | `simulator` | `simulator` | `disabled` |
| `BREVO_WEBHOOK_TOKEN` | `change-me` allowed | random | random | Brevo's secret |
| `PORTAL_TEST_PHONES` | empty (tests: `+33639980001`) | testers' and device-test numbers | same as STAGING or its own | the review account's number only |
| `PORTAL_TEST_OTP_CODE` | empty (tests: `424242`) | six digits | six digits | six digits, given to the stores only |

The ARCEP fiction range `06 39 98 xx xx` (to be checked before use) keeps test phones away from real subscribers.
