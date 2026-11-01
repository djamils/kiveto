# IdentityAccess System Context

The **IdentityAccess** system context manages user identities, authentication, and credential lifecycle for all three application scopes (Backoffice, VetApp/Clinic, Portal).

## Ubiquitous Language

- **User** — the abstract aggregate root representing any authenticated principal in the system.
- **BackofficeUser** — a platform-level administrator with no clinic affiliation.
- **ClinicUser** — a practitioner or staff member affiliated with one or more clinics via the Clinic BC.
- **PortalUser** — a pet owner of the client Portal (mobile app). Identified by their **mobile phone** (E.164, required, verified by a one-time code), no password; the e-mail is optional — copied unverified from a clinic's client file, or proven by the owner with a code (`emailVerifiedAt`, see « Portal e-mail change »). Name and address live in ClientPortal's owner profile, not here.
- **AuthenticationContext** — the application scope the login attempt is made against (`BACKOFFICE | CLINIC | PORTAL`). Addresses are looked up within the context's account type only: an account of another type is unknown here (`InvalidCredentialsException`).
- **OtpChallenge** — a one-time code sent by SMS to prove a phone (or by e-mail to prove an address), for one purpose: `login` (bound to nothing), `invitation` (bound to an invitation id), or the action codes `email_change`, `account_deletion`, `email_verification` (bound to a user id; `email_verification` also carries the `target_email` it proves).
- **Onboarding grant** — a 15-minute token carried by a verified login challenge, with a purpose (`onboarding_purpose`): `invitation` (the phone has pending Portal invitations; it accepts them without a second code) or `registration` (the phone has neither account nor invitation; it creates the owner account once).
- **Refresh token family** — the chain of refresh tokens born from one sign-in; any theft signal revokes the whole family.

## User Entity Sub-types

`UserEntity` uses Doctrine `SINGLE_TABLE` inheritance. The discriminator column determines the sub-type at hydration time:

| Sub-class | Discriminator | Description |
|-----------|--------------|-------------|
| `BackofficeUserEntity` | `backoffice` | Platform admins; no clinic FK |
| `ClinicUserEntity` | `clinic` | Veterinary staff; holds clinic membership references |
| `PortalUserEntity` | `portal` | Pet owners; phone-based, linked to clinic clients by ClientPortal |

**Uniqueness is per account type** (D1): unique keys `(type, email)` and `(type, phone)` replace the former global e-mail key — a veterinarian may also own a pet with the same address. `User::email()` is nullable; staff paths use `requireEmail()` (`EmailRequiredException`). Creation rules live in the named constructors only (`register`/`invite` require an e-mail and refuse PORTAL; `registerPortal(id, phone, ?email, at)` creates an ACTIVE owner with `phoneVerifiedAt`, event `PortalUserRegistered`); `reconstitute()` checks nothing, so legacy rows load — the migration disabled the phone-less PORTAL rows of the e-mail era. `SecurityUser::getUserIdentifier()` is the e-mail for staff and the **id** for owners. The table of the discriminator still reads `PORTAL`, `CLINIC`, `BACKOFFICE` (upper case).

New owner accounts are inserted with SQL (`addPortalIfPhoneFree`, `INSERT … ON DUPLICATE KEY UPDATE id = id`): a duplicate phone or owner e-mail is a no-op, never a closed EntityManager. Concurrent claims of one phone queue on `lockPortalPhone()`, an upsert in `identity_access__portal_phone_locks` whose row lock lasts until the transaction ends.

## Authentication Flow

1. The Symfony `ContextAuthenticator` extracts credentials from the login form and the current application scope.
2. It dispatches `AuthenticateUserQuery(email, password, context)` via the query bus.
3. `AuthenticateUserHandler` loads the user by email, verifies the password hash, checks account status (`ACTIVE` required), and enforces context matching.
4. On success it returns `AuthenticatedUser` (user ID, roles, display name). On failure it throws a typed exception (`InvalidCredentialsException`, `AccountStatusNotAllowedException`, `AuthenticationContextMismatchException`).
5. The Symfony security system is then satisfied by the `UserProvider` which hydrates the security token from the returned `AuthenticatedUser`.

