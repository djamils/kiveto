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

## Domain Events

- `UserRegistered` — emitted when a new user account is created via `RegisterUser` command.

## Known Security Debt

The following security features are intentionally out of scope for the current phase and documented here as known debt:

- **2FA** — no second factor authentication is implemented. Planned for Phase 3.
- **Account lockout** — no brute-force protection (failed attempt counter, temporary lock). Planned for Phase 3.
- **Password reset flow** — token-based reset exists but has not been security-audited.
