# System / Money — Bounded Context

## Purpose
Provides a universal multi-currency monetary precision primitive for the entire application.
All monetary amounts are stored as integer minor units (zero floats).
bcmath is used for all arithmetic to prevent floating-point precision errors.

## Ubiquitous Language
- **Money**: An immutable value object carrying an amount (minor units) and a currency.
- **Currency**: A reference aggregate identified by its ISO 4217 code.
- **RoundingPolicy**: A strategy for rounding decimal amounts.

## Documented Exceptions to Project Standards

### Currency uses CHAR(3) as Primary Key
The `Currency` aggregate uses `CurrencyCode` (CHAR 3) as its PK instead of the usual UUIDv7.
Reason: `Currency` is a static reference/dictionary aggregate where the code itself is the natural identifier.
Using a UUID PK while needing a unique currency code would add unnecessary indirection.
This is the only aggregate in the project with a non-UUID PK.

## Business Invariants
- Amounts are always stored as integer minor units (e.g. EUR 18.50 = 1850 minor units).
- SwissCashRounding only applies to CHF; other currencies throw `SwissCashRoundingRequiresChfException`.
