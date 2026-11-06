---
title: 'Kiveto Console — the backoffice redesign'
slug: 'backoffice-console'
created: '2026-11-06'
status: 'done'
branch: 'feature/backoffice-console'
source: 'D:\Kiveto\Kiveto Backoffice.pdf (38 screens, journeys A–I) and its HTML/PNG export kiveto-console-ecrans'
tech_stack:
  - 'PHP 8.5, Symfony 7.4 (Security, Messenger, Scheduler, Twig, AssetMapper)'
  - 'Doctrine ORM 3.5 + Migrations, MySQL 8.4'
  - 'Tailwind v4.2 + Stimulus 3.2 + Turbo 7.3 (no Node)'
  - 'PHPUnit 12.5, PHPStan max, Foundry 2.8, DAMA'
---

# Kiveto Console — the backoffice redesign

## Problem

The backoffice (`backoffice.{domain}`) is a sidebar CRUD over clinics, groups,
memberships, users and translations. It has no second factor, no audit, no
notion of a clinic's onboarding or subscription, no way to help a clinic
from inside its VetApp, no view of the ANMV registry imports, and its
controllers break the one-action rule. The mock-up « Kiveto Console »
replaces it.

## Scope decisions (user, 2026-11-06)

| Area | Decision |
|---|---|
| Journey I — Migrations | **Greyed out**, not handled in pre-alpha: the tab is shown disabled, the onboarding step « Données de l'ancien logiciel importées » is shown « Pré-alpha » and does not count. |
| Hospitalisation, Registre des stupéfiants | Listed in the feature catalogue as **« À venir »**, toggles disabled: the modules do not exist. |
| « Exporter les données » (clinic archive) | **Greyed out** in pre-alpha. |
| Old pages | Translations kept (account menu, new look, split into single-action controllers). Clinic groups and memberships pages removed (the clinic and user sheets replace them). |
| Delivery | One branch, one commit per slice once `make ci` is green, never pushed. |

## Design decisions