## Sign-in trace

Every successful sign-in (`LoginSuccessEvent`, any firewall) dispatches `RecordUserLogin`, which stamps the account's `last_login_at` through `UserRepositoryInterface::recordLogin()`. It is a trace of use kept out of the aggregate; the clinic team page reads it to tell who has started using their access and when they were last seen.

## Portal sign-in (one-time codes)

Table `identity_access__otp_challenges` (`OtpChallenge`, DBAL repository):

- 6 digits, valid **10 min**, **5 attempts**, a resend after **30 s** (new code, attempts reset, `send_count++`), used once. The code is stored as `HMAC-SHA256(code, kernel.secret)` (`OtpCodeHasherInterface`): a plain digest of 10⁶ values would be reversible.
- `verify()` never throws: a wrong code (or a code of another purpose / invitation) counts an attempt and is **committed** — the use cases return outcomes instead of throwing (`LoginOtpOutcome`, `RefreshResult`). A consumed challenge re-presented with the right code before its original expiry answers `already_verified` (honoured by invitation acceptances only, to make them retryable); wrong guesses on a consumed challenge still count, and no replay is honoured once the 5 attempts are spent.
- `RequestPortalOtp(phone, purpose, ?invitationRef, ?requestContext)`: sign-in codes only go to numbers of Client's `MobileNumberPolicy` (`IsPortalMobileNumber`, `PhoneNotSupportedException`). For a phone with neither an active owner account nor a pending invitation, a code spends the **unknown-number budget** (`UnknownNumberSmsBudgetInterface`), three hourly layers checked in order, the first exhausted one stopping there: the client's network `limiter.portal_otp_unknown_network` (20, keyed `a.b.c.0/24` in IPv4 — an IPv4-mapped IPv6 counts as IPv4 — or the first 64 bits in IPv6), the device hint `limiter.portal_otp_unknown_device` (5, keyed by the optional `X-Device-Id` header, only when present: forgeable, never the sole guard), then the global cap `limiter.portal_otp_unknown_global` (`PORTAL_OTP_UNKNOWN_HOURLY_CAP`, 500). The IP and the device id come from `OtpRequestContext`, filled by the API; without it only the global cap applies. Beyond any layer the challenge is created with `sms_suppressed` — same answer, no SMS now or on any resend; the global cap also logs a `critical` on channel `security` once per hour (`limiter.portal_otp_unknown_alert`). Invitation codes never spend that budget. The SMS (`PORTAL_OTP`, key `otp:<challengeId>:<sendCount>`) is requested last in the transaction.
- `ResendPortalOtp(challengeId, channel)` (`sms` or `email`, see below), `GetOtpChallengePhone(challengeId)` (lets the API answer 404 and key its quotas first), `VerifyPortalLoginOtp(challengeId, code)` → `authenticated` (active owner) | `invitation_pending` | `registration_required` (with a `registration` grant, see « Portal registration ») | `account_disabled` | `otp_invalid` | `otp_expired` | `otp_attempts_exceeded`. No answer tells whether a phone has an account before its code is proven.
- The API's per-phone / per-IP quotas are database-backed rate limiters (`limiter.portal_*`, table `shared__rate_limiter_cache`), consumed by the Presentation layer before dispatching. All Portal limiters (the unknown-number budget included, spent inside `RequestPortalOtp`) go through the dedicated DBAL connection `rate_limiter`, in autocommit: a counter is never read from a transaction's stale snapshot nor kept locked until a command commits.

## Portal action codes and e-mail change

**Subjects.** The purpose fixes what a challenge is bound to (`subject_type`, `subject_ref` of `identity_access__otp_challenges`): `invitation` → the invitation id, the action purposes → the user id, `login` → nothing. `OtpChallenge::verify(…, expectedPurpose, expectedSubjectRef)` compares purpose and subject: the code of user A presented by user B is a wrong attempt (never `attempts_exceeded` for B).

