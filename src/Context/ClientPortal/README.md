# ClientPortal Bounded Context

## Purpose

Connects pet owners to the clinics that treat their animals. A clinic invites
one of its clients to the Portal (the owner-facing mobile app, later the web
Portal); the owner proves the client's mobile phone and accepts; from then on
the owner account (an IdentityAccess `User` of type PORTAL) reaches that
client file through a **client link**. The context also keeps the owner's
Portal **profile** (name, postal address, accepted terms of use).

## Guiding rules

- **The phone is the identity.** An invitation is bound to the client's
  mobile; it can only be accepted by proving that phone — the one-time code
  sent to it (link flow) or the onboarding grant earned by signing in with it
  (phone flow). The proof is verified by IdentityAccess (`ClaimPortalIdentity`)
  inside the acceptance transaction; ClientPortal never resolves a code or a
  grant itself.
- **One phone rule.** Only numbers accepted by Client's `MobileNumberPolicy`
  (`IsPortalMobileNumber`) can be invited — the rule the Portal API applies to
  sign-in codes, so a clinic never invites a number the app would refuse.
- **Failures that cost an attempt are results.** The acceptance commands answer
  an `AcceptOutcome` and commit, so a wrong code keeps its attempt; the only
  exception, `TermsOutdatedException`, is thrown before any write.
- **Secrets are never stored.** The invitation token is a 256-bit secret
  (`OpaqueTokenHasherInterface`); only its sha256 is kept. It travels in the
  URL fragment (`…/invitation#t=<token>`), never in a path.
- **Cross-context access through ports only**: Client (`GetClientNotificationContact`,
  `GetClientById`, `IsPortalMobileNumber`), Clinic (`GetClinic`), Animal
  (`ListAnimalSummariesPerClientIds`), IdentityAccess (`ClaimPortalIdentity`,
  `RevokeOnboardingGrantsForPhone`), Notification (`RequestNotification`).

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **PortalInvitation** | A clinic's invitation of one client: client's mobile (E.164) and e-mail at send time, delivery channel (`sms` \| `email`, link delivery only — codes always go to the mobile), token digest, `sentAt`, `expiresAt` (+7 days). Statuses `pending` / `accepted` / `revoked`; « expired » is derived (pending past `expiresAt`). |
| **Reissue** | « Renvoyer l'invitation »: a pending invitation (expired or not) keeps its id and gets a new token (the old link is dead), a new 7-day expiry and the client's current addresses. Codes and grants bound to the id stay valid. |
| **ClientLink** | Owner account ↔ clinic client. `confirmed` (access), `revoked` (access withdrawn) or `pending` (reserved for link suggestions, slice 7). One per (user, clinic, client): a revoked link is reactivated with a new `linkedAt`, never duplicated. |
| **OwnerProfile** | One per owner account: first name, last name, postal address copied from the client file of the first accepted invitation (never overwritten), plus the terms version and date of the latest acceptance. |
| **Onboarding grant** | A 15-minute token IdentityAccess hands out when a phone that has pending invitations signs in; it accepts those invitations without a second code. Revoked when the phone's last pending invitation is accepted. |
| **Portal status** (clinic sheet) | `not_invited`, `invited`, `expired`, `active`, `revoked` (« Accès retiré »). |

## Invariants

1. An invitation always has a mobile phone accepted by `MobileNumberPolicy`; the
   e-mail channel requires an e-mail.
2. At most one pending invitation per clinic client: sending again reissues it.
3. Only a pending, unexpired invitation can be accepted, and only by proving its
   phone; acceptance confirms (or reactivates) the link and creates the profile
   if absent, in one transaction — any failure after the account claim rolls
   everything back, the claim included.
4. A retry of an acceptance whose answer was lost (same invitation, same
   account proven again) answers `accepted` without writing anything.
5. The grant route checks the grant at use time: on an invitation no longer
   pending it answers `onboarding_invalid` when the grant's phone has nothing
   left to accept, `invitation_invalid` otherwise. A grant never accepts an
   invitation of another phone.
6. A disabled owner account accepts nothing: no link, no profile, the
   invitation stays pending.
7. An archived client loses its access (pending invitations revoked, links
   revoked); unarchiving restores nothing — the clinic invites again and the
   new acceptance reactivates the same account's link.

## Use Cases

