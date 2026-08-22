---
title: 'Stock management: what the clinic holds, and what it is running out of'
slug: 'stock-management'
created: '2026-08-22'
status: 'in-progress'
stepsCompleted: [1, 2, 3]
tech_stack:
  - 'Symfony 7.4 + Twig, vanilla page module dispatched by route name'
  - 'Doctrine ORM for writes, raw DBAL read models (RowAccessor)'
  - 'Tailwind v4 + hand-written page CSS, PHPStan level max, PHPUnit'
files_to_modify:
  - 'src/Context/Inventory/Application/Port/** + Query/** (overview read models)'
  - 'src/Context/Inventory/Infrastructure/Persistence/Doctrine/Repository/DbalStockOverviewReadRepository.php (new)'
  - 'src/Context/Catalog/** (list criteria: trackStockOnly, articleIds)'
  - 'src/Presentation/Clinic/Controller/Stock/** (list, movements, alerts, writes)'
  - 'templates/clinic/stock/** + assets/styles/pages/stock.css + assets/js/pages/stock.js'
  - 'fixtures/Context/Inventory/Story/InventoryDemoStory.php (new)'
  - 'templates/components/layout/sidebar.html.twig (Stocks entry)'
code_patterns:
  - 'List pages follow the catalogue: topbar, tabs, filter bar, sticky table, pinned pagination'
  - 'Read models are DBAL queries returning readonly DTOs'
  - 'One __invoke() per controller; writes dispatch the existing Inventory commands'
test_patterns:
  - 'Unit: query handlers against a mocked port'
  - 'Integration: the DBAL read repository against Foundry fixtures'
  - '100% line coverage on the code this spec adds'
---

# Tech-Spec: Stock management

**Created:** 2026-08-22

## Overview

### Problem Statement

The Inventory bounded context is complete and invisible. It has a `StockItem`
aggregate with FEFO lot consumption, reservations and optimistic locking, an
append-only movement trail, a stock-alert projection with three alert types and
three severities, thirteen commands, six queries and three console commands.

Nothing in the interface reaches any of it. There is no route, no controller, no
sidebar entry. A clinic cannot see how many boxes of amoxicilline it holds, which
lot expires first, what was consumed last week, or that something is out of
stock — and it cannot record a delivery, a withdrawal or a count.

The demo data does not help either: the Inventory fixture stories build synthetic
stock for a clinic id that belongs to no demo clinic, and are not loaded by the
dev dataset. Every inventory table is empty.

### Solution

Three screens, in the shape of the catalogue: **Stocks**, **Mouvements**,
**Alertes**.

The stock list is driven by the **Catalog**, not by Inventory: it lists the
articles that are marked as tracked, and enriches each page with the figures
Inventory holds for them. That is what makes an article the clinic has never
received appear in the list at zero — with an Inventory-driven list it would
stay invisible until someone opened a stock item for it, which is exactly the
moment a user needs to find it.

Each bounded context answers what it owns: Catalog sorts and filters by name,
code and kind; Inventory answers "which of these are out, low, or expiring" and
"what are the figures for these articles". The page composes the two.

### Scope

**In scope**

- Stock list: article, quantity on hand, reserved, available, threshold, active
  lots, earliest expiry, state. Search, filters (kind, state, tracked/untracked),
  sorting, pagination.
- The lots of one article, with their number, quantity, expiry and status.
- Movements: the audit trail, filtered by type, reason and period, paginated.
- Alerts: what is out of stock, below threshold or expiring, by severity.
- Writes from the interface: receive a delivery (lot + expiry), record a
  withdrawal, correct a lot after a count, change the threshold, recall a lot,
  archive and restore a stock item. Receiving on an article that has no stock
  item opens one first.
- Demo fixtures on both clinics: healthy stock, low stock, out of stock, a lot
  expiring within days, an expired lot, a recalled lot, an untracked article and
  an archived item, plus a movement history behind them.

**Out of scope (stated, not silently dropped)**

- Reservations. `ReserveStock` and `ReleaseReservation` exist and the list shows
  the reserved quantity, but nothing in the interface reserves: reservations
  belong to the flows that consume stock (consultation, surgery planning), not
  to a stock screen.
- Supplier orders and receipts. Procurement owns them and has its own screens;
  `HandleSupplierReceiptCompleted` stays the way stock arrives from there.
- Automatic deduction on consultation. `HandleItemUsedInConsultation` is a stub
  in the Inventory context and wiring it is a Consultation-side decision.
- Bulk import. `BulkImportInitialStock` stays a fixtures- and console-level
  command.
