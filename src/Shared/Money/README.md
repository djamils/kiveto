# Shared / Money — Kernel module

## Purpose
Provides a universal multi-currency monetary precision primitive for the entire application.
Lives under `Shared/` because Money, Currency and the rounding policies are framework-grade
primitives consumed by every bounded context that touches monetary amounts (Taxation today,
Billing tomorrow…). It is not an autonomous business module.

All monetary amounts are stored as integer minor units (zero floats).
bcmath is used for all arithmetic to prevent floating-point precision errors.

## Ubiquitous Language
- **Money**: An immutable value object carrying an amount (minor units) and a currency.
- **Currency**: An immutable VO describing an ISO 4217 currency (symbol, decimals, display name).
- **RoundingPolicy**: A strategy for rounding decimal amounts.
- **UnitPrice**: A unit price at six decimals, as integer micro-units of the currency unit
  (1 EUR = 1 000 000). Used for EN16931 BT-146; `netOfTax()` divides a price incl. VAT by
  (1 + rate) half to even, with bcmath.
- **LargestRemainderAllocator**: Splits an integer amount over weights so the shares add up
  exactly (floors first, leftover units to the largest remainders, first index on ties;
  negative totals mirror the positive split; all-zero weights split equally).

## Currency catalogue
The list of supported currencies is a static dictionary loaded from
`Infrastructure/Resources/currencies.yaml` at boot by `YamlCurrencyRegistry`.
There is no database persistence and no runtime mutation: refusing or restricting
a currency for a given clinic is a separate concern that belongs to its own
bounded context.

## Business Invariants
- Amounts are always stored as integer minor units (e.g. EUR 18.50 = 1850 minor units).
- SwissCashRounding only applies to CHF; other currencies throw `SwissCashRoundingRequiresChfException`.
- No float anywhere: decimals are parsed from strings, divisions go through bcmath and round half to even.
