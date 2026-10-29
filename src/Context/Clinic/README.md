# Clinic Bounded Context

The **Clinic** Bounded Context is the multi-tenant root of the platform. It manages veterinary clinics (tenants), clinic groups, and the staff who work within them.

## Ubiquitous Language

- **Clinic** — a single veterinary practice; the unit of tenancy for all other BCs.
- **ClinicGroup** — optional grouping of multiple clinics under one organisational entity.
- **ClinicMembership** — the formal relationship between a user and a clinic, carrying role, engagement type, validity window, and status.
- **StaffProfile** — professional metadata for a member (titles, registration numbers, agenda display colour, specialties).
- **ClinicMemberRole** — `MANAGER | VETERINARY | VETERINARY_ASSISTANT | RECEPTIONIST`. The clinical job, not the administrative one.
- **Letterhead** — the clinic's legal identity as printed on its documents: legal name, legal form, share capital, SIRET, VAT number, RCS, APE code, address. Its SIREN is the one of the SIRET.
- **Billing settings** — how the clinic invoices: number prefixes, payment terms (individuals / professionals), IBAN / BIC, VAT regime, accepted payment methods, footer note. The **effective prefix** is the one invoices are actually numbered under: the clinic's own, else the default (`FAC` for invoices, `AV` for credit notes).
- **Seller identity** — the clinic seen as the seller of its invoices (`GetClinicSellerIdentity`): only the identifiers that hold are given.
- **Clinic administrator** — a membership carrying the `isClinicAdministrator` flag. It grants `manage_staff` and `manage_billing` on top of whatever the clinical role grants, and is **independent of that role**: the lead veterinarian of an independent practice wears the hat without ceasing to be a veterinarian, and a manager does not wear it by virtue of being a manager. In French UI copy the capability is *la gestion* — never *la gérance*, which names the `MANAGER` role.

## Aggregates

### Clinic
Core tenant aggregate. Carries: slug (unique URL identifier), name, timezone, locale, country code, jurisdiction code, currency code, status (`ACTIVE | SUSPENDED | CLOSED`), optional postal address and contact details. Status transitions are enforced by the domain; a closed clinic cannot be reactivated. `countryCode`, `currencyCode`, and `jurisdictionCode` are write-once at creation (alpha decision).