- Multi-location stock (a fridge, a vehicle, a second site). The aggregate has
  no notion of location.

## Context for development

### What the bounded context already offers

| Need | Existing |
| --- | --- |
| Every write | `OpenStockItem`, `ReceiveStock`, `ConsumeStock`, `RecordPhysicalInventory`, `ChangeStockThreshold`, `MarkLotAsRecalled`, `ArchiveStockItem`, `RestoreStockItem` |
| One stock item with its lots | `GetStockItem` |
| Active alerts | `GetActiveAlerts` (the alert row already carries the article name) |
| Expiring lots | `GetExpiringLots` |
| Movements | `SearchStockMovements` (through the write repository, unpaginated) |

What is missing is everything the pages need in one read: the figures for a set
of articles, the article ids in a given state, and a paginated movement list with
its total.

### Technical decisions

1. **The list is composed, not joined.** Inventory never queries the catalog
   tables and Catalog never queries the inventory ones. The controller asks
   Catalog for a page of tracked articles and Inventory for the figures of those
   article ids. Filtering by state works the other way round: Inventory returns
   the ids in that state and Catalog narrows its page on them.
2. **`CatalogListCriteria` gains `trackStockOnly` and `articleIds`.** Both are
   ordinary narrowings of a list the Catalog already owns.
3. **Quantities are decimal strings.** The domain stores them as strings to keep
   them exact; the forms take `2` or `2,5` and the controller normalises the
   comma once, on the boundary.
4. **Receiving opens the stock item when there is none.** The alternative is a
   separate "start tracking this article" step that exists only to satisfy the
   model.

## Implementation plan

### Tasks

1. **Catalog** — `trackStockOnly` and `articleIds` on the list criteria, honoured
   by the DBAL read.
2. **Inventory read models** — `StockSnapshotRow` (figures for a set of
   articles), `ListArticleIdsInState`, and a paginated movement list with its
   total, on one `StockOverviewReadRepositoryInterface` with a DBAL adapter.
3. **Controllers** — three list routes and the write routes.
4. **Templates, CSS, page module** — the catalogue's shape, its tab bar and its
   modals.
5. **Fixtures** — `InventoryDemoStory` on both demo clinics, through the command
   bus.
6. **Tests** — unit on the handlers, integration on the DBAL reads.

### Acceptance criteria

- **Given** a clinic whose catalogue has tracked articles, **when** `/stock` is
  opened, **then** every tracked article is listed with its quantity, its
  threshold and its state, including those that have never been received.
- **Given** the stock list, **when** "en rupture" is picked, **then** only the
  articles whose available quantity is zero are listed.
- **Given** an article with no stock item, **when** a delivery is recorded on it,
  **then** the stock item is opened, the lot is created and the quantity is the
  quantity received.
- **Given** an article with two lots, **when** a withdrawal is recorded, **then**
  the lot expiring first is the one drawn down (FEFO).
- **Given** a lot counted short, **when** the count is recorded, **then** the
  quantity matches the count and a movement of type `ADJUSTMENT` carries the
  delta.
- **Given** stock below its threshold, **when** the alerts are opened, **then**
  the article appears with its severity, and it disappears once the stock is
  back above the threshold.
- **Given** `make ci`, **then** it is green.

---

## What was found on the way

Three defects in the existing Inventory code surfaced while the pages were being
built against real data. They are fixed here because the screens cannot work
around them.

- **`DoctrineStockItemRepository::save()` rebuilt the aggregate as a new entity
  on every call.** Two commands on the same stock item in one process — a
  fixture load, a batch import, a message worker — collided with Doctrine's
  identity map. The mappers now hydrate the managed entity, and reconcile the
  lots by id instead of clearing them.
- **`StockAlertProjector` wrote an expiry alert with a unit but no level**, which
  the `chk_current_level` constraint refuses: every `LOW_EXPIRY` alert insert
  failed. The unit now travels with the level or not at all. The same fix
  applies to `app:inventory:detect-alerts`.
- **The unit came from the browser** on the withdrawal, count and threshold
  forms. It is a property of the stock item, so it is now read from
  `GetStockItem` and the hidden field is gone.

Two presentation decisions worth recording:

- A movement of type `ADJUSTMENT` stores the magnitude of the gap it closed, not
  its direction — the domain keeps quantities positive. The trail therefore
  shows an inventory line unsigned, rather than guessing a sign.
- A receipt whose expiry date is already past is refused. The date is the field
  people mistype, and stock arriving already out of date is a typo far more often
  than a delivery.
