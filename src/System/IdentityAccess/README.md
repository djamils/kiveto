# IdentityAccess System Context

The **IdentityAccess** system context manages user identities, authentication, and credential lifecycle for all three application scopes (Backoffice, VetApp/Clinic, Portal).

## Ubiquitous Language

- **User** — the abstract aggregate root representing any authenticated principal in the system.
- **BackofficeUser** — a platform-level administrator with no clinic affiliation.
- **ClinicUser** — a practitioner or staff member affiliated with one or more clinics via the Clinic BC.
- **PortalUser** — a pet owner accessing the client-facing portal.
- **AuthenticationContext** — the application scope the login attempt is made against (`BACKOFFICE | CLINIC | PORTAL`). Mismatches between user type and context raise `AuthenticationContextMismatchException`.

## User Entity Sub-types

`UserEntity` uses Doctrine `SINGLE_TABLE` inheritance. The discriminator column determines the sub-type at hydration time:

| Sub-class | Discriminator | Description |
|-----------|--------------|-------------|
| `BackofficeUserEntity` | `backoffice` | Platform admins; no clinic FK |
| `ClinicUserEntity` | `clinic` | Veterinary staff; holds clinic membership references |
| `PortalUserEntity` | `portal` | Pet owners; may be linked to a `ClientEntity` |

## Authentication Flow

1. The Symfony `ContextAuthenticator` extracts credentials from the login form and the current application scope.
2. It dispatches `AuthenticateUserQuery(email, password, context)` via the query bus.
3. `AuthenticateUserHandler` loads the user by email, verifies the password hash, checks account status (`ACTIVE` required), and enforces context matching.
4. On success it returns `AuthenticatedUser` (user ID, roles, display name). On failure it throws a typed exception (`InvalidCredentialsException`, `AccountStatusNotAllowedException`, `AuthenticationContextMismatchException`).
5. The Symfony security system is then satisfied by the `UserProvider` which hydrates the security token from the returned `AuthenticatedUser`.

## Sign-in trace

Every successful sign-in (`LoginSuccessEvent`, any firewall) dispatches `RecordUserLogin`, which stamps the account's `last_login_at` through `UserRepositoryInterface::recordLogin()`. It is a trace of use kept out of the aggregate; the clinic team page reads it to tell who has started using their access and when they were last seen.

## Account lifecycle

| Status | How an account gets there |
|--------|---------------------------|
| `PENDING` | `User::invite()` — a clinic invited the person; the hash matches no password (it starts with `!`), login is refused. |
| `ACTIVE` | `User::register()` (fixtures, back office) or `User::activate(passwordHash, at)` — the invited person chose a password from the link; `emailVerifiedAt` is set (the link reached the mailbox). |
| `DISABLED` | Account switched off; it can neither log in nor be invited again. |

`User::changePassword(passwordHash, at)` (ACTIVE only) replaces the password after a reset.

## Account tokens

Single-use links sent by e-mail, table `identity_access__account_tokens` (`AccountToken`, `TokenPurpose`):

- The secret is 32 random bytes in base64url; **only its sha256 is stored** (`token_hash`, unique).
- `INVITATION` — 72 h, scoped to **(clinic, user)**: a new invitation of a clinic invalidates only that clinic's previous one, so two clinics inviting the same person never cancel each other.
- `PASSWORD_RESET` — 30 min; a new one, and a successful reset, invalidate the user's other reset tokens.
- A token is used once (`used_at`); invalidated tokens are marked used too.
- Issuing a link locks the user row (`SELECT … FOR UPDATE`) before invalidating the previous ones: a double click leaves one live link.

Query `GetInvitationStatuses(clinicId, userIds)` answers `PENDING_VALID`, `PENDING_EXPIRED` or `ACTIVE` per user (the clinic team page, through a Clinic port).

## Use cases

