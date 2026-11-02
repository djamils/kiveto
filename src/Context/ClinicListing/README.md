# ClinicListing Bounded Context

The **ClinicListing** Bounded Context is the Portal's public directory of Kiveto clinics (slice 7, S7c): where an owner without a link finds a clinic by name, city or distance, opens its card, and (Task 48) asks it for a first appointment. It owns how a clinic appears there and a projection of what the Clinic context says of it; it never exposes a Clinic aggregate.

## Ubiquitous Language

- **Listing** — a clinic as the directory shows it: name, address, phone, time zone, weekly opening hours, coordinates, tags. Its **listing id** is the directory's own UUID, never the clinic id: public URLs (`/clinic-listings/{l}`) reveal nothing the tenancy keys on.
- **Listing settings** — set in VetApp « Paramètres › Annuaire »: `listed` (no by default), `acceptsNewClients` (yes by default), `tags` (« NAC », « Urgences »…).
- **Listed** — active (the clinic and its group, if any, are active) **and** `listed`. Only listed listings are searched or read; anything else is « not found ».
- **Online booking** — computed at read time, never stored: `acceptsNewClients` ∧ Scheduling takes new-client requests (settings `enabled` ∧ `newClientBookingEnabled` ∧ an online motif with `newClientsAllowed`).

## Model

- **`ClinicListing`** (`clinic_listing__clinic_listings`, PK `clinic_id`, unique `id`): the projection, one row per clinic, **never deleted** — a clinic suspended or closed (or whose group is suspended) is `active = false` and comes back with its settings and coordinates when reactivated. Columns: `name`, `address_line` (both letterhead street lines joined), `postal_code`, `city`, `phone`, `lat` DECIMAL(8,6) / `lng` DECIMAL(9,6) (nullable), `address_hash` (SHA-256 of the normalised address: lower case, single spaces), `timezone`, `opening_hours` JSON `[{day, opensAt, closesAt}]` (ISO day, by day then time; closures are not projected), `active`, `refreshed_at`. Indexes `(active, city)`, `(lat)`, `(lng)`.
  - `project()` (first read) / `refresh(profile)`: returns true when the normalised address changed, or still has no coordinates (an earlier geocoding failed or could not place it) — geocoding is then queued; the previous coordinates stay until the new ones are attached. An address removed drops the coordinates at once.
  - `attachCoordinates(hash, ?coordinates)`: ignored when the hash is no longer the current one (a stale answer).
- **`ListingSettings`** (`clinic_listing__listing_settings`, PK `clinic_id`; no row = defaults): `update(listed, acceptsNewClients, tags)` trims tags (inner spaces collapsed), drops blank ones and duplicates (ignoring case); each tag 2–30 characters, 8 at most, else `InvalidListingSettingsException(field: 'tags', French message)`.
- **`ListingAddress`** (needs a street line and a city), **`ListingCoordinates`** (WGS 84, six decimals), **`ListingProfile`** (what Clinic says).

## Commands

