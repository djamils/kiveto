# Bounded Context: Catalog

The Catalog BC manages the complete commercial offering of a veterinary clinic: acts (consultations, surgeries, etc.), articles (drugs and non-drug products), packages (bundles of acts and articles), and pricing (price lists with item overrides and adjustment rules).

---

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **Act** | A veterinary service rendered to a patient (consultation, surgery, vaccination, imaging, hospitalization, etc.) |
| **Article** | A physical good: either a drug (with optional marketing authorization reference) or a non-drug product (consumable, food, equipment) |
| **Package** | A bundle of acts and/or articles sold together, priced either at a fixed price or as the sum of its components |
| **Price List** | A clinic-specific catalogue of item overrides and pricing rules; exactly one is marked as "default" |
| **Price List Item** | A per-item override (net price) inside a price list |
| **Price Rule** | A conditional pricing modifier (coefficient, fixed discount) that applies to specific items or all items in a pricing context |
| **Pricing Context** | The runtime parameters that determine which price rules apply (urgency, animal size, species group, discount code) |
| **Marketing Authorization (AMM)** | A regulatory reference from the PharmaceuticalRegistry BC that certifies a drug is marketable |
| **Drug Properties** | Prescription requirements, prescription class, and controlled-substance flag associated with a drug article |
| **Starter Catalog** | A pre-built set of acts, articles, and a default price list that can be applied idempotently to a new clinic |
| **Tenant** | The clinic that owns an item; all repository methods are scoped to a `ClinicId` |

---

## Business Invariants

### Act
- Code is unique per clinic (`^[A-Z0-9_-]{2,20}$`)
- Archived acts cannot be renamed, have their price changed, or tax category changed
- `archive()` and `restore()` are idempotent

### Article
- Code is unique per clinic
- GTIN (if provided) is unique per clinic
- Drug articles must be created via `createDrug()` — `createNonDrug(kind=DRUG)` throws
- Prescription flags can only be updated on DRUG articles
- `updatePrescriptionFlags()` is idempotent (no event emitted if flags are unchanged)
- Restoring a DRUG article with a withdrawn AMM is forbidden at handler level (RegulatoryRestoreForbiddenException)

### Package
- FIXED_PRICE mode requires a non-null `fixedPrice`
- SUM_OF_COMPONENTS mode must have a null `fixedPrice`
- Adding an archived item to a package is forbidden at handler level

### Price List
- Exactly one price list per clinic can be the default
- `DefaultPriceListAlreadyExistsException` is thrown when trying to create a second default price list

---

## Use Cases

### Acts
- Create an act (validates tax category, validates clinic currency match, checks code uniqueness)
- Rename an act
- Change the base price
- Change the tax category
- Archive / restore (idempotent)

### Articles
- Create a drug article (with optional AMM lookup; magistral drugs skip the lookup)
- Create a non-drug article (CONSUMABLE, FOOD, EQUIPMENT)
- Rename an article
- Change the base price
- Change the tax category
- Update prescription flags (drug only, idempotent)
- Archive / restore (drug restore checks AMM marketability)

### Packages
- Create a package (FIXED_PRICE or SUM_OF_COMPONENTS)
- Add/remove components (checks item status before adding)
- Change fixed price (only for FIXED_PRICE mode)
- Archive / restore (idempotent)

### Pricing
- Create a price list (with optional default flag)
- Add/update/remove price list items
- Add/remove price rules (SPECIES_ADJUSTMENT, SIZE_ADJUSTMENT, URGENCY_COEFFICIENT, DISCOUNT)
- Resolve price for a catalog item in a given pricing context

### Catalog
- Apply a starter catalog (acts + articles + price list, idempotent via `existsByCode` checks)
- Search catalog items by name (LIKE search across acts and articles)

### Synchronization (integration events from PharmaceuticalRegistry BC)
- `PrescriptionRequirementChanged` → updates prescription flags on all matching articles
- `ControlledSubstanceClassificationChanged` → updates controlled-substance flag
- `MarketingAuthorizationWithdrawn` → archives all matching articles

---

## Fixture Examples

### CompanionClinicCatalogStory
Demonstrates a realistic companion-animal clinic catalog:
- 3 active acts (CONS_STD, STERIL_FEMELLE, RADIO_THORAX) + 1 archived (OLD_CONS)
- 1 drug article (AMOX-250) + 2 non-drug articles (COLLAR-E, FOOD-RC)
- 2 packages (one FIXED_PRICE, one SUM_OF_COMPONENTS)
- 2 price lists (one default, one non-default)

### EmptyClinicStory
Demonstrates the empty-catalog UI state:
- No acts, articles, or packages
- One default price list (minimum required)

---

## Architecture Notes

- Cross-BC data from the **Clinic BC** is accessed via `ClinicInfoProviderInterface` → `ClinicInfoAdapter` (dispatches `GetClinic` query)
- Cross-BC data from the **PharmaceuticalRegistry BC** is accessed via `PharmaceuticalRefProviderInterface` → `HealthRegistryPharmaceuticalRefAdapter` (dispatches registry queries)
- Doctrine entities use `tenant_id` (not `clinic_id`) for BC isolation terminology consistency
- All UUID columns are stored as BINARY(16) via Symfony's `UuidType`
- The `SyncCatalogOnPharmaceuticalChange` event handler is registered on `messenger.bus.integration_event`