| Command / Query | Bus | What it does |
|---|---|---|
| `SendPortalInvitation(clinicId, clientId, channel, actorUserId)` | command | Checks the client (`ClientNotInvitableException::reason`: `unknown_client`, `archived`, `no_mobile`, `mobile_not_supported`, `no_email`), creates or reissues the invitation, requests `PORTAL_INVITATION` last (`{clinicName, ownerFirstName, link, expiresOn}`, key `portal-invitation:<id>:<sentAt unix>`, `clientId` set so Notification journals it in the client file). Returns the invitation id. |
| `AcceptPortalInvitationWithCode(token, challengeId, code, termsVersion)` | command | Link flow. `AcceptOutcome{result, userId}`; `invitation_invalid` (unknown or replaced token), `invitation_expired`, `invitation_already_used` (revoked, or accepted by another account), the OTP results, `account_disabled`. |
| `AcceptPortalInvitationWithGrant(invitationId, onboardingToken, termsVersion)` | command | Phone flow. Same outcome; `onboarding_invalid` / `invitation_invalid` per invariant 5. |
| `RevokeClientPortalAccess(clinicId, clientId)` | command | Invariant 7 (dispatched by the `ClientArchived` consumer). |
| `GetPortalInvitationByToken(token)` | query | `PortalInvitationDetails` (clinic name and city, e-mail, phone, active pet names, expiry); `InvitationInvalidException` / `InvitationExpiredException` / `InvitationAlreadyUsedException`. |
| `ListPendingInvitationsForPhone(phone)` | query | Pending, unexpired invitations of a phone (`invitationId`, clinic name, city) — IdentityAccess's pending-invitation port. |
| `GetClientPortalStatus(clinicId, clientId)` | query | The sheet state: a confirmed link → `active`; else the pending invitation → `invited` / `expired`, unless it predates the access withdrawal → `revoked`; else `not_invited`. |
| `ListOwnerClinics(userId)` | query | Confirmed links, one entry per clinic (name, city, earliest `linkedAt`, pet names). |
| `GetOwnerProfile(userId)` | query | `?OwnerProfileDetails{firstName, lastName, address: ?{line1, line2, postalCode, city, country}, termsVersion, termsAcceptedAt}`. |
| `HasConfirmedClientLink(userId, clinicId)` | query | The Portal API's tenant guard (false for a missing, pending or revoked link). |

## Events

- `PortalInvitationSent` (`reissued` on a resend), `PortalInvitationAccepted`,
  `ClientLinkConfirmed`, `ClientLinkRevoked` — domain events.
- `ClientLinkConfirmedIntegrationEvent` (linkId, userId, clinicId, clientId) —
  bridged from `ClientLinkConfirmed`.

## Consumers

- `ClientArchivedIntegrationEventConsumer` (Client) → `RevokeClientPortalAccess`.
- Clients of this context: the Portal API (lookup, acceptances, `/me`, tenant
  guard), the clinic client sheet (send, status) and IdentityAccess
  (`ListPendingInvitationsForPhone`).

## Persistence

Raw DBAL repositories (`FOR UPDATE` reads, upserts) over schema-only Doctrine
entities: `client_portal__portal_invitations` (`token_hash` unique, index on
`phone`), `client_portal__client_links` (unique `user_id, clinic_id,
client_id`), `client_portal__owner_profiles` (PK `user_id`). Migrations in
`migrations/ClientPortal/`.

## Configuration

`PORTAL_PUBLIC_BASE_URL` (invitation links), `PORTAL_TERMS_VERSION` (the only
version an acceptance may carry).

## Fixtures

`fixtures/Context/ClientPortal/Story/ClientPortalStatesStory.php` (loaded by
the `clinic` and `portal` datasets) writes every sheet state in the Paris
clinic (Clinique Vétérinaire de Paris, `ClinicDataStory::INDEPENDENT_CLINIC_ID`):

| Client | Mobile | Sheet |
|---|---|---|
| Mme Camille Fontaine | +33611223344 | « Portal : non invité » · « Inviter au Portal » |
| M. Antoine Roussel | +33622334455 | invited yesterday · « Renvoyer l'invitation » |
| Mme Laura Perrin | +33633445566 | « Invitation expirée » · « Renvoyer l'invitation » |
| M. Julien Girard (Oslo) | +33612345678 | « Actif depuis le … » |
| Mme Élise Moreau (archived) | +33644556677 | « Accès retiré » · « Réinviter » (disabled while archived) |
| M. Bernard Giraud | landline only | disabled: no mobile |
| Mme Chloé Lambert | +33655667788 | E-mail segment disabled (no e-mail) |
| M. Lukas Weber | +4915112345678 (« mobile ») | disabled: number not supported by the Portal |

Demo phones on the Portal API:

- **+33612345678** — the demo owner account, linked to two clinics (Paris:
  Oslo; Lyon: Pixel), profile and current terms accepted.
- **+33622334455** — a pending invitation and no account: signing in shows
  the phone onboarding (`invitation_pending`). Its link token is
  `ClientPortalStatesStory::ROUSSEL_TOKEN`; `PERRIN_TOKEN` is expired.
- **+33644556677** — an account whose only link was revoked (no clinic).

**Reading the one-time code in dev**: with `NOTIFICATION_DELIVERY=simulator`
no SMS leaves; the notification worker (`external-worker`, started by `make
worker-start`) logs each SMS body on the monolog channel `dev_sms`, in
`var/log/dev.log` (the project directory is mounted in the worker
container): `grep dev_sms var/log/dev.log | tail -n 5`.
