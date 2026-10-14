# Inventory Bounded Context

## Purpose

The Inventory BC manages per-clinic stock of catalog articles. It answers questions like:
- How many units of article X does clinic Y have right now?
- Which lots are expiring in the next 30 days?
- What stock movements happened this month?
- Are there active stock alerts (low stock, expiring lots, out-of-stock)?

It does **not** own article definitions (those belong to the Catalog BC) or supplier orders (future Procurement BC).

---

## Aggregates

### StockItem

The central aggregate. One `StockItem` exists per `(clinicId, articleId)` pair.

Responsibilities:
- Tracks `totalOnHand` (authoritative quantity, stored as a decimal string).
- Holds a collection of `Lot` entities.
- Enforces FEFO (First-Expired, First-Out) lot consumption via `FefoSelector`.
- Manages reservations (`reservedAmount`) for pending operations.
- Applies optimistic locking (`@Version`) to prevent concurrent write conflicts.
- Supports `trackStock=false` mode: events are recorded but lot tracking and quantity updates are skipped.

Key invariants:
- `totalOnHand` must never go negative.
- Reservations cannot exceed available stock (`totalOnHand - reservedAmount`).
- A `StockItem` can only be archived when `totalOnHand` is zero.

### StockMovement

An append-only audit record of every quantity change: receipts, consumptions, adjustments, recalls, physical inventory corrections.

Read-only after creation. Never mutated.

### StockAlert (read-model projection)

A denormalized projection, not a traditional aggregate. Alerts are created and resolved by `StockAlertProjector` in response to domain events.

Three alert types:
- `LOW_EXPIRY` — a lot expires within the configured thresholds (CRITICAL: ≤7d, WARNING: ≤14d, INFO: ≤30d).
- `LOW_STOCK` — `totalOnHand` is below the configured `StockThreshold`.
- `OUT_OF_STOCK` — available quantity (`totalOnHand - reservedAmount`) is zero.

---

## Cross-BC Dependencies

### Catalog BC

The Inventory BC never imports Catalog domain classes directly. All cross-BC data flows through `ArticleProviderInterface`:

```
App\Context\Inventory\Application\Port\ArticleProviderInterface
```

Implemented by:
```
App\Context\Inventory\Infrastructure\Adapter\Catalog\CatalogArticleProviderAdapter
```

The adapter queries the Catalog BC via `QueryBusInterface` (GetArticleDetail).

### Integration Events

The BC subscribes to Catalog integration events on `messenger.bus.integration_event`:
- `ArticleArchived` → `HandleArticleArchived` — auto-archives the matching `StockItem` if stock is zero.
- `ArticleRestored` → `HandleArticleRestored` — restores the matching `StockItem`.

Future stubs (Phase 2):
- `HandleSupplierReceiptCompleted` — will process Procurement BC receipts.
- `HandleItemUsedInConsultation` — will deduct stock triggered by Consultation BC.

---

## Key Design Decisions

### FEFO (First-Expired, First-Out)

`FefoSelector` picks the lot with the earliest expiry date when consuming stock. This minimises waste and regulatory risk in a veterinary clinic context.

### Lot tracking

Each receipt creates a `Lot` with a lot number, expiry date, quantity, and optional supplier reference. Lots transition through `ACTIVE → DEPLETED | EXPIRED | RECALLED`.

### Optimistic locking

`StockItemEntity` uses a `@Version` column to prevent concurrent modifications from two simultaneous requests. If a concurrent write is detected, Doctrine raises `OptimisticLockException`; the calling handler should retry.

### `trackStock=false` behaviour

Some articles (e.g. consumables with bulk accounting) do not require per-unit lot tracking. When `trackStock=false`:
- `receiveStock` records a `StockReceived` event but creates no lot and does not update `totalOnHand`.
- All stock alert generation is skipped.
- `consumeStock`, `adjustStock`, and reservation commands are rejected with `TrackingDisabledException`.

### Event ordering

`StockAlertProjector` reacts to domain events synchronously in the same transaction as the command. The projector queries the database directly via DBAL for performance, avoiding a round-trip through the ORM.

### Audit trail

Every command that changes stock produces a `StockMovement` record. The movement history is immutable and append-only. Physical inventory corrections (`RecordPhysicalInventory`) produce an `ADJUSTMENT` movement recording the delta.

---

### What the stock page reads and writes

- A `StockItem` may say where it is kept (`StorageLocation`: pharmacy, fridge,
  narcotics safe, operating room, reception) — `RelocateStockItem`.
- `ConsumeStock` takes an optional `lotId`: the stock leaves the lot that was
  actually opened (`StockItem::consumeFromLot`) instead of the FEFO pick.
- `DiscardExpiredLot` clears what is left of a lot past its date. A lot the
  nightly expiry has not reached yet is expired on the way, so the trail keeps
  exactly one « Perte — péremption » per lot.
- A count (`RecordPhysicalInventory`) brings a depleted lot back when boxes are
  found, and a receipt of a depleted lot number tops it up again; expired and
  recalled lots never come back. The page's « Annuler » is a `CORRECTION`
  count: the trail is never rewritten.
- An adjustment movement records its sense (`decrease`): its quantity is always
  positive.
- `WatchStock` reads the shelves as they stand, in the clinic's calendar:
  critical (a lot past its date still holding something, an article received
  before and now without usable stock), to watch (at or under the threshold, a
  usable lot expiring within 60 days), for information (a lot recalled during
  the last 30 days). The navigation badge counts the critical ones; the
  `StockAlert` projection still feeds the dashboard.
- `GetClinicStock` gives the page every item of the clinic with its lots, its
  weekly outflows over 12 weeks, its latest movements and the date of the last
  count; `ListMovements` pages the trail with the balance after each movement.

## Console Commands

| Command | Description |
|---|---|
| `app:inventory:expire-lots [--clinic=UUID]` | Marks all ACTIVE lots whose `expiry_date < today` as EXPIRED. Idempotent. Run daily via cron. |
| `app:inventory:detect-alerts [--clinic=UUID]` | Full re-projection of all stock alerts from current DB state. Idempotent. Use after migration or data repair. |
| `app:inventory:detect-stock-drift [--clinic=UUID]` | Read-only audit: reports `StockItem` rows where `totalOnHand != sum(active lots)`. Returns exit code 1 if drift found. |

---

## References

- Git flow, branch naming, and commit conventions: `CONTRIBUTING.md`
- Cross-BC communication rules: `CLAUDE.md` §6
- BMAD planning artefacts: `_bmad-output/`
