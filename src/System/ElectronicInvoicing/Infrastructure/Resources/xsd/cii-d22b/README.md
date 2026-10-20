# UN/CEFACT Cross Industry Invoice (CII) D22B — XSD

Vendored copy of the standard UN/CEFACT CII D22B schemas, used by
`Infrastructure/Format/XsdValidator` to validate every CII invoice / credit
note rendered by `Infrastructure/Format/Cii/CiiInvoiceRenderer` before it
leaves Kiveto.

| | |
|---|---|
| Source | UNECE — UN/CEFACT Cross Industry Invoice D22B, **uncoupled** publication |
| Archive | `docs/e-invoicing/CII_D22B/CII_D22B/XSD/uncoupled/Schema.zip` (provided by the product owner) |
| Archive sha256 | `1581ac229ad325dc4f964c22408b0c9abc40872bd9e1279c554ee80efb8de577` |
| Extracted on | 2026-10-21 |
| Entry schema | `uncefact/CrossIndustryInvoice_100pD22B.xsd` (namespace `urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100`) |

The `uncefact/` tree is kept exactly as found in the archive; do not edit
these files. The coupled publication (Windows-style flat paths) and the PPF
`F1_FULL` / `F1_BASE` schemas (Flux 1 profiles, which reject every AFNOR
invoice sample) are not used.

The XSD only checks the syntax; the EN16931 / BR-FR business rules
(schematron) are not covered here.
