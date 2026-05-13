# System / Money — Bounded Context

## Purpose
Provides a universal multi-currency monetary precision primitive for the entire application.
All monetary amounts are stored as integer minor units (zero floats).
bcmath is used for all arithmetic to prevent floating-point precision errors.

## Ubiquitous Language
- **Money**: An immutable value object carrying an amount (minor units) and a currency.
- **Currency**: A reference aggregate identified by its ISO 4217 code.
- **ExchangeRate**: Historical rate between two currencies for a given date.
- **RoundingPolicy**: A strategy for rounding decimal amounts.
- **ConversionService**: Domain service for converting Money between currencies using historical rates.

## Documented Exceptions to Project Standards

### Currency uses CHAR(3) as Primary Key
The `Currency` aggregate uses `CurrencyCode` (CHAR 3) as its PK instead of the usual UUIDv7.
Reason: `Currency` is a static reference/dictionary aggregate referenced by FK in `money__exchange_rates`.
Using a UUID PK while needing a unique currency code would add unnecessary indirection.
This is the only aggregate in the project with a non-UUID PK.

## Business Invariants
- Amounts are always stored as integer minor units (e.g. EUR 18.50 = 1850 minor units).
- Exchange rates are stored as bcmath decimal strings (e.g. "0.9523").
- ConversionService throws `StaleExchangeRateException` if the best rate is more than 7 days older than the requested date.
- SwissCashRounding only applies to CHF; other currencies throw `SwissCashRoundingRequiresChfException`.
