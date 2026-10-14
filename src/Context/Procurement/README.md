# Context/Procurement

Bounded context responsible for supplier relationship management, purchase orders, and supplier receipts.

## Aggregates

- **Supplier**: A supplier/vendor that provides products to the clinic.
- **SupplierAccount**: Clinic-specific relationship with a supplier (customer codes, delivery addresses).
- **SupplierCatalogEntry**: A product in a supplier's catalog.
- **SupplierPricing**: Negotiated pricing for a clinic/catalog entry pair.
- **PurchaseOrder**: An order sent to a supplier (DRAFT → SUBMITTED → CONFIRMED → RECEIVED → CLOSED).
- **SupplierReceipt**: A delivery received from a supplier (PENDING_REVIEW → VALIDATED).

## Integration Events

- **SupplierReceiptCompletedIntegrationEvent**: Emitted when a receipt is validated; consumed by Inventory BC to credit stock.

## Order book (read side of the stock page)

- **ListSupplyOffers**: the active catalogue entries of the suppliers a clinic
  holds an active account with, matched on a barcode (GTIN) or a supplier
  reference, at the clinic's negotiated price while it holds. Procurement does
  not know the clinic's articles: the caller names them by what a catalogue can
  be matched on.
- **ListOpenPurchaseOrders**: the orders under way (being filled, sent, partly
  delivered, or whose sending failed) with their lines. A clinic fills one
  DRAFT order per supplier; `CreatePurchaseOrder` and `CreateSupplierReceipt`
  return the id of what they created.

