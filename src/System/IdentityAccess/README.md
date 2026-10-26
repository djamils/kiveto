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

Query `GetInvitationStatuses(clinicId, userIds)` answers `PENDING_VALID`, `PENDING_EXPIRED` or `ACTIVE` per user (the clinic team page, through a Clinic port).

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