### D1 — Console shell
New layout `templates/backoffice/console/base.html.twig`: black top bar (logo,
deployment-tier badge, search button ⌘K, bell → audit log, avatar menu: Mon
compte, Traductions, Se déconnecter), white tab bar (Cliniques n, Migrations
greyed, Utilisateurs, Fonctionnalités, Registre ANMV, Journal d'audit). Geist
and Geist Mono, zinc neutrals, indigo `#4338ca` accent. Styles in
`assets/styles/pages/console.css` (`.co-*` classes), no inline styles.
Every page is a single-action controller under
`src/Presentation/Backoffice/Controller/<Area>/`. Classic POST forms with
redirects (backoffice strategy), Stimulus for modals, drawers, wizard rows,
toggles and the command palette.

### D2 — Back-office sign-in with a second factor (A1, A2, H2)
- The backoffice firewall drops `ContextAuthenticator` for a dedicated
  `BackofficeAuthenticator` (IdentityAccess Infrastructure) over two classic
  form posts: `/login` (e-mail + password, same rate limiter) then
  `/login/verification` (TOTP code or backup code, « Faire confiance à cet
  appareil 30 jours »). An admin without a second factor is sent to
  `/login/configuration` (QR code + confirmation code) before any page, then
  shown his ten backup codes once.
- The pending sign-in lives in the session (user id, 5 minutes, 5 code
  attempts). It is not an authenticated token: `IS_AUTHENTICATED_FULLY`
  never holds before the code.
- TOTP is RFC 6238 (SHA-1, 6 digits, 30 s, ±1 step), written in
  IdentityAccess with the RFC test vectors; the last accepted time step is
  stored, so a code is used once. The secret is encrypted at rest
  (sodium secretbox, key derived from `kernel.secret`). QR code rendered as
  SVG server side (`bacon/bacon-qr-code`).
- Backup codes: ten, 10 characters, stored as HMAC digests
  (`OtpCodeHasherInterface`), each used once.
- Trusted devices: table `identity_access__trusted_devices`, cookie with a
  32-byte secret (only its sha256 stored), 30 days, revocable.
- Back-office password reset: `/mot-de-passe-oublie` and
  `/reinitialiser` on the backoffice host; `RequestPasswordReset` and
  `ResetPassword` accept BACKOFFICE accounts, the link points to the
  backoffice host. The second factor is still asked after a reset.

### D3 — Admin profile and staff sessions
- `identity_access__backoffice_profiles`: display name, display time zone,
  two e-mail preferences (registry import failed, invitation expired). « DS »
  initials and « Djamil Soidiki » everywhere come from it.
- `identity_access__user_sessions`: one row per signed-in browser of a staff
  or admin account (session id digest, user agent, IP, created, last seen,
  revoked). A listener on the clinic and backoffice firewalls opens and
  touches it (at most once a minute) and logs out a revoked one at its next
  request. Lists « Sessions actives » (H2, E2) with « Révoquer » and
  « Révoquer toutes les sessions ». The place is the masked IP: there is no
  geolocation.

### D4 — Audit log (new BC `System/Audit`)
- Append-only table `audit__entries`: occurred at, actor kind (KIVETO,
  STAFF, SYSTEM, UNKNOWN), actor id and printed name (a snapshot), action
  code (`clinic.created`, `support.session_started`…), category (access,
  clinics, users, support, features, registry), French summary, clinic id
  and name, target user id, IP, details (ordered label/value pairs). No
  update, no delete in code.
- Written through one command, `RecordAuditEntry`, by the layer that knows
  the actor and the meaning: the backoffice controllers, the clinic team
  pages, the sign-in listeners (`admin.login`, `auth.login`,
  `auth.login_failed`, `auth.2fa_enabled`), the support-session and
  registry handlers.
- `ActorContext` is finally set from the security token on every request.
- Queries: search (filters category, clinic, actor, period, text; 12 per
  page), clinic activity, user history, CSV export.

### D5 — Clinic lifecycle and subscription (Clinic BC)
- `Clinic` gains: `subscription` (plan PILOT | PAID | INTERNAL, pilot end
  date), `parallelSoftware` (Vetup, dr.veto, Vétocom, Epivet, Autre, Aucun),
  `internalNote`, `onboardedAt`, and a `suspension` (reason, comment, at,
  actor id and name) kept while SUSPENDED.
- Displayed status: Suspendue (SUSPENDED) › Pilote actif / Payant / Interne
  (onboarded) › Onboarding.
- Onboarding checklist (`GetClinicOnboarding`): created; invitations sent;
  every invitation accepted; legacy data imported (pre-alpha, not counted);
  opening hours and rooms configured; first appointment. Ports to
  IdentityAccess (invitations, existing) and Scheduling (rooms, first
  appointment). `CompleteClinicOnboarding` stamps `onboardedAt` once every
  counted step is done; the scheduler runs it every 15 minutes.
- Billable vets = non-disabled memberships with the VETERINARY role;
  99 € HT per vet per month (`app.console.price_per_vet_cents`).
- Clinic creation wizard (B3–B6): company search and VIES check reuse
  Client's `SearchCompanyRegistry` / VAT verifier through the query bus;
  tenant id = slug (live availability check); team rows (the first vet is
  the clinic administrator); starting features (defaults pre-checked);
  plan. One POST creates the clinic, its letterhead, subscription, features,
  memberships and invitations (7-day links).
- Invitations live 7 days (was 72 h), for the clinic team page too.
  « Annuler l'invitation » revokes the link and disables the membership;
  « Modifier l'e-mail » changes a PENDING account's address and resends.

### D6 — Feature catalogue (new BC `System/FeatureCatalog`)
- Catalogue fixed in code (`FeatureCode`): `portal.online_booking`,
  `notification.sms_reminders`, `compliance.controlled_substances`
  (UPCOMING), `clinic.hospitalisation` (UPCOMING),
  `billing.electronic_invoicing` (BETA), `support.write_mode` (INTERNAL).
  Per feature: status (AVAILABLE, BETA, INTERNAL, UPCOMING), enabled by
  default for new clinics, modified at. Per clinic: enabled, since, by.
- Gating through ports in the consuming BCs: Scheduling's online booking
  (`IsOnlineBookingOpen`, the Portal booking routes, the settings section),
  Scheduling's SMS reminders, Billing's e-invoicing transmission and its
  VetApp entry, support write mode. UPCOMING features cannot be enabled.
- « Activer pour toutes » enables it for every non-suspended clinic, with an
  optional e-mail to their administrators.

### D7 — Support sessions (IdentityAccess)
- `SupportSession`: admin, target user, clinic, reason (required), mode
  (READ_ONLY | READ_WRITE — write needs `support.write_mode`), duration
  (15, 30, 60 min), started, expires, ended, pages viewed, writes.
- Hand-off: the backoffice issues a single-use 60-second secret (sha256
  stored) and redirects to `clinic.{domain}/support/{secret}`; the clinic
  host signs the target user in (`Security::login`, a support badge: no
  last-login stamp, no sign-in audit as the user) and selects the clinic.
- On the clinic host, a listener ends an expired or ended session (logout,
  back to the backoffice sheet), refuses unsafe methods in READ_ONLY (403)
  and counts pages and writes. The VetApp shows the yellow banner (D2
  screen) with the countdown and « Terminer la session ».
- Audit `support.session_started` / `support.session_ended` with target,
  duration, mode, pages viewed, writes, visible on the clinic sheet.

### D8 — ANMV registry (PharmaceuticalRegistry)
- « Lancer l'import » dispatches the async `StartRegistryImport`; a lock
  refuses a second run. Each run records its five steps (read XML,
  fingerprints, diff, write, catalogues) with durations, the file name and
  checksum, who launched it, the counts (added, modified, withdrawn,
  products impacted), and on failure the step, the message and a hint (a Git
  LFS pointer is detected and named).
- G1–G3 show the last run (success, running with live refresh, failed),
  the history and the source panel; G4 the diff entries (filters, « only
  AMM used by a clinic », catalogue impact per clinic from Catalog) and a CSV
  export.

### D9 — Users (E1–E4)
Staff tab: one row per account (clinics « +n », role, state Actif /
Invitation envoyée / Invitation expirée / Désactivé, last sign-in), filters
and pagination; Portal owners tab from ClientPortal. User sheet: profile,
clinic accesses (role change, remove = disable the membership, add to a
clinic), history (audit), sessions, security (password link; the second
factor is « Non disponible » for clinic accounts in pre-alpha), danger zone
« Désactiver le compte » (new `DisableStaffUser`: DISABLED account, every
membership disabled, sessions revoked). Pending invitation sheet: resend,
change e-mail, cancel.

### D10 — Global search
⌘K palette over clinics (name, slug, SIRET) and users (name, e-mail),
JSON endpoint, keyboard navigation.

## Slices (one commit each)

1. Console shell, audit BC, `ActorContext`, sign-in and audit of sign-ins,
   audit log page (H1), old pages removed, translations ported.
2. Second factor, trusted devices, backoffice password reset, admin
   profile, sessions, « Mon compte » (A1, A2, H2).
3. Clinic lifecycle, subscription, list (B1, B2), sheet (C1–C6), creation
   wizard (B3–B6), 7-day invitations.
4. Users (E1–E4).
5. Feature catalogue and gating (F1–F3).
6. Support sessions (D1–D3).
7. ANMV registry (G1–G4).
8. Global search, demo fixtures for every state, final pass.

## Acceptance (whole feature)

- Every screen of journeys A–H matches the mock-up's content and states;
  journey I and the clinic export are visibly greyed « Pré-alpha ».
- No backoffice page is reachable without password and second factor.
- Every action of the console appears in the audit log with its actor.
- `make ci` green; the new and touched BCs stay at 100 % line coverage;
  functional tests cover each console page and form.
- Demo fixtures show each state: clinics pilot / onboarding / suspended /
  internal, pending and expired invitations, a disabled user, support
  sessions, registry runs succeeded and failed, features at every status.

## Implementation notes (2026-11-06)

Delivered as eight commits on `feature/backoffice-console`, one per slice
above. What differs from the mock-up, and why:

- **Onboarding (C2):** the steps are clinic created, team invited,
  invitations accepted, opening hours set and first appointment; the
  mock-up's « Salles et agenda » is « Horaires d'ouverture » (there is no
  room concept) and the legacy import step is shown greyed « Pré-alpha »,
  not counted. Onboarding completes every 15 minutes (schedule `clinic`).
- **Company (B3):** the registry search is the Client BC's
  `SearchCompanyRegistry`; the VAT number is computed from the SIREN and
  checked with VIES (`VerifyVatNumber`).
- **Sessions (E2, H2):** the place of a session is its masked IP address
  (no geolocation).
- **Clinic staff second factor (E2):** not offered yet; the security card
  says so, greyed « Pré-alpha ».
- **Invitation (E3):** the banner shows the dates of the latest link; the
  delivery status of the e-mail and the automatic reminder are not shown
  (no such data yet). « Annuler l'invitation » revokes the links and turns
  the account off.
- **Features (F1–F3):** a clinic created before the catalogue keeps the
  modules it already had (legacy state); « Prévenir les administrateurs par
  e-mail » is greyed « Pré-alpha ».
- **Support (D1–D3):** the hand-off is a one-time secret valid two
  minutes; the clinic host opens the session without a sign-in event (no
  login recorded, not listed in « Sessions actives »); read-only refuses
  every change; the banner shows the expiry time (no live countdown).
- **Registry (G1–G4):** the files are dropped in
  `storage/pharma-registry/ANMV/current/` (the import command's folder)
  and « Lancer l'import » hands them to a worker; the history reads the
  snapshots' diff entries. The per-clinic catalogue impact (G4) is greyed
  « Pré-alpha ».
- **Greyed « Pré-alpha »:** journey I (Migrations, a tab without page) and
  « Exporter les données » of a clinic, as decided.



## Second pass (2026-11-06, after the first review)

1. **Layout.** Pages across the window; the foot of every table (count,
   pages) stays at the bottom of the screen; clinic marks centred and tinted
   (brand colour, else a tint from the id); « À traiter » folded after five,
   filtered by kind; no ⌘K badge; the company search's sources.
2. **Forms.** The masked code of A2 and of the configuration (« 000 000 »,
   « XXXXX-XXXXX »); every form checked as one types, no browser bubble;
   the new clinic named from the registry, its tenant shown with
   « Modifier », the summary live; Turbo frames on the clinics, users and
   audit lists, morphing back on the same page.
3. **Features.** `FeatureResolver` decides (upcoming, kill switch, plan,
   the clinic's switch, legacy); kill switch with its reason; « Réservée à »
   a plan; the feature page filters and switches the clinics shown; its
   settings save as they change.
4. **ANMV.** Each import keeps its source files (SHA-256, size, who
   launched it) and they download from its page; the files of the last
   import are not imported again.
5. **Team.** « Équipe »: members invited with a link (shown once, sent by
   hand in pre-alpha), roles administrator / support, switched off and on;
   `app:console:invite` for the first administrator.