- **`RefreshClinicListing(clinicId)`** — re-reads the clinic through `ClinicProfileInterface` (`QueryBusClinicProfile`: Clinic's `GetClinic`, `GetClinicSettings`, `GetClinicGroup`) and upserts its projection; when the address hash changed or the address has no coordinates yet (also through `RebuildClinicListings`), sends **`GeocodeClinicListing`** through `ExternalApiCommandBusInterface` in the same transaction (implicit outbox). An unknown clinic writes nothing.
- **`RebuildClinicListings`** → int (clinics projected) — every clinic of `ListClinics`; console **`clinic-listing:rebuild`**, run at deploy and by the dev dataset (`ClinicListingStory`).
- **`UpdateListingSettings(clinicId, listed, acceptsNewClients, tags)`** — saves the settings and refreshes the projection synchronously.
- **`GeocodeClinicListing(clinicId)`** (`ExternalApiCommandInterface`, `async_external`, `messenger.bus.external_api`, no transaction) — geocodes the listing's current address through `AddressGeocoderInterface`; an outage (`ExternalServiceUnavailable`) is rethrown so that Messenger retries (3 times), after which the previous coordinates are kept; then **`AttachListingCoordinates(clinicId, addressHash, ?latitude, ?longitude)`** (short, transactional) records the answer, none when the address could not be placed. The coordinates are written apart (`saveCoordinates()`: only while the row has that address hash) and the refresh upsert never writes them, save to drop them with the address: a refresh and an answer never overwrite each other.

## Queries

- **`SearchClinicListings(?q, ?lat, ?lng, ?cursor, ?limit)`** → `ClinicListingPage{items: list<ClinicListingSummary{id, name, ?address{line, ?postalCode, city}, ?phone, ?distanceM, onlineBooking, tags, onKiveto = true, nextAvailableAt = null}>, ?nextCursor}`.
  - `q` trimmed (blank = none), ≥ 2 characters, `LIKE` over `name` and `city`, `%`, `_` and the escape character taken literally; `lat` ∈ [−90, 90] and `lng` ∈ [−180, 180], both or neither; `limit` clamped like every cursor route (`Cursor::clampLimit`: 20 by default, 1 to 50, never refused). Refusals: `InvalidListingSearchException(field, French message)`.
  - Around a point: a bounding box of `RADIUS_METRES` (150 km) on the indexed `lat` / `lng`, then the Haversine distance in whole metres (≤ the radius), nearest first then by listing id; listings without coordinates are left out of this result only. Otherwise by name then id.
  - Keyset cursor (base64url JSON): `{d, id, lat, lng}` around a point, `{name, id}` otherwise. A cursor that cannot be read, of the other ordering, or issued for other coordinates → `InvalidCursorException` (API `400 cursor_invalid`).
  - `onlineBooking`: one `OnlineBookingStatusInterface::openToNewClients()` call per page (Scheduling's `ListClinicsOpenToNewClients`), for the clinics that accept new clients only.
- **`GetClinicListing(listingId)`** → `?ClinicListingDetail{id, clinicId, name, ?address, ?phone, timezone, openingHours: list<OpeningHoursView{day, opensAt, closesAt}>, acceptsNewClients, onlineBooking, tags}`: null when unknown, malformed, unlisted or inactive (API `404 not_found` on every `/clinic-listings/{l}…` route). `clinicId` resolves the listing for the new-client booking and **never leaves the server**.
- **`GetListingSettings(clinicId)`** → `ListingSettingsView{listed, acceptsNewClients, tags}` (VetApp « Annuaire »).

## Geocoding

`GeoplateformeAddressGeocoder`: the Géoplateforme **search** endpoint (`https://data.geopf.fr/geocodage/search`, `index=address`, `limit=1`, `postcode` when known) — not the autocompletion the Client context uses. The first feature is kept when its `score` ≥ 0.5; its GeoJSON point is `[longitude, latitude]`. 429 → throttled, any other non-200, a transport error or a body that is not JSON → unavailable. `FakeAddressGeocoder` is bound under `when@dev` and `when@test` (no network): ten main cities at their centre, nudged by up to ~2 km from the street, any other city spread over metropolitan France from its name, a city containing « introuvable » not placed.

## Cross-BC Dependencies

| Direction | Counterpart | Mechanism |
|-----------|-------------|-----------|
| Consumes | Clinic | `ClinicProfileChangedIntegrationEvent` → `RefreshClinicListing` (`ClinicProfileChangedIntegrationEventConsumer`) |
| Reads | Clinic | `GetClinic`, `GetClinicSettings`, `GetClinicGroup`, `ListClinics` through `ClinicProfileInterface` |
| Reads | Scheduling | `ListClinicsOpenToNewClients` through `OnlineBookingStatusInterface` |
| Consumed by | Presentation (VetApp) | « Paramètres › Annuaire » (`/settings/directory`, `MANAGE_STAFF`): `GetListingSettings`, `UpdateListingSettings` |
| Consumed by | Portal API, ClientPortal (Tasks 48–49) | `SearchClinicListings`, `GetClinicListing` (listing → clinic resolution for the new-client booking) |

`tests/Unit/Architecture/PortalBoundaryTest` checks that this context imports no other context's Domain or Infrastructure, integration events aside.
