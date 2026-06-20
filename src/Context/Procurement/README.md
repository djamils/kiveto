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