Commands reusable by any action of a signed-in owner (the account deletion uses them with `account_deletion`):

| Command | Answer | What it does |
|---|---|---|
| `RequestPortalActionOtp(string $userId, string $purpose)` | `OtpChallengeIssued` | `purpose` ∈ `email_change`, `account_deletion` (else `\InvalidArgumentException`). SMS to the account's phone (no coverage check, no unknown-number budget), challenge bound to the user, wording per purpose (`PortalOtpRequest::$purpose`: « Code de confirmation de changement d'e-mail… », « Code de confirmation de suppression de votre compte Kiveto : … »). Not an ACTIVE owner account with a phone → `PortalAccountUnavailableException`. The API consumes `portal_otp_phone`, `portal_otp_phone_daily` and `portal_otp_ip` first. |
| `VerifyPortalActionOtp(string $userId, string $challengeId, string $code, string $purpose)` | `ActionOtpResult` (`VERIFIED`, `OTP_INVALID`, `OTP_EXPIRED`, `OTP_ATTEMPTS_EXCEEDED`) | Same action purposes. Never throws on a wrong code (the attempt is committed); unknown challenge, other user, other purpose and a replay of a used code are `OTP_INVALID`. A verified code is consumed. Built on `Application\Service\ActionOtpVerifier`, which the e-mail commands share. |
| `StartPortalEmailChange(string $userId, string $challengeId, string $code, string $email)` | `EmailChangeStarted{result: ActionOtpResult, ?verificationChallengeId, ?expiresAt}` | The `email_change` SMS code is verified, then an `email_verification` challenge (bound to the user, `target_email` = the address trimmed and lower-cased, 10 min, 5 attempts) sends a code to the address (`PORTAL_EMAIL_VERIFICATION`). An invalid address (> 180 characters, refused by `FILTER_VALIDATE_EMAIL`) throws `\InvalidArgumentException` before the code is spent. Also re-verifies the current unverified address. No collision is checked here. |
| `ConfirmPortalEmailChange(string $userId, string $challengeId, string $code)` | `EmailChangeResult` (`CONFIRMED`, `OTP_INVALID`, `OTP_EXPIRED`, `OTP_ATTEMPTS_EXCEEDED`, `EMAIL_TAKEN`) | Verifies the e-mail code, then locks the caller and the PORTAL account holding the address (`findByEmailAndTypeForUpdate`): a holder that **verified** it keeps it (`EMAIL_TAKEN`, the code stays used); one that merely had it loses it (`User::releaseUnverifiedEmail()`); the caller gets it (`User::confirmEmail(email, at)`). A previous **verified** address of the caller, other than the new one, receives `PORTAL_EMAIL_CHANGED_NOTICE` (new address masked, key `email-changed:<challengeId>`). |

**A verified e-mail is a sign-in factor (D6).** This reverses the slice-1 rule « no e-mail channel » (D18): `ResendPortalOtp(challengeId, 'email')` sends a **login** code (`PORTAL_OTP` by e-mail, same key space `otp:<challengeId>:<sendCount>`) to the verified address of the phone's ACTIVE owner account. Anything else — no verified address, an action code — and any resend of an `email_verification` code (it must reach the address it proves, never the phone) leaves the challenge untouched, the current code still valid, and answers like a resend (fresh `expiresAt` / `resendAvailableAt`, the 30 s delay enforced by `OtpChallenge::assertResendable()`): the answer tells nothing about the account. Action codes are resent by SMS with their wording.

## Portal account deletion

ClientPortal orchestrates the deletion (`RequestAccountDeletion`, then the
`async` `DeletePortalAccount`); IdentityAccess answers with three commands,
run in the caller's transaction:

