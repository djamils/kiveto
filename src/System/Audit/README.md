# Audit System Context

The **Audit** system context keeps the console's audit log: who did what,
when, from where, on which clinic or account. It is **append-only**: an entry
is recorded once and never changed nor removed, so the log is read as it was
written (names included).

## Ubiquitous Language

- **Audit entry** — one line of the log: when (`occurred_at`, UTC), the
  **actor**, the **action** (a dotted code such as `clinic.created`,
  `support.session_started`), its **category**, a French **summary**, the
  clinic and account concerned, the request's IP address and an ordered list
  of **details** (label → value, shown when the line is unfolded).
- **Actor** — `kiveto` (a member of the Kiveto team, back office), `staff` (a
  member of a clinic), `system` (a job of the platform, named after what it
  does: « Import ANMV »), `unknown` (a refused sign-in). The actor's name is a
  snapshot of the moment.
- **Category** — the filter chips of the log page: `access`, `clinics`,
  `users`, `support`, `features`, `registry`.

## Invariants

- Append-only: `AuditEntryRepositoryInterface` offers `append()` only; no
  update, no delete path exists.
- An action code is lower case, dotted, at most 64 characters.
- A `kiveto` or `staff` actor has an id and a name; `system` a name only;
  `unknown` neither.
- The summary is required (cut at 255 characters); an IP must be an address.

## Use cases

| Use case | What it does |
|---|---|
| `RecordAuditEntry` | Appends one entry; the caller (the console controllers, the sign-in listener, the support-session and registry handlers) gives the actor and the meaning. Answers the entry id. |
| `SearchAuditLog` | A page of the log (12 by default), newest first, filtered by category, clinic, kind of actor, account (written by it or about it), period in days and text (summary, code, actor and clinic names). A page beyond the last answers the last. |
| `ExportAuditLog` | The same filters, at most 10 000 entries, for the CSV export. |
| `ListClinicActivity` | « Activité récente » of a clinic's sheet. |
| `ListUserHistory` | « Historique du compte » of a user's sheet. |

## Persistence

Table `audit__entries` (DBAL repositories, schema `AuditEntryEntity`), indexed
by date, clinic, target account, actor and category, each with the date.
