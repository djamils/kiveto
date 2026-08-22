---
title: 'The catalogue page: what the clinic sells, and what it costs'
slug: 'catalogue-page'
created: '2026-08-22'
status: 'complete — 2026-08-22, branch feature/catalogue-page'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'Symfony 7.4 + Twig, Turbo frames, vanilla page module dispatched by route name'
  - 'Doctrine ORM for writes, raw DBAL read models (RowAccessor)'
  - 'Tailwind v4 + hand-written page CSS, PHPStan level max, PHPUnit'
files_to_modify:
  - 'src/Context/Catalog/Application/Port/** + Query/** (four read models)'
  - 'src/Context/Catalog/Infrastructure/Persistence/Doctrine/Repository/DbalCatalogListReadRepository.php (new)'
  - 'src/Presentation/Clinic/Controller/Catalog/** (list, create, update, archive, restore)'
  - 'templates/clinic/catalog/** (four tabs, their forms and their modals)'
  - 'assets/styles/pages/catalog.css + assets/js/pages/catalog.js'
  - 'config/services.yaml (read repository binding)'
code_patterns:
  - 'List pages follow templates/clinic/consultation/index.html.twig: topbar, filters, sticky table, pinned pagination'
  - 'Read models are DBAL queries returning readonly DTOs (DbalInvoiceReadRepository as reference)'
  - 'One __invoke() per controller; writes dispatch the existing Catalog commands'
test_patterns:
  - 'Unit: query handlers against a mocked port'
  - 'Integration: the DBAL read repository against Foundry fixtures'
  - '100% line coverage on src/Context/Catalog (Presentation excluded)'
---

# Tech-Spec: The catalogue page

**Created:** 2026-08-22

## Overview

### Problem Statement

`/catalogue` is an empty state that says "Bientôt disponible". Meanwhile the
Catalog bounded context is complete: acts, articles, packages, price lists,
their invariants, their commands and their fixtures all exist, and the rest of
the application already depends on them — the cockpit picks medicines and acts
from the catalogue, invoices snapshot their prices, ordonnances snapshot their
drug properties.

So the clinic can sell from a catalogue it cannot see. Nothing in the interface
lists what is on offer, nothing creates an act, nothing corrects a price that
moved, nothing archives the consultation type that is no longer practised. The
demo data was seeded by fixtures and can only be changed by reloading them.

### Solution

A catalogue page in the same shape as the other lists — topbar, filter bar,
sticky table, pinned pagination — split into the four families the bounded
context owns: **Actes**, **Articles**, **Forfaits**, **Tarifs**. Each is its own
route so the URL stays shareable and the filters stay per family.

The write side already exists as commands; the page only had to gain read models
that list and filter, which the bounded context did not have: it could fetch one
act by id, or search items by name for a picker, but not list a page of them.

### Scope

**In scope**

- Four read models on one new port: acts, articles, packages, price-list entries,
  plus the counters the tabs show.
- Four list routes with search, filters, sorting and pagination.
- Acts: create, edit (name, description, category, price, tax category,
  duration, anaesthesia), archive, restore.
- Articles: create (drug and non-drug), edit (name, price, tax category,
  prescription flags for drugs), archive, restore.
- Packages: create, edit the fixed price, add and remove components, archive,
  restore. Components resolve to their real names and prices, and the
  sum-of-components total is computed.
- Price lists: list them, open one, add, update and remove a per-item override.
- The demo fixtures already cover both clinics; the empty-catalogue clinic keeps
  exercising the empty state.

**Out of scope (stated, not silently dropped)**

- Creating and deleting price lists, and editing price rules. A rule carries a
  JSON condition and a JSON adjustment; editing those needs a form language of
  its own. The rules of a price list are listed read-only, and the "default"
  price list is the one the fixtures marked.
- Importing a catalogue from a file, and applying the starter catalogue from the
  interface. `ApplyStarterCatalog` stays a fixtures- and console-level command.
- Stock. `trackStock` is set on an article, but Inventory owns the quantities and
  has its own screens.
- Bulk edits (change the tax category of a whole category at once).

## Context for development

### What the bounded context already offers

| Need | Existing |
| --- | --- |
| One act, one article, one package, one price list | `GetActDetail`, `GetArticleDetail`, `GetPackageDetail`, `GetPriceList` |
| Name search for a picker | `SearchCatalogItems` (LIKE, capped at 20) |
| Every write | `CreateAct`, `RenameAct`, `ChangeActBasePrice`, `ChangeActTaxCategory`, `ArchiveAct`, `RestoreAct`, and their Article, Package and Pricing equivalents |
| Tax categories for the forms | `ListTaxCategories` (Taxation) |

What is missing is a list: a page of acts filtered by category and status, a page
of articles filtered by kind and prescription requirement, both sorted and
counted. That is what this spec adds.

### Tables

