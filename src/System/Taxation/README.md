# Taxation Bounded Context

Resolves applicable tax rates for any product/service sale and produces
`TaxApplication` value objects ready to be snapshotted on future invoice lines.

---

## Ubiquitous Language

| Term | Definition |
|------|-----------|
| **TaxRegime** | A national/regional fiscal framework (e.g. `FR`, `FR-DOM`). Contains a set of `TaxRate`s. |
| **TaxCategory** | A stable business classification for a product or service (e.g. `veterinary.drug.companion`). |
| **TaxRate** | A rate within a regime: a percentage value + a validity period + conditions that determine when it applies. |
| **TaxRateCondition** | The multi-axis filter on a `TaxRate`: matched categories (glob), regions, customer statuses, animal usages, clinic liability. |
| **FiscalContext** | The context of a sale: country, sale date, customer tax status, animal usage, clinic liability. |
| **TaxApplication** | The resolved result: net amount, tax amount, gross amount, applied rate, legal mentions. |
| **MentionTemplate** | A template for a legal mention (e.g. intracom reverse-charge). Conditions determine when it appears. |
| **LegalMentions** | A collection of rendered `LegalMention` items attached to a `TaxApplication`. |
| **TaxResolver** | Domain service that resolves `(TaxableItem + TaxRegimeId + FiscalContext) → TaxApplication`. |
| **Specificity Score** | A numeric score used to break ties between multiple matching `TaxRate`s. Higher = more specific. |

---

## Business Invariants

1. A `TaxRegime` cannot be activated if it has no rates.
2. Two rates with the same condition cannot have overlapping validity periods (detected at load time).
3. A `TaxApplication` is only valid when `netAmount + taxAmount === grossAmount` (integer minor units).
4. Currency must match the expected currency for the regime (`FR → EUR`, `CH → CHF`, `UK/GB → GBP`).
5. All category patterns in a YAML file must match at least one active `TaxCategory`.

---

## Use Cases

| Command / Query | Description |
|----------------|-------------|
| `app:taxation:load-categories` | Load all 19 veterinary `TaxCategory` records from `categories.yaml` (idempotent). |
| `app:taxation:load-regime <id>` | Load or reload a `TaxRegime` from its YAML file (idempotent). |
| `app:taxation:reload-all` | Reload all YAML-defined regimes at once. |
| `app:taxation:list-regimes` | List all active regimes. |
| `app:taxation:resolve-test` | Interactive tax resolution for testing. |
| `ResolveTax` query | Resolve tax for a `TaxableItem` in a given `FiscalContext`. |
| `GetEffectiveRateForUI` query | Retrieve the effective rate percentage for display (no money amounts needed). |

---

## Supported Regimes

| Regime | Status | Notes |
|--------|--------|-------|
| `FR` | **Active (MVP)** | Standard 20%, reduced 10%, super-reduced 5.5%, particular 2.1%. |
| `CH` | Backlog (Q4 2026) | Swiss VAT 8.1% / 3.8% / 2.6% — YAML file not yet created. |
| `BE`, `ES`, `DE` | Backlog | Architecture ready; no YAML files needed until implemented. |
| `UK` / `GB` | Backlog | Post-Brexit rules; blocked on jurisdiction clarification. |
| US sales tax | Out of scope | Per-state/county complexity; deferred to dedicated BC. |

---

## How to Add a New Country

1. **Create `regimes/<cc>.yaml`** under `src/System/Taxation/Infrastructure/Resources/regimes/`.
   - Follow the same structure as `fr.yaml` (id, name, country, active, rates, mentionTemplates).
   - Each rate needs: `id`, `value`, `validFrom`, optional `validTo`, and `appliesTo`.

2. **Add categories if needed** — add entries to `categories.yaml` and re-run `app:taxation:load-categories`.

3. **Load the regime** — `bin/console app:taxation:load-regime <CC>`.

4. **Add unit tests** in `tests/Unit/System/Taxation/Domain/Service/TaxResolverTest.php` for the new regime scenarios.

5. **Wire the currency** — add the regime code to `deriveExpectedCurrency()` in `ResolveTaxHandler` and `GetEffectiveRateForUIHandler`.

---

## Fixture Examples

Bootstrap the full taxonomy for development / testing:

```bash
bin/console app:taxation:load-categories
bin/console app:taxation:load-regime FR
```

Verify resolution:

```bash
bin/console app:taxation:resolve-test \
  --category=veterinary.act.consultation \
  --regime=FR \
  --net=10000 \
  --currency=EUR \
  --country=FR
```

For Foundry-based test seeding use `TaxonomyBootstrapStory` which dispatches
both commands programmatically.

---

## Architecture Decisions

### TaxRateCondition vs MentionTemplateCondition duplication

`TaxRateCondition` and `MentionTemplateCondition` share ~80% of their matching
logic (regions, customerStatuses, animalUsages, clinicLiability). This is
**intentional at MVP**. A shared `ConditionMatcher` abstraction is justified
only when one of these conditions is met:

1. A new matching dimension (e.g. `saleChannel`) must be propagated to both.
2. Three or more identical bugs are found and fixed in both classes within a
   6-month period.
3. A third condition-bearing class is added.

### DQL DELETE for TaxRateEntity (no orphanRemoval)

`DoctrineTaxRegimeRepository.save()` uses a DQL `DELETE` query to remove all
existing `TaxRateEntity` rows before inserting fresh ones. `orphanRemoval` was
rejected because it requires loading all existing rate entities into Doctrine's
identity map before deleting them — O(N) SELECTs + N DELETEs vs. one batch
DELETE. For future regimes with 50+ historical rates, this matters.

### String PKs for TaxRegimeId and TaxCategoryCode

Both use human-readable string PKs (`FR`, `veterinary.act.consultation`) rather
than UUIDs. These are stable dictionary values referenced by external BCs; a
stable, readable identifier has higher value than UUID collision-resistance here.
`MentionTemplateId` uses UUIDv7 (via `AbstractUuidId`) because it has no
external reference requirement.
