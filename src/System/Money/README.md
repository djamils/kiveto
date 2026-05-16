# System / Money — Bounded Context

## Purpose
Provides a universal multi-currency monetary precision primitive for the entire application.
All monetary amounts are stored as integer minor units (zero floats).
bcmath is used for all arithmetic to prevent floating-point precision errors.

## Ubiquitous Language
- **Money**: An immutable value object carrying an amount (minor units) and a currency.
- **Currency**: An immutable VO describing an ISO 4217 currency (symbol, decimals, display name).
- **RoundingPolicy**: A strategy for rounding decimal amounts.

## Currency catalogue
The list of supported currencies is a static dictionary loaded from
`Infrastructure/Resources/currencies.yaml` at boot by `YamlCurrencyRegistry`.
There is no database persistence and no runtime mutation: refusing or restricting
a currency for a given clinic is a separate concern that belongs to its own
bounded context.

## Business Invariants
- Amounts are always stored as integer minor units (e.g. EUR 18.50 = 1850 minor units).
- SwissCashRounding only applies to CHF; other currencies throw `SwissCashRoundingRequiresChfException`.