`catalog__acts`, `catalog__articles`, `catalog__packages`,
`catalog__package_components`, `catalog__price_lists`,
`catalog__price_list_items`, `catalog__price_rules`. All are scoped by
`tenant_id` (the clinic), and components and overrides reference their target by
`item_type` + `item_id` — the read models resolve those to names.

### Technical decisions

1. **One port, four reads.** `CatalogListReadRepositoryInterface` carries the
   four listing methods. Splitting it per family would multiply adapters over
   what is one SQL surface on one schema.
2. **Editing dispatches only what changed.** The domain splits an edit into
   `RenameAct`, `ChangeActBasePrice` and `ChangeActTaxCategory`; a single form
   compares against the current detail and dispatches the two or three commands
   that actually differ. The invariants stay in the domain, the form stays one
   form.
3. **Prices are typed in euros, stored in minor units.** The forms take
   `12,50`, the controller converts once, on the boundary.
4. **The four tabs are four routes.** `/catalogue`, `/catalogue/articles`,
   `/catalogue/forfaits`, `/catalogue/tarifs` — each a single-action controller,
   each with its own filters in the query string.

## Implementation plan

### Tasks

1. **Read models** — `ActListRow`, `ArticleListRow`, `PackageListRow`,
   `PriceListEntryRow`, `CatalogCounters`; the criteria objects; the port; the
   DBAL adapter; the query handlers.
2. **List routes** — four controllers rendering the four tabs, sharing the
   filter bar and the pagination partial.
3. **Write routes** — create, update, archive and restore for acts and articles;
   create, update, add and remove components, archive and restore for packages;
   add, update and remove an override for price lists.
4. **Templates** — the shell with its tabs, one table per family, the modals for
   the forms.
5. **CSS and page module** — reuse the list-page rules; the module handles the
   filter menus, the row menus and the modal forms.
6. **Tests** — unit tests on the handlers, integration tests on the DBAL reads.

### Acceptance criteria

- **Given** a clinic with a seeded catalogue, **when** `/catalogue` is opened,
  **then** its acts are listed with code, name, category, duration, tax category
  and price, the tab counters show how many acts, articles, packages and price
  lists exist, and the pagination is pinned to the bottom.
- **Given** the acts list, **when** a category and "archivés" are picked,
  **then** only the archived acts of that category are listed and the filters are
  in the URL.
- **Given** the create form, **when** an act is created with a code that already
  exists, **then** the page comes back with the domain's message and the typed
  values are still there.
- **Given** an act, **when** its price and its name are changed in one edit,
  **then** two commands are dispatched and the row shows both changes.
- **Given** an archived article, **when** it is restored and its marketing
  authorization was withdrawn, **then** the restore is refused with the domain's
  message.
- **Given** a package in SUM_OF_COMPONENTS mode, **when** it is opened, **then**
  its components are listed with their names and prices and the total is their
  weighted sum.
- **Given** a price list, **when** an override is added on an act, **then** the
  entry shows the base price, the override and the difference.
- **Given** `make ci`, **then** it is green and `src/Context/Catalog` stays at
  100% line coverage.

## Additional context

### Testing strategy

- **Unit** — the five query handlers against a mocked port, and the price
  parsing of the forms.
- **Integration** — the DBAL adapter against Foundry fixtures: filters, sorting,
  pagination, tenant isolation, and the name resolution of components and
  overrides.
- **Manual** — every screen and every write walked through in the browser
  against the demo fixtures.

## Outcome

Delivered on `feature/catalogue-page`. Every screen and every write was walked
through in the browser against the demo fixtures: create, edit, archive and
restore for acts and articles; create, compose, decompose and archive for
packages; add, correct and remove an override on a price list. `make ci` is
green and the new read models are at 100% line coverage.

Three things were found and fixed along the way, none of them foreseen:

- **`DoctrinePriceListRepository::save()` could not save twice.** It wiped the
  override rows and re-inserted them under the same ids, which collides with
  Doctrine's identity map. Adding a second override to a grid returned a 500.
  Items and rules are now updated where they stand and only true orphans are
  removed; a regression test saves the same grid twice.
- **The demo packages held nothing.** A forfait with no prestations shows a
  price with nothing behind it, so the composition and the "au détail"
  comparison had nothing to display. The Catalog story now composes all four.
- **The scheduling fixtures could not be loaded after 16:00.** They cancelled an
  appointment at a fixed hour, and the domain refuses to cancel one that has
  passed. The cancelled demo appointments are now placed relative to the clock.

One addition to the bounded context: `UpdateArticlePrescriptionFlags`, the
command behind the delivery field of the article form. The domain method and its
event already existed and were only reachable from the pharmaceutical registry's
synchronisation.

Domain messages are shown in French through `CatalogErrorMessage`, which maps the
exception type rather than rewording the bounded context — the invariants stay
where they are, and the counter reads a sentence it understands.