#### Letterhead (validated seller identity)
`Letterhead::create()` is the write path (`UpdateClinicLetterhead`): it checks the fiscal identity and throws `InvalidLetterheadException` (`field` = `siret` | `vatNumber`, `reason` = `format` | `checksum` | `missing` | `siren_mismatch` | `key`) when it does not hold — the SIRET must be 14 digits whose SIREN and whole number pass their check digit (La Poste's establishments exempted), and the VAT number must be the French one of that SIREN (key included). The settings page maps the field and reason to a French message on `id.siret` / `id.tva`. `Letterhead::reconstitute()` is the read path (`ClinicMapper`): it does **not** check again, so a row stored before the checks existed still loads and simply reads back with no valid SIREN.

#### Number series unique per SIREN
Several establishments (clinics) may carry one SIREN, each with its own invoice and credit-note series, provided no two of them number under the same **effective** prefix. `InvoiceNumberingUniqueness` (`Application/Service`) enforces it through `ClinicRepositoryInterface::findOthersWithSiren()`: saving a letterhead SIRET (`UpdateClinicLetterhead`) or a billing prefix (`UpdateClinicBillingSettings`) that would make two series of one company collide throws `InvoicePrefixAlreadyUsedException` (« Ce préfixe est déjà utilisé par un autre établissement du même SIREN. »). It is a no-worsening rule: a clinic whose SIREN and effective prefixes do not change may still save even when older data already collides. `(seller SIREN, document number)` therefore identifies one document, which electronic invoicing relies on.

#### GetClinicSellerIdentity
`GetClinicSellerIdentity(clinicId)` → `ClinicSellerIdentityDto`: legal name (the letterhead's, else the clinic's name), SIREN and VAT number **only when valid**, the address invoices are issued from (billing address, else the letterhead's), legal sentence, professional payment terms, IBAN / BIC, the effective invoice and credit-note prefixes, and `numberingIsUnique` (false when another establishment of the same SIREN numbers under one of these prefixes — legacy data). Billing reads it through its own port to qualify and check a professional invoice before it is numbered.

### ClinicGroup
Lightweight aggregate linking clinics under a shared brand or owner entity.

### Staff Sub-domain (`Domain/Staff/`)
Manages who works at which clinic and in which capacity.

- **ClinicMembership** — aggregate root. Tracks role, engagement type (`PARTNER | EMPLOYEE | FIXED_TERM | CONTRACTOR | REPLACEMENT | INTERN` — the last three end by themselves, `isTemporary()`), work time (percent of a full-time week, 100 by default), validity period, and lifecycle status (`ACTIVE | DISABLED`). Role changes and status transitions raise domain events consumed by the AccessControl BC to sync its RBAC projection.
- **StaffProfile** — aggregate linked to a ClinicMembership. Holds the member's name, phone, agenda colour and visibility, and — for a veterinarian only — the credentials (registration number with the Ordre, title, signature). Every role gets one through `OnboardStaffMember`, because the team page names everyone; `CreateClinicMembership` (back office) still creates a non-practitioner membership without one. Registering a new number or title keeps the signature on file.
- **ClinicPermission** — enum of fine-grained permission slugs (e.g. `create_prescription`, `view_medical_record`).
- **StaffRolePermissionMap** — static map: role → list of permissions, plus `administratorPermissions()` for the hat. Read by AccessControl BC at membership creation to seed role assignments; note that the AccessControl projection is keyed by role and therefore cannot express the per-membership hat.
- **`ClinicMembership::permissions()`** — the single seam where a member's rights are resolved: the union of the role's permissions and, when the hat is worn, the administrator's.

## Business Invariants

- Clinic slug must be globally unique and match `[a-z0-9-]+`.
- A closed clinic cannot be reactivated (terminal status).
- A letterhead is saved only with a valid SIRET and a VAT number matching its SIREN (`Letterhead::create`); stored letterheads load unchecked (`Letterhead::reconstitute`).
- Two clinics with the same SIREN never get the same effective invoice or credit-note prefix through a write (no-worsening, `InvoiceNumberingUniqueness`).
- A membership requires a verified user identity (validated via `UserExistenceCheckerInterface`).
- **No write may leave a clinic without an effective administrator.** Stated as a
  no-worsening rule, not as "a clinic always has one": a validity window closes
  on its own, so a clinic can reach zero with no write to refuse. Three writes
  are guarded — revoking the hat, disabling the membership, closing its validity
  window — and each is refused only when *that write* takes the clinic from
  having an effective administrator to having none. A clinic already at zero is
  never blocked, or nobody could repair it. Two ways back: a membership created
  into an administrator-less clinic receives the hat (best-effort — one created
  already out of window is hatted but not effective), and the publisher
  backoffice can always grant it on any existing membership, running outside
  `ClinicPermissionGuard`.

## Notifications

Clinic is the source of truth of what Notification needs about a clinic and pushes it there (Notification never reads Clinic).

- **Messaging settings** (`MessagingSettings`, JSON column `clinic__clinics.messaging_settings`) — the SMS sender name (alphanumeric, at most 11 letters, digits or spaces, at least one letter; by default the clinic name reduced to those characters, « Kiveto » when nothing usable remains) and the SMS overage cap in segments (default 500). `UpdateClinicMessagingSettings` replaces both and raises `ClinicMessagingSettingsChanged`; Paramètres › Notifications (`/settings/notifications`, clinic administrators) edits them and shows the month's SMS usage read from Notification's `GetSmsUsage`.
- **Messaging profile** — `ClinicMessagingProfileBridge` pushes every change of name, slug, letterhead, branding or messaging settings to Notification (`UpsertClinicMessagingProfile`) in the same transaction, through `MessagingProfileSyncInterface` (`NotificationMessagingProfileSync`). `SyncAllClinicMessagingProfiles` / `app:clinic:sync-messaging-profiles` pushes every clinic's profile and today's veterinarian count: run it once after deployment (the fixtures run it too).
- **Active veterinarians (D6)** — `clinic__veterinarian_daily_counts(clinic_id, day, count)`: effective VETERINARY memberships whose user account is ACTIVE (`UserAccountStatusInterface` → IdentityAccess), Europe/Paris day, the day's highest count kept. `RecordVeterinarianDailyCounts(clinicId)` counts one clinic; without an id (daily at 00:07, schedule `clinic`) it only queues one `async` job per clinic that is not CLOSED (`VeterinarianCountJobsInterface`, `TransportNamesStamp` — the class has no route), so one clinic failing never holds back the others. A clinic is also counted on membership changes (`RecountVeterinariansOnMembershipChange`) and when an invited user activates their account (`UserActivatedConsumer`); each value is pushed to Notification (`RecordVeterinarianCount`) for the SMS quota.
- **SMS quota in the bell** — `SmsUsageThresholdCrossedConsumer` turns `SmsUsageThresholdCrossedIntegrationEvent` (80 %, 100 %, overage cap) into a `SMS_QUOTA` bell entry for the clinic's administrators (`ListClinicMembersForNotification`), opening Paramètres › Notifications, through `BellNotifierInterface` (`CommandBusBellNotifier` → Notification's `PostInAppNotification`).
- **Queries for notifications** — `ListClinicMembersForNotification(clinicId, role?, administratorsOnly)`: user ids of the memberships in force now whose account is ACTIVE — an invited member who never activated is left out (bell recipients of Inventory: assistants, else administrators). `GetStaffPrintedName(clinicId, userId)` (D15): « Dr Claire Lambert » (title without a dot), « Hugo Martin », null without a staff profile.
- **Invitations** — « Inviter un membre » no longer takes a provisional password: the address gets a PENDING account (IdentityAccess `RegisterInvitedUser`), the membership is created, then the invitation e-mail is sent. The team page shows « Invité », « Invitation expirée » and « Renvoyer », read through `InvitationStatusReaderInterface` (IdentityAccess `GetInvitationStatuses`). The onboarding search also finds PENDING clinic accounts. Disabling a membership, or ending it (end date past), revokes that clinic's invitation links (`RevokeInvitationOnMembershipEnd` → `InvitationRevokerInterface` → IdentityAccess `RevokeInvitation`); a membership that only starts later keeps them.

## Cross-BC Dependencies

| Direction | Counterpart | Mechanism |
|-----------|-------------|-----------|
| Produces | Notification | `UpsertClinicMessagingProfile` and `RecordVeterinarianCount` commands (messaging profile, daily active veterinarians) |
| Consumes | Notification | `SmsUsageThresholdCrossedIntegrationEvent` → `SMS_QUOTA` bell entry to the administrators |
| Consumes | IdentityAccess | `UserActivatedIntegrationEvent` → recount of active veterinarians; `GetInvitationStatuses`, user statuses and `RevokeInvitation` through Clinic ports |
| Consumed by | Inventory, Scheduling, Presentation | `ListClinicMembersForNotification` (Inventory bell recipients), `GetStaffPrintedName` (appointment messages, invitation e-mail) |
| Produces | AccessControl BC | `ClinicMembershipCreated/Disabled/Enabled/RoleChanged` domain events → AccessControl syncs its RBAC projection |
| Consumed by | Billing BC | `GetClinicSellerIdentity` query — the seller identity of an invoice and its numbering uniqueness, through Billing's own port |
| Reads | IdentityAccess BC | `UserExistenceCheckerInterface` port (implemented by `IdentityAccessUserExistenceAdapter`) — verifies a user UUID exists before creating a membership |

## Known Debt

- `ClinicController` has multiple public action methods (pre-existing violation of the single-`__invoke` controller rule). Tracked as a separate chore.