| Command | Answer | What it does |
|---|---|---|
| `DisablePortalUser(userId)` | — | After the `account_deletion` code is verified (`VerifyPortalActionOtp`): `User::disablePortal()` (DISABLED) and `RefreshTokenRepositoryInterface::revokeAllForUser(userId, at)`. `PortalApiUserProvider` (through `GetPortalAccount`) refuses the account's access tokens at once, `RefreshPortalSession` its refresh tokens. Not an ACTIVE owner account → `PortalAccountUnavailableException`. |
| `PurgePortalPhoneTraces(userId)` | `PurgedPortalIdentity{?phone, ?email}` | First step of the erasure. Reads the phone, takes its lock row (`lockPortalPhone`, the claims' order: phone, then account), then deletes every challenge sent to the phone or bound to the user (`deleteForPhoneOrUser`, onboarding grants included), resets the phone's quotas `portal_otp_phone` / `portal_otp_phone_daily` (port `PortalOtpQuotasInterface`) and deletes the user's `shared__idempotency_keys` (`IdempotencyStoreInterface::forgetPrincipal`). An anonymised account has no phone: only the idempotency keys are deleted. Not a DISABLED owner account → `\LogicException`. |
| `AnonymisePortalUser(userId)` | — | Last step. `User::anonymisePortal(at)`: phone, e-mail and their verification dates NULL, `anonymised_at` set (DISABLED required, done once); then the phone's lock row is deleted (`deletePortalPhoneLock`). |

An anonymised owner row stays (other contexts reference its id) and stays
DISABLED, like the phone-less owner rows of the migration
`Version20261029120000`. Its phone is free: a claim of that phone finds no
account and creates a new one.

## Portal onboarding (invitations)

- **Onboarding grant**: when a sign-in code is verified for a phone with pending invitations (ClientPortal, through `PendingPortalInvitationsInterface` → `ListPendingInvitationsForPhone`) and its account is not disabled, `VerifyPortalLoginOtp` gives the challenge its one grant (`grantOnboarding(hash, invitation, now)`, 15 min, only the digest is stored) in the same transaction and answers it in clear: `invitation_pending` (no account yet) or `authenticated` with the invitations. No command mints a grant on its own. `findByOnboardingHashForUpdate(hash, purpose)` only finds a grant of that purpose: `ClaimPortalIdentity` accepts `invitation` grants only. A phone with a pending invitation counts as known for the unknown-number SMS budget.
- `ClaimPortalIdentity(invitationRef, expectedPhone, challengeId+code | onboardingToken, ?email, createIfMissing)` — called by ClientPortal inside its acceptance transaction. It verifies the proof itself (an invitation code bound to that invitation, or a live grant whose phone is the invitation's: `phone_mismatch` otherwise), takes the per-phone mutex, then answers the ACTIVE owner account of the phone, refuses a disabled one (`account_disabled`), or creates one with the client's e-mail unless another owner holds it (families). An invitation code re-presented before its original expiry is `already_verified` (retries after a lost response). With `createIfMissing` false it never creates anything.
- `RevokeOnboardingGrantsForPhone(phone)` — every live `invitation` grant of the phone (a `registration` grant is left usable), once its last pending invitation is accepted.

## Portal registration (organic arrival)

A phone with neither account nor invitation is answered `registration_required` with a **registration grant** (`grantOnboarding(hash, registration, now)`, 15 min, columns `onboarding_purpose` and `onboarding_consumed_at`). The API's `POST /auth/register` runs ClientPortal's `CompletePortalRegistration`, which calls, in its transaction:

- `RegisterPortalUser(registrationToken, ?email)` → `PortalUserRegistration{result, ?userId}`. Order: the grant read `FOR UPDATE` by its digest and purpose (unknown, expired, revoked or an invitation grant → `ONBOARDING_INVALID`) → the per-phone mutex → the phone's account read past the snapshot and locked → an ACTIVE account throws `PortalAccountExistsException` (409 `account_exists`, the grant stays unused), a DISABLED one answers `ACCOUNT_DISABLED` → `consumeRegistrationGrant(now)` and the account created by `PortalAccountRegistrar` (shared with `ClaimPortalIdentity`: `addPortalIfPhoneFree`, the e-mail dropped when another owner holds it, never verified, event `PortalUserRegistered`) → `REGISTERED`.
- A grant is **single use**: once consumed, it answers `REPLAYED` with the account it created while it lives (a retry after a lost answer; the phone's challenges are deleted with an account, so a consumed grant and an active account of its phone go together), then `ONBOARDING_INVALID`.
- Two registrations of one phone queue on the mutex: the second finds the first one's account (AC 74).

## Portal sessions (Portal API)

- **Access token**: JWT signed by lexik (`LexikAccessTokenIssuer`), `sub` = owner id, `typ` = `portal`, 15 min. Keys: `make jwt-keys` (`config/jwt/`, git-ignored); tests use the committed keypair of `tests/Fixtures/Jwt/`.
- **Refresh token**: opaque 32-byte secret, only its sha256 in `identity_access__refresh_tokens`, 90 days of inactivity. `RefreshPortalSession` applies, in order: unknown / revoked / expired → refused; account no longer an ACTIVE owner → family revoked; unused → **rotated** (`rotated_at`, `rotated_to_id`); rotated < 30 s ago, successor unused, never re-issued → **one** extra pair of the same family (`grace_successor_id`); anything else → **family revoked** (`reuse_detected`). The presented row is read `FOR UPDATE`. The app must serialise its refreshes; the grace window is a safety net.
- `IssuePortalSession(userId)` opens a family; `RevokePortalSession(refreshToken, userId)` revokes it only when it belongs to the caller; `GetPortalAccount(userId)` answers the active owner (`PortalApiUserProvider` of the API firewall).

## Account lifecycle

| Status | How an account gets there |
|--------|---------------------------|
| `PENDING` | `User::invite()` — a clinic invited the person; the hash matches no password (it starts with `!`), login is refused. |
| `ACTIVE` | `User::register()` (fixtures, back office) or `User::activate(passwordHash, at)` — the invited person chose a password from the link; `emailVerifiedAt` is set (the link reached the mailbox). |
| `DISABLED` | Account switched off; it can neither log in nor be invited again. |

`User::changePassword(passwordHash, at)` (ACTIVE only) replaces the password after a reset.

## Account tokens

Single-use links sent by e-mail, table `identity_access__account_tokens` (`AccountToken`, `TokenPurpose`):

- The secret is 32 random bytes in base64url; **only its sha256 is stored** (`token_hash`, unique). Secret and digest come from Shared's `OpaqueTokenHasherInterface`, also used by refresh tokens and onboarding grants.
- `INVITATION` — 72 h, scoped to **(clinic, user)**: a new invitation of a clinic invalidates only that clinic's previous one, so two clinics inviting the same person never cancel each other.
- `PASSWORD_RESET` — 30 min; a new one, and a successful reset, invalidate the user's other reset tokens.
- A token is used once (`used_at`); invalidated tokens are marked used too.
- Issuing a link locks the user row (`SELECT … FOR UPDATE`) before invalidating the previous ones: a double click leaves one live link.

Query `GetInvitationStatuses(clinicId, userIds)` answers `PENDING_VALID`, `PENDING_EXPIRED` or `ACTIVE` per user (the clinic team page, through a Clinic port).

## Use cases

| Use case | What it does |
|----------|--------------|
| `RegisterInvitedUser(email)` | Creates the PENDING clinic account if absent and returns `InvitedUserRegistration{userId, alreadyActive}`; sends nothing. Only clinic accounts are looked up: an owner or back-office account of the same address is another account. A disabled clinic account (`AccountDisabledException`) is refused. Two invitations of the same address at once: the second joins the account the first created (no unique-key error). A membership refused afterwards leaves a PENDING account without link, reused by the next invitation. |
| `SendInvitation(userId, clinicId, inviterPrintedName, clinicName, clinicCity, inviteeFirstName, roleLabel)` | PENDING accounts only: invalidates the clinic's previous invitation, issues a new one and requests `STAFF_INVITATION` (key `invitation:<tokenId>`, link `KIVETO_PUBLIC_BASE_URL/invitation/<secret>`). Display values are resolved by the caller through Clinic queries — IdentityAccess never reads a clinic. « Renvoyer » dispatches it again. |
| `RevokeInvitation(userId, clinicId)` | The clinic disabled or ended the membership: its unused invitation links of that user stop working (Clinic's `InvitationRevokerInterface`). Returns how many. |
| `ActivateInvitedUser(token, plainPassword)` | Usable invitation, password of 8 characters or more → `activate()`, token used, `ActivationResult::ACTIVATED`. An account already ACTIVE (link of another clinic) → token used, `ALREADY_ACTIVE`. |
| `RequestPasswordReset(email)` | **Asynchronous** (`async` transport, raw `messenger.bus.command`). For an ACTIVE clinic account only: invalidates its reset tokens, issues a 30-minute one and requests `PASSWORD_RESET` (key `password-reset:<tokenId>`, « Demande reçue le … à … » Europe/Paris). Otherwise does nothing. |
| `ResetPassword(token, plainPassword)` | Usable reset token of an ACTIVE account → `changePassword()`, token and every other reset token of the user used. |
| `CheckAccountToken(token, purpose)` | `AccountTokenCheck{VALID (with the e-mail) | INVALID | ALREADY_ACTIVE}` for the public pages. |

Unusable links throw `InvalidAccountTokenException` (« Ce lien n’est plus valide. »), refused passwords `UnacceptablePasswordException`. Ports: `PasswordHasherInterface` (Symfony hasher), `AccountLinkBuilderInterface` (`KIVETO_PUBLIC_BASE_URL`), `NotificationRequesterInterface` (`CommandBusNotificationRequester` → Notification's `RequestNotification`, sender « Kiveto <no-reply@kiveto.app> »).

## Public pages (clinic host)

`/mot-de-passe-oublie`, `/reinitialiser/{token}` and `/invitation/{token}` are PUBLIC_ACCESS on the clinic host only (the « Mot de passe oublié ? » link is hidden on the portal and back-office logins). A link stores its secret in the session and redirects to the token-less address; the pages send `Referrer-Policy: no-referrer` and every form carries a CSRF token. The forgot-password form always answers « Si un compte existe pour cette adresse, un e-mail vient d’être envoyé. » and is rate limited with sliding windows (`forgot_password_ip` 5 / 15 min, `forgot_password_email` 3 / h); beyond, nothing is sent. **Production must set `SYMFONY_TRUSTED_PROXIES`** (the load balancer / reverse proxy): otherwise every request comes from the proxy's IP and the per-IP limiter becomes one global 5 / 15 min limit.

The secret kept in the session is bound to its page (one key per page) and lives 30 minutes at most (`issuedAt` stored with it); any successful sign-in and the logout drop it (`AccountSecretsCleaner`) — a shared workstation keeps no usable link. A refused form is re-rendered with the page script (`pages/auth`) and the « Pour le compte … » line.

## Sessions

`SecurityUser` carries a **password fingerprint** (first 16 hex characters of the sha256 of the password hash) and implements `EquatableInterface`. `ContextUserProvider::refreshUser()` reloads the account at every request: when the password changed elsewhere (reset), the fingerprints differ and Symfony closes the session — every other open session of the user is logged out.

## Domain Events

- `UserRegistered` — emitted when a new user account is created via `RegisterUser` command.
- `UserInvited` — a PENDING account was created for an invitation.
- `UserActivated` — the invited user chose a password; bridged to `UserActivatedIntegrationEvent` (outbox), consumed by Clinic to recount its active veterinarians (SMS quota).
- `UserPasswordChanged` — the password was replaced (reset).
- `PortalUserRegistered` — an owner account was created from a proven phone (invitation claim or registration).

## Known Security Debt

The following security features are intentionally out of scope for the current phase and documented here as known debt:

- **2FA** — no second factor authentication is implemented. Planned for Phase 3.
- **Account lockout** — no brute-force protection (failed attempt counter, temporary lock). Planned for Phase 3.