| Use case | What it does |
|----------|--------------|
| `RegisterInvitedUser(email)` | Creates the PENDING clinic account if absent and returns `InvitedUserRegistration{userId, alreadyActive}`; sends nothing. A portal / back-office address (`EmailUsedByAnotherAccountException`) or a disabled account (`AccountDisabledException`) is refused. Two invitations of the same address at once: the second joins the account the first created (no unique-key error). A membership refused afterwards leaves a PENDING account without link, reused by the next invitation. |
| `SendInvitation(userId, clinicId, inviterPrintedName, clinicName, clinicCity, inviteeFirstName, roleLabel)` | PENDING accounts only: invalidates the clinic's previous invitation, issues a new one and requests `STAFF_INVITATION` (key `invitation:<tokenId>`, link `KIVETO_PUBLIC_BASE_URL/invitation/<secret>`). Display values are resolved by the caller through Clinic queries — IdentityAccess never reads a clinic. « Renvoyer » dispatches it again. |
| `RevokeInvitation(userId, clinicId)` | The clinic disabled or ended the membership: its unused invitation links of that user stop working (Clinic's `InvitationRevokerInterface`). Returns how many. |
| `ActivateInvitedUser(token, plainPassword)` | Usable invitation, password of 8 characters or more → `activate()`, token used, `ActivationResult::ACTIVATED`. An account already ACTIVE (link of another clinic) → token used, `ALREADY_ACTIVE`. |
| `RequestPasswordReset(email)` | **Asynchronous** (`async` transport, raw `messenger.bus.command`). For an ACTIVE clinic account only: invalidates its reset tokens, issues a 30-minute one and requests `PASSWORD_RESET` (key `password-reset:<tokenId>`, « Demande reçue le … à … » Europe/Paris). Otherwise does nothing. |
| `ResetPassword(token, plainPassword)` | Usable reset token of an ACTIVE account → `changePassword()`, token and every other reset token of the user used. |
| `CheckAccountToken(token, purpose)` | `AccountTokenCheck{VALID (with the e-mail) | INVALID | ALREADY_ACTIVE}` for the public pages. |

Unusable links throw `InvalidAccountTokenException` (« Ce lien n’est plus valide. »), refused passwords `UnacceptablePasswordException`. Ports: `PasswordHasherInterface` (Symfony hasher), `AccountLinkBuilderInterface` (`KIVETO_PUBLIC_BASE_URL`), `NotificationRequesterInterface` (`CommandBusNotificationRequester` → Notification's `RequestNotification`, sender « Kiveto <no-reply@kiveto.fr> »).

## Public pages (clinic host)

`/mot-de-passe-oublie`, `/reinitialiser/{token}` and `/invitation/{token}` are PUBLIC_ACCESS on the clinic host only (the « Mot de passe oublié ? » link is hidden on the portal and back-office logins). A link stores its secret in the session and redirects to the token-less address; the pages send `Referrer-Policy: no-referrer` and every form carries a CSRF token. The forgot-password form always answers « Si un compte existe pour cette adresse, un e-mail vient d’être envoyé. » and is rate limited with sliding windows (`forgot_password_ip` 5 / 15 min, `forgot_password_email` 3 / h); beyond, nothing is sent. **Production must set `SYMFONY_TRUSTED_PROXIES`** (the load balancer / reverse proxy): otherwise every request comes from the proxy's IP and the per-IP limiter becomes one global 5 / 15 min limit.

## Sessions

`SecurityUser` carries a **password fingerprint** (first 16 hex characters of the sha256 of the password hash) and implements `EquatableInterface`. `ContextUserProvider::refreshUser()` reloads the account at every request: when the password changed elsewhere (reset), the fingerprints differ and Symfony closes the session — every other open session of the user is logged out.

## Domain Events

- `UserRegistered` — emitted when a new user account is created via `RegisterUser` command.
- `UserInvited` — a PENDING account was created for an invitation.
- `UserActivated` — the invited user chose a password; bridged to `UserActivatedIntegrationEvent` (outbox), consumed by Clinic to recount its active veterinarians (SMS quota).
- `UserPasswordChanged` — the password was replaced (reset).

## Known Security Debt

The following security features are intentionally out of scope for the current phase and documented here as known debt:

- **2FA** — no second factor authentication is implemented. Planned for Phase 3.
- **Account lockout** — no brute-force protection (failed attempt counter, temporary lock). Planned for Phase 3.
