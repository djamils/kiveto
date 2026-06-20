---
title: 'Context/Procurement Bounded Context — Complete Implementation'
slug: 'context-procurement-bc'
created: '2026-05-25'
status: 'review'
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]
completedAt: '2026-05-26'
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3.5
  - MySQL/MariaDB (BINARY(16) UUIDs, FULLTEXT indexes, SELECT FOR UPDATE)
  - Foundry v2 (zenstruck/foundry ^2.8)
  - Symfony Messenger (command/query/event/integration_event buses)
  - Symfony YAML (simulated catalog resource files)
files_to_modify:
  # Config
  - config/packages/doctrine.yaml                                                                                          [MODIFY]
  - config/services.yaml                                                                                                    [MODIFY]
  # Migration
  - migrations/Procurement/Version<timestamp>.php                                                                          [NEW — 12 tables, generate via doctrine:migrations:diff]
  # README
  - src/Context/Procurement/README.md                                                                                      [NEW]
  # Domain — Shared VOs (intra-BC)
  - src/Context/Procurement/Domain/Shared/ValueObject/ClinicId.php                                                         [NEW]
  - src/Context/Procurement/Domain/Shared/ValueObject/ArticleId.php                                                        [NEW]
  # (PurchaseOrderRef removed — use PurchaseOrderId directly)
  - src/Context/Procurement/Domain/Shared/ValueObject/Address.php                                                          [NEW]
  # Domain — Supplier aggregate
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierId.php                                                     [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierName.php                                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierCode.php                                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierType.php                                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierIntegrationMode.php                                        [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierStatus.php                                                 [NEW]
  - src/Context/Procurement/Domain/Supplier/ValueObject/SupplierContact.php                                                [NEW]
  - src/Context/Procurement/Domain/Supplier/Supplier.php                                                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/Repository/SupplierRepositoryInterface.php                                     [NEW]
  - src/Context/Procurement/Domain/Supplier/Event/SupplierRegistered.php                                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/Event/SupplierRenamed.php                                                      [NEW]
  - src/Context/Procurement/Domain/Supplier/Event/SupplierIntegrationModeChanged.php                                       [NEW]
  - src/Context/Procurement/Domain/Supplier/Event/SupplierArchived.php                                                     [NEW]
  - src/Context/Procurement/Domain/Supplier/Exception/SupplierNotFoundException.php                                        [NEW]
  - src/Context/Procurement/Domain/Supplier/Exception/DuplicateSupplierCodeException.php                                   [NEW]
  - src/Context/Procurement/Domain/Supplier/Exception/ArchivedSupplierException.php                                        [NEW]
  # Domain — SupplierAccount aggregate
  - src/Context/Procurement/Domain/SupplierAccount/ValueObject/SupplierAccountId.php                                       [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/ValueObject/CustomerCode.php                                            [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/ValueObject/SupplierAccountStatus.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/SupplierAccount.php                                                     [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Repository/SupplierAccountRepositoryInterface.php                       [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Event/SupplierAccountCreated.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Event/SupplierAccountUpdated.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Event/SupplierAccountDisabled.php                                       [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Event/SupplierAccountEnabled.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Exception/SupplierAccountNotFoundException.php                          [NEW]
  - src/Context/Procurement/Domain/SupplierAccount/Exception/DuplicateSupplierAccountException.php                         [NEW]
  # Domain — SupplierCatalog aggregate
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/SupplierCatalogEntryId.php                                  [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/SupplierProductCode.php                                     [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/SupplierProductName.php                                     [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/Gtin.php                                                    [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/CatalogPrice.php                                            [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/ValueObject/SupplierCatalogEntryStatus.php                              [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/SupplierCatalogEntry.php                                                [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Repository/SupplierCatalogEntryRepositoryInterface.php                  [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Event/SupplierCatalogEntryAdded.php                                     [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Event/SupplierCatalogEntryUpdated.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Event/SupplierCatalogEntryDiscontinued.php                              [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Event/SupplierCatalogPriceChanged.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Exception/SupplierCatalogEntryNotFoundException.php                     [NEW]
  - src/Context/Procurement/Domain/SupplierCatalog/Exception/DuplicateSupplierProductCodeException.php                     [NEW]
  # DiscontinuedCatalogEntryException lives in Application/Exception/ (see below)
  # Domain — SupplierPricing aggregate
  - src/Context/Procurement/Domain/SupplierPricing/ValueObject/SupplierPricingId.php                                       [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/ValueObject/NegotiatedPrice.php                                         [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/ValueObject/SupplierPricingSource.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/ValueObject/EffectivePrice.php                                          [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/SupplierPricing.php                                                     [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Repository/SupplierPricingRepositoryInterface.php                       [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Service/PriceResolver.php                                               [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Event/SupplierPricingNegotiated.php                                     [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Event/SupplierPricingUpdated.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Event/SupplierPricingRemoved.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Exception/SupplierPricingNotFoundException.php                          [NEW]
  - src/Context/Procurement/Domain/SupplierPricing/Exception/NoEffectivePriceException.php                                 [NEW]
  # Domain — PurchaseOrder aggregate
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderId.php                                           [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderLineId.php                                       [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderNumber.php                                       [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderStatus.php                                       [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderLineStatus.php                                   [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/ExternalReference.php                                         [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/ValueObject/PurchaseOrderTotals.php                                       [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Entity/PurchaseOrderLine.php                                              [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/PurchaseOrder.php                                                         [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Repository/PurchaseOrderRepositoryInterface.php                           [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Service/PurchaseOrderNumberGeneratorInterface.php                         [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderCreated.php                                            [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderLineAdded.php                                          [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderLineUpdated.php                                        [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderLineRemoved.php                                        [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderSubmittingStarted.php                                  [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderSubmitted.php                                          [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderSendFailed.php                                         [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderConfirmed.php                                          [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderPartiallyReceived.php                                  [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderFullyReceived.php                                      [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderClosed.php                                             [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderCancelled.php                                          [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Event/PurchaseOrderLineCancelled.php                                      [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Exception/PurchaseOrderNotFoundException.php                              [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Exception/InvalidPurchaseOrderStatusTransitionException.php               [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Exception/EmptyPurchaseOrderException.php                                 [NEW]
  - src/Context/Procurement/Domain/PurchaseOrder/Exception/PurchaseOrderClosedException.php                                [NEW]
  # Domain — SupplierReceipt aggregate
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/SupplierReceiptId.php                                       [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/SupplierReceiptLineId.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/DeliveryNoteReference.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/LotInformation.php                                          [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/ReceiptMatchType.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/ValueObject/SupplierReceiptStatus.php                                   [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Entity/SupplierReceiptLine.php                                          [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/SupplierReceipt.php                                                     [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Repository/SupplierReceiptRepositoryInterface.php                       [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptCreated.php                                        [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptValidated.php                                      [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptCompleted.php                                      [NEW — domain event]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptCompletedIntegrationEvent.php                      [NEW — integration event, consumed by Inventory BC]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptLineAdded.php                                      [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Event/SupplierReceiptLineRemoved.php                                    [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Exception/SupplierReceiptNotFoundException.php                          [NEW]
  - src/Context/Procurement/Domain/SupplierReceipt/Exception/ReceiptValidationException.php                                [NEW]
  # Application — Ports
  - src/Context/Procurement/Application/Port/SupplierIntegrationAdapterInterface.php                                       [NEW]
  - src/Context/Procurement/Application/Port/ArticleProviderInterface.php                                                  [NEW]
  - src/Context/Procurement/Application/Port/ClinicProviderInterface.php                                                   [NEW]
  - src/Context/Procurement/Application/Port/SupplierAccountReadRepositoryInterface.php                                    [NEW]
  - src/Context/Procurement/Application/Port/SupplierCatalogReadRepositoryInterface.php                                    [NEW]
  - src/Context/Procurement/Application/Port/SupplierPricingReadRepositoryInterface.php                                    [NEW]
  - src/Context/Procurement/Application/Port/PurchaseOrderReadRepositoryInterface.php                                      [NEW]
  - src/Context/Procurement/Application/Port/SupplierReceiptReadRepositoryInterface.php                                    [NEW]
  # Application — Exceptions
  - src/Context/Procurement/Application/Exception/ReceiptQuantityExceedsOrderedException.php                                [NEW]
  - src/Context/Procurement/Application/Exception/CancelledLineCannotReceiveException.php                                   [NEW]
  - src/Context/Procurement/Application/Exception/PurchaseOrderClosedOrCancelledException.php                               [NEW]
  - src/Context/Procurement/Application/Exception/DiscontinuedCatalogEntryException.php                                     [NEW]
  - src/Context/Procurement/Application/Exception/SupplierAccountDisabledException.php                                      [NEW]
  - src/Context/Procurement/Application/Exception/CatalogImportNotSupportedException.php                                    [NEW]
  - src/Context/Procurement/Application/Exception/UnmatchedDeliveryNotFoundException.php                                     [NEW]
  - src/Context/Procurement/Application/Exception/UnmatchedDeliveryAlreadyResolvedException.php                             [NEW]
  - src/Context/Procurement/Application/Exception/ClinicSupplierMismatchException.php                                       [NEW]
  # Application — DTOs (adapter contract objects, pure PHP)
  - src/Context/Procurement/Application/Dto/SendOrderResult.php                                                            [NEW]
  - src/Context/Procurement/Application/Dto/DeliveryNoteData.php                                                           [NEW]
  - src/Context/Procurement/Application/Dto/CatalogEntryData.php                                                           [NEW]
  # Application — Services
  - src/Context/Procurement/Application/Service/SupplierIntegrationDispatcher.php                                          [NEW]
  - src/Context/Procurement/Application/Service/PurchaseOrderTotalsCalculator.php                                          [NEW]
  - src/Context/Procurement/Application/Service/IncomingDeliveryProcessor.php                                              [NEW]
  # Application — Commands: Supplier
  - src/Context/Procurement/Application/Command/RegisterSupplier/RegisterSupplier.php                                      [NEW]
  - src/Context/Procurement/Application/Command/RegisterSupplier/RegisterSupplierHandler.php                               [NEW]
  - src/Context/Procurement/Application/Command/RenameSupplier/RenameSupplier.php                                          [NEW]
  - src/Context/Procurement/Application/Command/RenameSupplier/RenameSupplierHandler.php                                   [NEW]
  - src/Context/Procurement/Application/Command/ChangeSupplierIntegrationMode/ChangeSupplierIntegrationMode.php            [NEW]
  - src/Context/Procurement/Application/Command/ChangeSupplierIntegrationMode/ChangeSupplierIntegrationModeHandler.php     [NEW]
  - src/Context/Procurement/Application/Command/ArchiveSupplier/ArchiveSupplier.php                                        [NEW]
  - src/Context/Procurement/Application/Command/ArchiveSupplier/ArchiveSupplierHandler.php                                 [NEW]
  # Application — Commands: SupplierAccount
  - src/Context/Procurement/Application/Command/CreateSupplierAccount/CreateSupplierAccount.php                            [NEW]
  - src/Context/Procurement/Application/Command/CreateSupplierAccount/CreateSupplierAccountHandler.php                     [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierAccount/UpdateSupplierAccount.php                            [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierAccount/UpdateSupplierAccountHandler.php                     [NEW]
  - src/Context/Procurement/Application/Command/DisableSupplierAccount/DisableSupplierAccount.php                          [NEW]
  - src/Context/Procurement/Application/Command/DisableSupplierAccount/DisableSupplierAccountHandler.php                   [NEW]
  - src/Context/Procurement/Application/Command/EnableSupplierAccount/EnableSupplierAccount.php                            [NEW]
  - src/Context/Procurement/Application/Command/EnableSupplierAccount/EnableSupplierAccountHandler.php                     [NEW]
  # Application — Commands: SupplierCatalog
  - src/Context/Procurement/Application/Command/AddSupplierCatalogEntry/AddSupplierCatalogEntry.php                        [NEW]
  - src/Context/Procurement/Application/Command/AddSupplierCatalogEntry/AddSupplierCatalogEntryHandler.php                 [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierCatalogEntry/UpdateSupplierCatalogEntry.php                  [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierCatalogEntry/UpdateSupplierCatalogEntryHandler.php           [NEW]
  - src/Context/Procurement/Application/Command/DiscontinueSupplierCatalogEntry/DiscontinueSupplierCatalogEntry.php        [NEW]
  - src/Context/Procurement/Application/Command/DiscontinueSupplierCatalogEntry/DiscontinueSupplierCatalogEntryHandler.php [NEW]
  - src/Context/Procurement/Application/Command/ImportSupplierCatalog/ImportSupplierCatalog.php                            [NEW]
  - src/Context/Procurement/Application/Command/ImportSupplierCatalog/ImportSupplierCatalogHandler.php                     [NEW]
  # Application — Commands: SupplierPricing
  - src/Context/Procurement/Application/Command/NegotiateSupplierPricing/NegotiateSupplierPricing.php                     [NEW]
  - src/Context/Procurement/Application/Command/NegotiateSupplierPricing/NegotiateSupplierPricingHandler.php               [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierPricing/UpdateSupplierPricing.php                            [NEW]
  - src/Context/Procurement/Application/Command/UpdateSupplierPricing/UpdateSupplierPricingHandler.php                     [NEW]
  - src/Context/Procurement/Application/Command/RemoveSupplierPricing/RemoveSupplierPricing.php                            [NEW]
  - src/Context/Procurement/Application/Command/RemoveSupplierPricing/RemoveSupplierPricingHandler.php                     [NEW]
  # Application — Commands: PurchaseOrder
  - src/Context/Procurement/Application/Command/CreatePurchaseOrder/CreatePurchaseOrder.php                                [NEW]
  - src/Context/Procurement/Application/Command/CreatePurchaseOrder/CreatePurchaseOrderHandler.php                         [NEW]
  - src/Context/Procurement/Application/Command/AddPurchaseOrderLine/AddPurchaseOrderLine.php                              [NEW]
  - src/Context/Procurement/Application/Command/AddPurchaseOrderLine/AddPurchaseOrderLineHandler.php                       [NEW]
  - src/Context/Procurement/Application/Command/UpdatePurchaseOrderLine/UpdatePurchaseOrderLine.php                        [NEW]
  - src/Context/Procurement/Application/Command/UpdatePurchaseOrderLine/UpdatePurchaseOrderLineHandler.php                 [NEW]
  - src/Context/Procurement/Application/Command/RemovePurchaseOrderLine/RemovePurchaseOrderLine.php                        [NEW]
  - src/Context/Procurement/Application/Command/RemovePurchaseOrderLine/RemovePurchaseOrderLineHandler.php                 [NEW]
  - src/Context/Procurement/Application/Command/SubmitPurchaseOrder/SubmitPurchaseOrder.php                                [NEW]
  - src/Context/Procurement/Application/Command/SubmitPurchaseOrder/SubmitPurchaseOrderHandler.php                         [NEW]
  - src/Context/Procurement/Application/Command/ConfirmPurchaseOrder/ConfirmPurchaseOrder.php                              [NEW]
  - src/Context/Procurement/Application/Command/ConfirmPurchaseOrder/ConfirmPurchaseOrderHandler.php                       [NEW]
  - src/Context/Procurement/Application/Command/CancelPurchaseOrderLine/CancelPurchaseOrderLine.php                        [NEW]
  - src/Context/Procurement/Application/Command/CancelPurchaseOrderLine/CancelPurchaseOrderLineHandler.php                 [NEW]
  - src/Context/Procurement/Application/Command/ClosePurchaseOrder/ClosePurchaseOrder.php                                  [NEW]
  - src/Context/Procurement/Application/Command/ClosePurchaseOrder/ClosePurchaseOrderHandler.php                           [NEW]
  - src/Context/Procurement/Application/Command/CancelPurchaseOrder/CancelPurchaseOrder.php                                [NEW]
  - src/Context/Procurement/Application/Command/CancelPurchaseOrder/CancelPurchaseOrderHandler.php                         [NEW]
  # Application — Commands: SupplierReceipt
  - src/Context/Procurement/Application/Command/CreateSupplierReceipt/CreateSupplierReceipt.php                            [NEW]
  - src/Context/Procurement/Application/Command/CreateSupplierReceipt/CreateSupplierReceiptHandler.php                     [NEW]
  - src/Context/Procurement/Application/Command/ValidateSupplierReceipt/ValidateSupplierReceipt.php                        [NEW]
  - src/Context/Procurement/Application/Command/ValidateSupplierReceipt/ValidateSupplierReceiptHandler.php                 [NEW]
  - src/Context/Procurement/Application/Command/MatchManualDelivery/MatchManualDelivery.php                                [NEW]
  - src/Context/Procurement/Application/Command/MatchManualDelivery/MatchManualDeliveryHandler.php                         [NEW]
  - src/Context/Procurement/Application/Command/PollSupplierDeliveries/PollSupplierDeliveries.php                          [NEW]
  - src/Context/Procurement/Application/Command/PollSupplierDeliveries/PollSupplierDeliveriesHandler.php                   [NEW]
  # Application — Queries
  - src/Context/Procurement/Application/Query/ListSuppliers/ListSuppliers.php                                              [NEW]
  - src/Context/Procurement/Application/Query/ListSuppliers/ListSuppliersHandler.php                                       [NEW]
  - src/Context/Procurement/Application/Query/GetSupplierDetail/GetSupplierDetail.php                                      [NEW]
  - src/Context/Procurement/Application/Query/GetSupplierDetail/GetSupplierDetailHandler.php                               [NEW]
  - src/Context/Procurement/Application/Query/ListSupplierAccountsByClinic/ListSupplierAccountsByClinic.php                [NEW]
  - src/Context/Procurement/Application/Query/ListSupplierAccountsByClinic/ListSupplierAccountsByClinicHandler.php         [NEW]
  - src/Context/Procurement/Application/Query/SearchSupplierCatalog/SearchSupplierCatalog.php                              [NEW]
  - src/Context/Procurement/Application/Query/SearchSupplierCatalog/SearchSupplierCatalogHandler.php                       [NEW]
  - src/Context/Procurement/Application/Query/GetSupplierPricingForClinic/GetSupplierPricingForClinic.php                  [NEW]
  - src/Context/Procurement/Application/Query/GetSupplierPricingForClinic/GetSupplierPricingForClinicHandler.php           [NEW]
  - src/Context/Procurement/Application/Query/ResolveEffectivePrice/ResolveEffectivePrice.php                              [NEW]
  - src/Context/Procurement/Application/Query/ResolveEffectivePrice/ResolveEffectivePriceHandler.php                       [NEW]
  - src/Context/Procurement/Application/Query/ListPurchaseOrdersByClinic/ListPurchaseOrdersByClinic.php                    [NEW]
  - src/Context/Procurement/Application/Query/ListPurchaseOrdersByClinic/ListPurchaseOrdersByClinicHandler.php             [NEW]
  - src/Context/Procurement/Application/Query/GetPurchaseOrderDetail/GetPurchaseOrderDetail.php                            [NEW]
  - src/Context/Procurement/Application/Query/GetPurchaseOrderDetail/GetPurchaseOrderDetailHandler.php                     [NEW]
  - src/Context/Procurement/Application/Query/ListPendingReceipts/ListPendingReceipts.php                                  [NEW]
  - src/Context/Procurement/Application/Query/ListPendingReceipts/ListPendingReceiptsHandler.php                           [NEW]
  - src/Context/Procurement/Application/Query/SearchUnmatchedDeliveries/SearchUnmatchedDeliveries.php                      [NEW]
  - src/Context/Procurement/Application/Query/SearchUnmatchedDeliveries/SearchUnmatchedDeliveriesHandler.php               [NEW]
  - src/Context/Procurement/Application/Query/ListPurchaseOrderHistory/ListPurchaseOrderHistory.php                        [NEW]
  - src/Context/Procurement/Application/Query/ListPurchaseOrderHistory/ListPurchaseOrderHistoryHandler.php                 [NEW]
  - src/Context/Procurement/Application/Query/GetStaleOpenPurchaseOrders/GetStaleOpenPurchaseOrders.php                    [NEW]
  - src/Context/Procurement/Application/Query/GetStaleOpenPurchaseOrders/GetStaleOpenPurchaseOrdersHandler.php             [NEW]
  # Infrastructure — Doctrine Entities
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierEntity.php                                  [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierAccountEntity.php                           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierCatalogEntryEntity.php                      [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierPricingEntity.php                           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/PurchaseOrderEntity.php                             [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/PurchaseOrderLineEntity.php                         [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierReceiptEntity.php                           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity/SupplierReceiptLineEntity.php                       [NEW]
  # Infrastructure — Mappers
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/SupplierMapper.php                                  [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/SupplierAccountMapper.php                           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/SupplierCatalogEntryMapper.php                      [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/SupplierPricingMapper.php                           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/PurchaseOrderMapper.php                             [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Mapper/SupplierReceiptMapper.php                           [NEW]
  # Infrastructure — Write Repositories
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierRepository.php                  [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierAccountRepository.php           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierCatalogEntryRepository.php      [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierPricingRepository.php           [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrinePurchaseOrderRepository.php             [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierReceiptRepository.php           [NEW]
  # Infrastructure — Read Repositories (CQRS lite)
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierReadRepository.php              [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierAccountReadRepository.php       [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierCatalogReadRepository.php       [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierPricingReadRepository.php       [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrinePurchaseOrderReadRepository.php         [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrineSupplierReceiptReadRepository.php       [NEW]
  - src/Context/Procurement/Infrastructure/Persistence/Doctrine/Repository/DoctrinePurchaseOrderNumberSequenceRepository.php [NEW]
  # Infrastructure — Adapters
  - src/Context/Procurement/Infrastructure/Adapter/Catalog/CatalogArticleProviderAdapter.php                               [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Clinic/ClinicProviderAdapter.php                                        [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Simulation/SimulationProfileConfig.php                         [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Simulation/SimulatedSupplierAdapter.php                        [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Manual/ManualExportAdapter.php                                 [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Manual/CentravetCsvExporter.php                                [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Manual/AlcyonCsvExporter.php                                   [NEW]
  - src/Context/Procurement/Infrastructure/Adapter/Supplier/Manual/GenericCsvExporter.php                                  [NEW]
  # Infrastructure — Console Commands
  - src/Context/Procurement/Infrastructure/Console/PollSupplierDeliveriesCommand.php                                       [NEW]
  - src/Context/Procurement/Infrastructure/Console/ImportSupplierCatalogsCommand.php                                       [NEW]
  - src/Context/Procurement/Infrastructure/Console/CloseStaleOrdersCommand.php                                             [NEW]
  - src/Context/Procurement/Infrastructure/Console/DemoBootstrapClinicCommand.php                                          [NEW]
  - src/Context/Procurement/Infrastructure/Console/DemoApplyStarterCatalogCommand.php                                      [NEW]
  - src/Context/Procurement/Infrastructure/Console/DemoRegisterSuppliersCommand.php                                        [NEW]
  - src/Context/Procurement/Infrastructure/Console/DemoSimulatePurchaseOrdersCommand.php                                   [NEW]
  - src/Context/Procurement/Infrastructure/Console/DemoSimulateConsumptionCommand.php                                      [NEW]
  # Resources
  - resources/simulated-catalogs/centravet.yaml                                                                             [NEW]
  - resources/simulated-catalogs/alcyon.yaml                                                                                [NEW]
  - resources/simulated-catalogs/hippocampe.yaml                                                                            [NEW]
  # Fixtures
  - fixtures/Context/Procurement/Factory/SupplierEntityFactory.php                                                          [NEW]
  - fixtures/Context/Procurement/Factory/SupplierAccountEntityFactory.php                                                   [NEW]
  - fixtures/Context/Procurement/Factory/SupplierCatalogEntryEntityFactory.php                                              [NEW]
  - fixtures/Context/Procurement/Factory/SupplierPricingEntityFactory.php                                                   [NEW]
  - fixtures/Context/Procurement/Factory/PurchaseOrderEntityFactory.php                                                     [NEW]
  - fixtures/Context/Procurement/Factory/PurchaseOrderLineEntityFactory.php                                                 [NEW]
  - fixtures/Context/Procurement/Factory/SupplierReceiptEntityFactory.php                                                   [NEW]
  - fixtures/Context/Procurement/Factory/SupplierReceiptLineEntityFactory.php                                               [NEW]
  - fixtures/Context/Procurement/Story/ThreeSimulatedSuppliersStory.php                                                     [NEW]
  - fixtures/Context/Procurement/Story/ClinicWithSupplierAccountsStory.php                                                  [NEW]
  - fixtures/Context/Procurement/Story/ActivePurchaseOrdersStory.php                                                        [NEW]
  - fixtures/Context/Procurement/Story/CompletedReceiptsStory.php                                                           [NEW]
  # Tests — Unit
  - tests/Context/Procurement/Unit/Domain/Supplier/SupplierTest.php                                                         [NEW]
  - tests/Context/Procurement/Unit/Domain/SupplierAccount/SupplierAccountTest.php                                           [NEW]
  - tests/Context/Procurement/Unit/Domain/SupplierCatalog/SupplierCatalogEntryTest.php                                      [NEW]
  - tests/Context/Procurement/Unit/Domain/SupplierPricing/SupplierPricingTest.php                                           [NEW]
  - tests/Context/Procurement/Unit/Domain/SupplierPricing/PriceResolverTest.php                                             [NEW]
  - tests/Context/Procurement/Unit/Domain/PurchaseOrder/PurchaseOrderTest.php                                               [NEW]
  - tests/Context/Procurement/Unit/Domain/PurchaseOrder/PurchaseOrderWorkflowTest.php                                       [NEW]
  - tests/Context/Procurement/Unit/Domain/SupplierReceipt/SupplierReceiptTest.php                                           [NEW]
  # Tests — Integration
  - tests/Context/Procurement/Integration/Persistence/SupplierMapperTest.php                                                [NEW]
  - tests/Context/Procurement/Integration/Persistence/SupplierAccountMapperTest.php                                         [NEW]
  - tests/Context/Procurement/Integration/Persistence/SupplierCatalogEntryMapperTest.php                                    [NEW]
  - tests/Context/Procurement/Integration/Persistence/SupplierPricingMapperTest.php                                         [NEW]
  - tests/Context/Procurement/Integration/Persistence/PurchaseOrderMapperTest.php                                           [NEW]
  - tests/Context/Procurement/Integration/Persistence/SupplierReceiptMapperTest.php                                         [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrineSupplierRepositoryTest.php                                    [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrineSupplierAccountRepositoryTest.php                             [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrineSupplierCatalogEntryRepositoryTest.php                        [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrineSupplierPricingRepositoryTest.php                             [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrinePurchaseOrderRepositoryTest.php                               [NEW]
  - tests/Context/Procurement/Integration/Persistence/DoctrineSupplierReceiptRepositoryTest.php                             [NEW]
  - tests/Context/Procurement/Integration/Persistence/PurchaseOrderNumberGeneratorTest.php                                  [NEW]
  - tests/Context/Procurement/Integration/Adapter/SimulatedSupplierAdapterTest.php                                          [NEW]
  - tests/Context/Procurement/Integration/Adapter/CentravetCsvExporterTest.php                                              [NEW]
  - tests/Context/Procurement/Integration/Command/ValidateSupplierReceiptHandlerTest.php                                    [NEW]
  - tests/Context/Procurement/E2E/DemoBootstrapPipelineTest.php                                                             [NEW]
  # Tests — Application Unit (Command Handlers)
  - tests/Context/Procurement/Unit/Application/Command/RegisterSupplierHandlerTest.php                                      [NEW]
  - tests/Context/Procurement/Unit/Application/Command/RenameSupplierHandlerTest.php                                        [NEW]
  - tests/Context/Procurement/Unit/Application/Command/ChangeSupplierIntegrationModeHandlerTest.php                         [NEW]
  - tests/Context/Procurement/Unit/Application/Command/ArchiveSupplierHandlerTest.php                                       [NEW]
  - tests/Context/Procurement/Unit/Application/Command/CreateSupplierAccountHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/UpdateSupplierAccountHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/DisableSupplierAccountHandlerTest.php                                [NEW]
  - tests/Context/Procurement/Unit/Application/Command/EnableSupplierAccountHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/AddSupplierCatalogEntryHandlerTest.php                               [NEW]
  - tests/Context/Procurement/Unit/Application/Command/UpdateSupplierCatalogEntryHandlerTest.php                            [NEW]
  - tests/Context/Procurement/Unit/Application/Command/DiscontinueSupplierCatalogEntryHandlerTest.php                       [NEW]
  - tests/Context/Procurement/Unit/Application/Command/ImportSupplierCatalogHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/NegotiateSupplierPricingHandlerTest.php                              [NEW]
  - tests/Context/Procurement/Unit/Application/Command/UpdateSupplierPricingHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/RemoveSupplierPricingHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/CreatePurchaseOrderHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Command/AddPurchaseOrderLineHandlerTest.php                                  [NEW]
  - tests/Context/Procurement/Unit/Application/Command/UpdatePurchaseOrderLineHandlerTest.php                               [NEW]
  - tests/Context/Procurement/Unit/Application/Command/RemovePurchaseOrderLineHandlerTest.php                               [NEW]
  - tests/Context/Procurement/Unit/Application/Command/SubmitPurchaseOrderHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Command/ConfirmPurchaseOrderHandlerTest.php                                  [NEW]
  - tests/Context/Procurement/Unit/Application/Command/CancelPurchaseOrderLineHandlerTest.php                               [NEW]
  - tests/Context/Procurement/Unit/Application/Command/ClosePurchaseOrderHandlerTest.php                                    [NEW]
  - tests/Context/Procurement/Unit/Application/Command/CancelPurchaseOrderHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Command/CreateSupplierReceiptHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Command/MatchManualDeliveryHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Command/PollSupplierDeliveriesHandlerTest.php                                [NEW]
  # Tests — Application Unit (Query Handlers)
  - tests/Context/Procurement/Unit/Application/Query/ListSuppliersHandlerTest.php                                           [NEW]
  - tests/Context/Procurement/Unit/Application/Query/GetSupplierDetailHandlerTest.php                                       [NEW]
  - tests/Context/Procurement/Unit/Application/Query/ListSupplierAccountsByClinicHandlerTest.php                            [NEW]
  - tests/Context/Procurement/Unit/Application/Query/SearchSupplierCatalogHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Query/GetSupplierPricingForClinicHandlerTest.php                             [NEW]
  - tests/Context/Procurement/Unit/Application/Query/ResolveEffectivePriceHandlerTest.php                                   [NEW]
  - tests/Context/Procurement/Unit/Application/Query/ListPurchaseOrdersByClinicHandlerTest.php                              [NEW]
  - tests/Context/Procurement/Unit/Application/Query/GetPurchaseOrderDetailHandlerTest.php                                  [NEW]
  - tests/Context/Procurement/Unit/Application/Query/ListPendingReceiptsHandlerTest.php                                     [NEW]
  - tests/Context/Procurement/Unit/Application/Query/SearchUnmatchedDeliveriesHandlerTest.php                               [NEW]
  - tests/Context/Procurement/Unit/Application/Query/ListPurchaseOrderHistoryHandlerTest.php                                 [NEW]
  - tests/Context/Procurement/Unit/Application/Query/GetStaleOpenPurchaseOrdersHandlerTest.php                              [NEW]
  # Tests — Application Unit (Services)
  - tests/Context/Procurement/Unit/Application/Service/SupplierIntegrationDispatcherTest.php                                [NEW]
  - tests/Context/Procurement/Unit/Application/Service/IncomingDeliveryProcessorTest.php                                    [NEW]
  - tests/Context/Procurement/Unit/Application/Service/PurchaseOrderTotalsCalculatorTest.php                                [NEW]
  # Tests — Infrastructure
  - tests/Context/Procurement/Integration/Infrastructure/CleansSimulationTables.php                                         [NEW — trait for tearDown DELETE FROM simulated tables]
  # Shared — File Storage (prerequisite for Phase B)
  - src/Shared/Domain/Storage/FileStorageInterface.php                                                                       [NEW — prerequisite story chore/shared-file-storage]
  - src/Shared/Infrastructure/Storage/LocalFileSystemStorage.php                                                             [NEW — prerequisite story chore/shared-file-storage]
code_patterns:
  - 'AggregateRoot extends App\Shared\Domain\Aggregate\AggregateRoot; domain events collected via recordEvent()'
  - 'Named constructors: create() records events; reconstitute() NEVER calls recordDomainEvent()'
  - 'All mutation methods accept \DateTimeImmutable $updatedAt as last parameter'
  - 'final readonly class for ALL domain events, integration events, and VOs'
  - 'Handlers: final readonly class when all deps injected via constructor'
  - 'IDs extend AbstractUuidId; fromString() validates UUIDv7 regex; accessor: toString()'
  - 'ID generation in handlers: UuidGeneratorInterface::generate() — NEVER Uuid::v7() directly. ReceiveStockHandler uses Uuid::v7() directly — this is a LEGACY DEVIATION. Do NOT follow it. UuidGeneratorInterface::generate() is the mandatory pattern for all new handlers in Procurement.'
  - 'Local opaque VOs (ClinicId, ArticleId): final class extending AbstractUuidId, fromString() no regex'
  - 'Money: App\Shared\Money\Domain\ValueObject\Money — NO add()/multiply() methods; use bcmul/bcadd on minorUnits directly'
  - 'Doctrine entities: Uuid $id with UuidType::NAME from Symfony\Bridge\Doctrine\Types\UuidType (NOT binary/string)'
  - 'Mapper converts: Uuid::fromString(domain->toString()) ↔ entity->getId()->toString()'
  - 'All enums: backed string; match on enum = no default branch (PHPStan enforces exhaustiveness)'
  - 'Commands: implement CommandInterface; Queries: implement QueryInterface; Handlers: #[AsMessageHandler]'
  - 'Transaction: wrapInTransaction{save} → domainEventPublisher->publish() AFTER → integrationEventPublisher->publish() AFTER'
  - 'Optimistic locking: #[ORM\Version] on entity int column; version: int on aggregate; mapper round-trips it'
  - 'Doctrine: PHP attributes only; auto_mapping: false → must add explicit mapping entry in doctrine.yaml'
  - 'Integration events: extend AbstractIntegrationEvent; live in emitting BC Domain/*/Event/'
  - 'VO accessors: ->toString() NEVER ->value(); null check: null === $x NEVER is_null($x)'
  - 'UnitOfMeasure code regex: ^[A-Z][A-Z_]{0,16}$ (e.g. UNITE, ML, FLACON, KG)'
test_patterns:
  - 'PHPUnit 11; failOnDeprecation/Notice/Warning active — zero warnings allowed'
  - 'Unit: pure PHP, mock repositories, no database'
  - 'Integration: KernelTestCase + real MySQL database; Foundry v2 PersistentProxyObjectFactory'
  - '100% line coverage required on Domain + Application layers'
  - 'Foundry factories build from Doctrine entities (not domain aggregates)'
---

# Tech-Spec: Context/Procurement Bounded Context — Complete Implementation

**Created:** 2026-05-25

## Overview

### Problem Statement

The Kiveto platform has no procurement layer. Clinics cannot manage supplier relationships, issue purchase orders, or track deliveries. Stock in Inventory can only be received via direct manual commands with zero traceability to supplier orders. The gap between "what we ordered" and "what arrived in stock" is invisible.

### Solution

Implement the `Context/Procurement` bounded context from scratch — 6 aggregate roots (Supplier, SupplierAccount, SupplierCatalogEntry, SupplierPricing, PurchaseOrder, SupplierReceipt), a pluggable `SupplierIntegrationAdapterInterface` abstraction (SIMULATION / MANUAL_EXPORT / AUTOMATIC modes), a complete DRAFT → CLOSED PO workflow with partial reception, and a 5-command end-to-end demo bootstrap pipeline. When a receipt is validated, Procurement emits `SupplierReceiptCompletedIntegrationEvent` which Inventory consumes autonomously to credit stock — no direct coupling.

### Scope

**In Scope:**
- 6 aggregate roots with full lifecycle and events
- `Address` shared intra-BC VO (billing + delivery addresses on SupplierAccount and PurchaseOrder)
- PriceResolver domain service + EffectivePrice VO
- PurchaseOrderNumber sequential generation (SELECT FOR UPDATE on sequence table)
- PurchaseOrder workflow: DRAFT → SUBMITTED → CONFIRMED → PARTIALLY_RECEIVED → RECEIVED → CLOSED (+ CANCELLED branches)
- Partial reception: 1 PO → N SupplierReceipts; close with auto-cancel of remaining lines
- SupplierIntegrationAdapterInterface with SimulatedSupplierAdapter (3 profiles: DEMO_FAST, STAGING_REALISTIC, DEV_INSTANT) and ManualExportAdapter (Centravet CSV, Alcyon CSV, Generic CSV)
- Cross-BC integration: `SupplierReceiptCompletedIntegrationEvent` (integration event) + `SupplierReceiptCompleted` (domain event); Inventory side out of scope
- IncomingDeliveryProcessor application service (called by PollSupplierDeliveries handler)
- 12 tables (8 with Doctrine ORM entities + 4 managed via raw DBAL) with FULLTEXT indexes on supplier name and catalog entry name
- 24 application commands, 12 queries, 8 read repositories (CQRS lite)
- 3 operational console commands + 5 demo bootstrap commands
- Simulated catalog YAML files (centravet, alcyon, hippocampe, ~100 products each)
- Foundry v2 factories + stories
- 100% line coverage (Domain + Application)

**Out of Scope:**
- `GeneratePurchaseOrderPdf` / Gotenberg integration (deferred, separate story)
- `pdfFileId` field kept nullable on PurchaseOrder domain aggregate as a placeholder — no command for it
- Inventory-side `SupplierReceiptCompletedIntegrationEvent` consumer (Inventory BC concern)
- Supplier dispute management (SupplierClaim)
- RFQ / quotes
- Supplier invoices (future Billing BC)
- Automatic restock from Inventory thresholds
- Multi-site within a clinic
- Historical price tracking on SupplierPricing (overwrite in place; audit via events)
- Auto-close of stale POs (CloseStaleOrdersCommand surfaces alerts only, no auto-action)
- Credentials storage for AUTOMATIC adapters
- Any concrete AUTOMATIC adapter (CentravetFtpAdapter etc.)
- Presentation layer / Twig UI

## Context for Development

### Codebase Patterns

**Money**: `App\Shared\Money\Domain\ValueObject\Money` — stored as BIGINT minor units in DB, never float. See `src/Shared/Money/Domain/ValueObject/Money.php`.

**Local opaque VOs** (`ClinicId`, `ArticleId`): final class extending `AbstractUuidId`, `fromString()` with no regex (UUID format validated by the originating BC). Pattern from `src/Context/Catalog/Domain/ValueObject/ClinicId.php`.

**Aggregate IDs**: final class extending `AbstractUuidId`, `fromString()` validates UUIDv7 regex `'/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'`. Pattern from `src/Context/Inventory/Domain/StockItem/ValueObject/StockItemId.php`.

**Handler transaction + event pattern** (exactly as in ReceiveStockHandler):
```php
$this->entityManager->wrapInTransaction(function () use ($aggregate): void {
    $this->repository->save($aggregate);
});
$this->domainEventPublisher->publish($aggregate);
// if cross-BC: $this->integrationEventPublisher->publish(new FooIntegrationEvent(...));
```

**Integration events**: extend `AbstractIntegrationEvent`, live in the *emitting* BC's `Domain/*/Event/`. Consumer lives in consuming BC's `Infrastructure/Messaging/Consumer/`. Pattern from `src/Context/Client/Domain/Event/ClientArchivedIntegrationEvent.php` + `src/Context/Animal/Infrastructure/Messaging/Consumer/ClientArchivedIntegrationEventConsumer.php`.

**SupplierIntegrationAdapterInterface**: lives in `Application/Port/` (not `Domain/`). Adapters are Infrastructure. The interface uses domain objects (PurchaseOrder, Supplier, SupplierAccount) as parameters — allowed since adapters are in the same BC's infrastructure.

**SubmitPurchaseOrder special case**: handler uses two-phase pattern with SUBMITTING/SEND_FAILED states. 1) `wrapInTransaction{$po->markAsSubmitting() → save}` → publish. 2) OUTSIDE transaction: `$adapter->sendOrder()`. 3a) On success: `wrapInTransaction{$po->submit() → save}` → publish. 3b) On failure: `wrapInTransaction{$po->markAsSendingFailed() → save}` → publish. PO never stays silently in an ambiguous state — failure is recorded as SEND_FAILED.

**ValidateSupplierReceipt special case**: handler must also update PO line `receivedQuantity` within the SAME transaction. Pattern:
```php
$this->entityManager->wrapInTransaction(function() use ($receipt, $po): void {
    $receipt->validate($validatedAt, $now);
    // update PO lines' receivedQuantity from receipt lines
    $po->recordPartialReception(...) or $po->recordFullReception(...);
    $this->receiptRepository->save($receipt);
    $this->purchaseOrderRepository->save($po);
});
$this->domainEventPublisher->publish($receipt); $this->domainEventPublisher->publish($po);
$this->integrationEventPublisher->publish(new SupplierReceiptCompletedIntegrationEvent(...));
```

**PurchaseOrderNumberGeneratorInterface** (Domain/PurchaseOrder/Service/) — interface with method `next(ClinicId $clinicId, int $year): PurchaseOrderNumber`. Implemented by `DoctrinePurchaseOrderNumberSequenceRepository`. Uses `SELECT FOR UPDATE` on `procurement__purchase_order_number_sequences` table. Format: `PO-{YYYY}-{NNNNNN}` (e.g. `PO-2026-000142`). No clinic_short in the format. In `services.yaml`: alias `PurchaseOrderNumberGeneratorInterface` → `DoctrinePurchaseOrderNumberSequenceRepository`.

**Doctrine version / optimistic locking**: `#[Version]` attribute on the entity's `version` field (int); the domain aggregate stores `version: int` and `reconstitute()` sets it; mapper round-trips it. Only `PurchaseOrder` and `SupplierAccount` need optimistic locking (per arch doc — actually all mutable aggregates, i.e. all except SupplierReceipt post-VALIDATED).

**FULLTEXT indexes**: `#[Index(columns: ['name'], flags: ['fulltext'])]` on SupplierEntity and SupplierCatalogEntryEntity. MySQL 8.0+ required (already in CI per arch doc).

**Address VO** (intra-BC shared): replaces `DeliveryAddress` from the arch doc. A pure value object with nullable fields. Used as `billingAddress: ?Address` and `defaultDeliveryAddress: ?Address` on SupplierAccount, and as `deliveryAddress: Address` (required) on PurchaseOrder. Stored as JSON columns in DB.

**SimulatedSupplierAdapter tables**: `procurement__simulated_orders` and `procurement__simulated_deliveries` are pure technical tables — no domain aggregate, no mapper. The adapter writes/reads them directly via an injected `Doctrine\DBAL\Connection` (raw DBAL). **Do NOT use `EntityManagerInterface::getConnection()`** — that method is removed in Doctrine DBAL 4. Inject `Connection` directly in the constructor.

**SupplierContact VO**: embedded in Supplier. Stored as flat columns on `procurement__suppliers` (not JSON). See DB schema section.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Inventory/Application/Command/ReceiveStock/ReceiveStockHandler.php` | Canonical wrapInTransaction + publish pattern |
| `src/Context/Inventory/Domain/StockItem/ValueObject/StockItemId.php` | UUIDv7 ID VO pattern with regex |
| `src/Context/Catalog/Domain/ValueObject/ClinicId.php` | Opaque local VO (no regex) |
| `src/Context/Client/Domain/Event/ClientArchivedIntegrationEvent.php` | Integration event pattern |
| `src/Context/Animal/Infrastructure/Messaging/Consumer/ClientArchivedIntegrationEventConsumer.php` | Integration event consumer pattern |
| `src/Shared/Money/Domain/ValueObject/Money.php` | Money VO |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | AggregateRoot base class |
| `src/Shared/Domain/Identifier/AbstractUuidId.php` | AbstractUuidId base class |
| `src/Shared/Application/Event/IntegrationEventPublisher.php` | Cross-BC event publisher |
| `src/Context/Inventory/Infrastructure/Persistence/Doctrine/Entity/StockItemEntity.php` | Doctrine entity with BoundedContextPrefixNamingStrategy, #[Version] |
| `src/Context/Inventory/Infrastructure/Persistence/Doctrine/Mapper/StockItemMapper.php` | Mapper domain ↔ entity pattern |
| `_bmad-output/implementation-artifacts/tech-spec-context-inventory-bc.md` | Prior BC spec for patterns reference |

### Critical Implementation Details (from Step 2 investigation)

**Money has NO `add()` / `multiply()` methods.** All arithmetic must use raw `minorUnits` + `bcmul`/`bcadd` directly:

```php
// PurchaseOrderLine::lineTotal()
public function lineTotal(): Money
{
    $totalMinor = (int) bcmul(
        (string) $this->unitPrice->minorUnits(),
        $this->orderedQuantity->amount(),
        0
    );
    return Money::fromMinorUnits($totalMinor, $this->unitPrice->currency());
}

// PurchaseOrder::totalAmount()
public function totalAmount(): Money
{
    $total = 0;
    $currency = null;
    foreach ($this->lines as $line) {
        if (PurchaseOrderLineStatus::CANCELLED === $line->status()) {
            continue;
        }
        $total += $line->lineTotal()->minorUnits();
        $currency ??= $line->unitPrice()->currency();
    }
    // fallback currency guard: all lines must share the same currency (validated at addLine)
    return Money::fromMinorUnits($total, $currency ?? CurrencyCode::fromString('EUR'));
}
```

**Doctrine entities use `Uuid` (Symfony UID), NOT `string` or `BINARY(16)`:**
```php
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Column(type: UuidType::NAME)]
private Uuid $id;
```
Mapper converts: `Uuid::fromString($domain->id()->toString())` and `$entity->getId()->toString()`.

**`doctrine.yaml` requires an explicit mapping entry** (`auto_mapping: false`). Add under `mappings:`:
```yaml
Procurement:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/Context/Procurement/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity'
    alias: Procurement
```

**UUID generation in handlers**: use `UuidGeneratorInterface::generate()` (NOT `Uuid::v7()->toRfc4122()`). Inject `UuidGeneratorInterface` in all handlers that create aggregates. Preserves testability.

**`reconstitute()` MUST NEVER call `recordDomainEvent()`** (project-context rule, critical). Only `create()` records events.

**DAMA DoctrineTestBundle**: integration tests are automatically wrapped in rolled-back transactions — never add manual `tearDown()` with `TRUNCATE`/`DELETE` for Doctrine-managed tables. Exception: raw DBAL writes (e.g. in `SimulatedSupplierAdapter`) are NOT wrapped — use the `CleansSimulationTables` trait in `tearDown()` for tests involving `procurement__simulated_orders` / `procurement__simulated_deliveries`.

**Exhaustive enum `match`**: no `default` branch — PHPStan enforces exhaustiveness.

**Handler class form**: `final readonly class FooHandler` when all dependencies are injected via constructor.

**Forbidden in production code**: `is_null()` → use `null ===`; `dd`, `var_dump`, `dump`; `array()` → use `[]`.

**UnitOfMeasure regex** (simulated YAML must respect): `^[A-Z][A-Z_]{0,16}$` — so units must be like `UNITE`, `ML`, `FLACON`, `BOITE`, `KG`, `G`.

### Technical Decisions

1. **`Address` VO** (not `DeliveryAddress`): variable names are `deliveryAddress`, `billingAddress`, `defaultDeliveryAddress` — the VO class is `Address`. Stored as JSON column in DB.

2. **`SupplierIntegrationAdapterInterface`** lives in `Application/Port/` (not `Domain/`): adapters are infrastructure, the interface is an application port. The interface uses domain objects — allowed because all are in the same BC.

3. **No `DispatchReceiptCompletedToInventory` EventHandler in Procurement**: `ValidateSupplierReceiptHandler` publishes `SupplierReceiptCompletedIntegrationEvent` directly via `IntegrationEventPublisher`. Inventory BC autonomously consumes it.

4. **`SupplierReceiptCompletedIntegrationEvent`** carries: `receiptId`, `clinicId`, `supplierId`, `purchaseOrderId`, for each line: `purchaseOrderLineId`, `articleId`, `receivedAmount`, `receivedUnit`, `lotNumber` (nullable), `expiryDate` (nullable), `manufacturedAt` (nullable), `actualUnitPriceMinor` (nullable), `actualUnitPriceCurrency` (nullable). This is the published contract for Inventory.

5. **`ProcessIncomingDelivery` → `IncomingDeliveryProcessor`**: renamed to an Application Service (not EventHandler). Called synchronously from `PollSupplierDeliveriesHandler` after `adapter->fetchDeliveries()` returns results. Matches by `externalReference` against open POs; on match, dispatches `CreateSupplierReceipt` command; on no match, inserts into `procurement__unmatched_deliveries` via DBAL.

6. **`pdfFileId`** field: kept as nullable string on PurchaseOrder aggregate (placeholder). No command or handler for it in this spec.

7. **Optimistic locking scope**: all 6 mutable aggregates carry a `version: int` field and `#[Version]` on entity, except `SupplierReceipt` (becomes immutable after VALIDATED — but still has version for the PENDING_REVIEW mutation window).

8. **Simulated catalog YAML** location: `resources/simulated-catalogs/{supplier_code}.yaml`. Each file has ~100 product entries. Loaded by `SimulatedSupplierAdapter::fetchCatalog()` via Symfony's `kernel.project_dir` parameter.

9. **Demo commands are NOT idempotent at fixture level** — they check for existence via a lookup before creating (e.g. skip if Supplier with code "CENTRAVET" already exists). Not transactional but safe to re-run.

10. **`CloseStaleOrdersCommand`** does NOT close POs — it only surfaces them via console output/log. The actual `ClosePurchaseOrder` command is manual.

11. **DISCONTINUED constraint** lives in `AddPurchaseOrderLineHandler` (Application), not in the `PurchaseOrder` domain aggregate. The aggregate has no knowledge of catalog status — that concern belongs to the application layer which loads and checks the `SupplierCatalogEntry`.

12. **E2E demo test strategy**: `DemoBootstrapPipelineTest` uses real CommandBus dispatches across BCs (no mocking). If a required external BC command (`CreateClinic`, `ApplyStarterCatalog`, `ConsumeStock`) is not found at test setup time, the test calls `$this->markTestSkipped()`. This avoids fragile mocks while gracefully handling partial BC availability.

13. **`SimulatedSupplierAdapter` and `IncomingDeliveryProcessor`** both inject `Doctrine\DBAL\Connection` directly (NOT `EntityManagerInterface::getConnection()` — removed in DBAL 4). Add `Doctrine\DBAL\Connection: ~` or autowire by type in `services.yaml`.

14. **`PurchaseOrderLine::recordReception(Quantity $qty)`** must validate `receivedQuantity + $qty <= orderedQuantity` and throw `ReceiptQuantityExceedsOrderedException` (in `Application/Exception/`) if violated; and throw `CancelledLineCannotReceiveException` if line status is CANCELLED.

## Implementation Plan

### Tasks

Implementation follows the dependency order from the architecture document. Each task group must be complete (including tests) before starting the next.

---

#### TASK GROUP 1 — Shared VOs + Config

**T1.1** `src/Context/Procurement/Domain/Shared/ValueObject/ClinicId.php`
- final class extending AbstractUuidId; fromString() without UUID validation (opaque); equals() from parent; toString() from parent.

**T1.2** `src/Context/Procurement/Domain/Shared/ValueObject/ArticleId.php`
- Same pattern as ClinicId.

**T1.3** ~~`PurchaseOrderRef`~~ — REMOVED. Use `PurchaseOrderId` directly wherever a receipt references its originating PO.

**T1.4** `src/Context/Procurement/Domain/Shared/ValueObject/Address.php`
- final readonly class; fields: `street: ?string`, `addressLine2: ?string`, `postalCode: ?string`, `city: ?string`, `countryCode: ?CountryCode` (App\Shared\Domain\ValueObject\CountryCode).
- Named constructor: `Address::create(?string $street, ?string $addressLine2, ?string $postalCode, ?string $city, ?CountryCode $countryCode): self`
- `toArray(): array` for JSON serialization; `fromArray(array $data): self` for deserialization from JSON column.

**T1.5** `config/packages/doctrine.yaml`
- Add `App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity` to entity mappings under the `procurement` mapping key.
- Verify `BoundedContextPrefixNamingStrategy` covers `procurement` prefix (check existing config; if mapping is auto-detected, just adding the entity namespace suffices).

**T1.6** `config/services.yaml`
- Tag `SimulatedSupplierAdapter` and `ManualExportAdapter` concretions with a `procurement.supplier_adapter` tag for `SupplierIntegrationDispatcher` to collect them.
- Register `ClinicProviderInterface` → `ClinicProviderAdapter` and `ArticleProviderInterface` → `CatalogArticleProviderAdapter` aliases.
- Alias `PurchaseOrderNumberGeneratorInterface` → `DoctrinePurchaseOrderNumberSequenceRepository`.

---

#### TASK GROUP 2 — Supplier Aggregate + Tests

**T2.1** VOs: `SupplierId`, `SupplierName`, `SupplierCode`, `SupplierType`, `SupplierIntegrationMode`, `SupplierStatus`, `SupplierContact`
- `SupplierId`: extends AbstractUuidId, fromString() validates UUIDv7 regex.
- `SupplierName`: final readonly, non-empty string max 255, `toString()`.
- `SupplierCode`: final readonly, validates regex `^[A-Z0-9_-]{2,32}$`, `toString()`.
- `SupplierType`: backed string enum — CENTRALE, LABORATORY, DIVERS.
- `SupplierIntegrationMode`: backed string enum — AUTOMATIC, MANUAL_EXPORT, SIMULATION.
- `SupplierStatus`: backed string enum — ACTIVE, ARCHIVED.
- `SupplierContact`: final readonly class; fields: `email: ?string`, `phone: ?string`, `contactPerson: ?string`, `address: ?Address` (uses `App\Context\Procurement\Domain\Shared\ValueObject\Address`). Named constructor `SupplierContact::create(...)`. In SupplierEntity (T8.1): SupplierContact.address is stored as flat columns (`address_street`, `address_line2`, `postal_code`, `city`, `address_country_code`) — SupplierMapper hydrates Address from these columns and injects into SupplierContact.

**T2.2** Domain events (final readonly, extend AbstractDomainEvent, BOUNDED_CONTEXT='procurement', VERSION=1):
- `SupplierRegistered`: supplierId, name, code, type, countryCode, integrationMode, adapterIdentifier (nullable).
- `SupplierRenamed`: supplierId, oldName, newName.
- `SupplierIntegrationModeChanged`: supplierId, newMode, newAdapterIdentifier (nullable).
- `SupplierArchived`: supplierId.

**T2.3** Exceptions: `SupplierNotFoundException`, `DuplicateSupplierCodeException`, `ArchivedSupplierException`.

**T2.4** `SupplierRepositoryInterface`: `save(Supplier): void`; `findById(SupplierId): ?Supplier`; `findByCode(SupplierCode): ?Supplier`; `findAll(): array`.

**T2.5** `Supplier.php` aggregate:
- Fields: `id: SupplierId`, `name: SupplierName`, `code: SupplierCode`, `type: SupplierType`, `countryCode: CountryCode`, `defaultCurrency: CurrencyCode`, `integrationMode: SupplierIntegrationMode`, `adapterIdentifier: ?string`, `contact: ?SupplierContact`, `status: SupplierStatus`, `version: int`, `createdAt: \DateTimeImmutable`, `updatedAt: \DateTimeImmutable`.
- `register()` named constructor → raises SupplierRegistered.
- `rename()`: throws ArchivedSupplierException if ARCHIVED; raises SupplierRenamed.
- `changeIntegrationMode()`: if AUTOMATIC and adapterIdentifier null → throw \InvalidArgumentException; raises SupplierIntegrationModeChanged.
- `updateContact()`: no event (not critical path).
- `archive()`: throws ArchivedSupplierException if already ARCHIVED; raises SupplierArchived.
- `reconstitute()`: static factory for hydration from persistence, no events raised.

**T2.6** `tests/Context/Procurement/Unit/Domain/Supplier/SupplierTest.php`:
- register() raises SupplierRegistered with correct payload.
- rename() on ARCHIVED throws ArchivedSupplierException.
- changeIntegrationMode(AUTOMATIC, null) throws exception.
- changeIntegrationMode(AUTOMATIC, 'centravet_ftp') succeeds, raises SupplierIntegrationModeChanged.
- archive() → status ARCHIVED; second archive() throws ArchivedSupplierException.
- SupplierCode rejects invalid format (e.g. 'a', 'too long string', 'invalid chars').

---

#### TASK GROUP 3 — SupplierAccount Aggregate + Tests

**T3.1** VOs: `SupplierAccountId`, `CustomerCode`, `SupplierAccountStatus`
- `SupplierAccountId`: UUIDv7 regex.
- `CustomerCode`: non-empty string max 64 chars, `toString()`.
- `SupplierAccountStatus`: backed string enum — ACTIVE, DISABLED.

**T3.2** Events: `SupplierAccountCreated`, `SupplierAccountUpdated`, `SupplierAccountDisabled`, `SupplierAccountEnabled`.
- All carry: accountId, clinicId, supplierId.

**T3.3** Exceptions: `SupplierAccountNotFoundException`, `DuplicateSupplierAccountException`.

**T3.4** `SupplierAccountRepositoryInterface`: `save(SupplierAccount): void`; `findById(SupplierAccountId): ?SupplierAccount`; `findByClinicAndSupplier(ClinicId, SupplierId): ?SupplierAccount`.

**T3.5** `SupplierAccount.php` aggregate.

**T3.6** `tests/Context/Procurement/Unit/Domain/SupplierAccount/SupplierAccountTest.php`.

---

#### TASK GROUP 4 — SupplierCatalogEntry Aggregate + Tests

**T4.1** VOs: `SupplierCatalogEntryId`, `SupplierProductCode`, `SupplierProductName`, `Gtin`, `CatalogPrice`, `SupplierCatalogEntryStatus`
- `Gtin`: 8, 12, 13, or 14 digit string validation.
- `CatalogPrice`: final readonly; `amount: Money`, `validFrom: \DateTimeImmutable`, `validTo: ?\DateTimeImmutable`; invariant validFrom <= validTo (when validTo is not null); `isValidAt(\DateTimeImmutable $date): bool` — returns `true` when `$date >= $validFrom && ($validTo === null || $date <= $validTo)`. If `validTo` is null, the price has no expiry (forever valid).
- `SupplierCatalogEntryStatus`: backed string enum — ACTIVE, DISCONTINUED.

**T4.2** Events: `SupplierCatalogEntryAdded`, `SupplierCatalogEntryUpdated`, `SupplierCatalogEntryDiscontinued`, `SupplierCatalogPriceChanged`.

**T4.3** Exceptions: `SupplierCatalogEntryNotFoundException`, `DuplicateSupplierProductCodeException`.

**T4.4** `SupplierCatalogEntryRepositoryInterface`: `save(SupplierCatalogEntry): void`; `findById(SupplierCatalogEntryId): ?SupplierCatalogEntry`; `findBySupplierAndCode(SupplierId, SupplierProductCode): ?SupplierCatalogEntry`.

**T4.5** `SupplierCatalogEntry.php` aggregate.
- `updatePrice()` raises `SupplierCatalogPriceChanged` (audit trail).
- `discontinue()` raises `SupplierCatalogEntryDiscontinued`.
- The aggregate itself does NOT enforce "DISCONTINUED refuses new order lines" — the domain does not know about POs. This constraint lives in **`AddPurchaseOrderLineHandler`** (Application layer): the handler loads the `SupplierCatalogEntry` via `SupplierCatalogReadRepositoryInterface`, checks `status === DISCONTINUED`, and throws `DiscontinuedCatalogEntryException` (from `Application/Exception/`) before calling `$po->addLine()`.

**T4.6** `tests/Context/Procurement/Unit/Domain/SupplierCatalog/SupplierCatalogEntryTest.php`.

---

#### TASK GROUP 5 — SupplierPricing Aggregate + PriceResolver + Tests

**T5.1** VOs: `SupplierPricingId`, `NegotiatedPrice`, `SupplierPricingSource`, `EffectivePrice`
- `NegotiatedPrice`: final readonly; `amount: Money`, `discountPercentage: ?string` (e.g. "5.00"), `notes: ?string`; amount must be > 0 minor units.
- `SupplierPricingSource`: backed string enum — CATALOG_DEFAULT, NEGOTIATED, IMPORTED.
- `EffectivePrice`: final readonly; `amount: Money`, `source: SupplierPricingSource`, `resolvedAt: \DateTimeImmutable`.

**T5.2** `NoEffectivePriceException`: thrown by PriceResolver when neither negotiated nor catalog price is valid.

**T5.3** Events: `SupplierPricingNegotiated`, `SupplierPricingUpdated`, `SupplierPricingRemoved`.

**T5.4** `SupplierPricingRepositoryInterface`: `save(SupplierPricing): void`; `findById(SupplierPricingId): ?SupplierPricing`; `findByClinicAndEntry(ClinicId, SupplierCatalogEntryId): ?SupplierPricing`.

**T5.5** `SupplierPricing.php` aggregate.
- Explicit fields include: `negotiatedAt: \DateTimeImmutable` — timestamp when the price was negotiated. In DB: `negotiated_at DATETIME NOT NULL`.
- Invariant on `negotiate()`: expiresAt > negotiatedAt if not null; currency consistency check is deferred to handler (needs Supplier loaded).
- `remove()` method: raises SupplierPricingRemoved then marks internal `_removed = true` — handler removes from repository.

**T5.6** `PriceResolver.php` domain service (pure, no I/O):
```php
resolve(?SupplierPricing $pricing, SupplierCatalogEntry $entry, \DateTimeImmutable $referenceDate): EffectivePrice
```
Algorithm:
1. If `$pricing !== null` AND `$pricing->expiresAt() === null OR $pricing->expiresAt() > $referenceDate` → return EffectivePrice(pricing.negotiatedPrice.amount, NEGOTIATED, $referenceDate).
2. Else if `$entry->catalogPrice()->isValidAt($referenceDate)` → return EffectivePrice(catalogPrice.amount, CATALOG_DEFAULT, $referenceDate).
3. Else → throw NoEffectivePriceException.

**T5.7** `tests/Context/Procurement/Unit/Domain/SupplierPricing/SupplierPricingTest.php`.
- Add test: "expiresAt ≤ negotiatedAt throws exception".

**T5.8** `tests/Context/Procurement/Unit/Domain/SupplierPricing/PriceResolverTest.php`:
- Non-expired negotiated pricing → NEGOTIATED result.
- Expired negotiated pricing + valid catalog → CATALOG_DEFAULT result.
- Null pricing + valid catalog → CATALOG_DEFAULT result.
- Null pricing + expired catalog → NoEffectivePriceException.
- Negotiated pricing with null expiresAt → treated as never expires → NEGOTIATED.
- Null pricing + entry with validTo=null + any future date → CATALOG_DEFAULT (forever valid).

---

#### TASK GROUP 6 — PurchaseOrder Aggregate + Tests

**T6.1** VOs: `PurchaseOrderId`, `PurchaseOrderLineId`, `PurchaseOrderNumber`, `PurchaseOrderStatus`, `PurchaseOrderLineStatus`, `ExternalReference`, `PurchaseOrderTotals`
- `PurchaseOrderStatus`: backed string enum — DRAFT, SUBMITTING (transient — sending to adapter in progress), SUBMITTED, CONFIRMED, PARTIALLY_RECEIVED, RECEIVED, CLOSED, CANCELLED, SEND_FAILED (adapter call failed).
- `PurchaseOrderLineStatus`: backed string enum — ACTIVE, FULLY_RECEIVED, CANCELLED.
- `ExternalReference`: final readonly; `value: string`, `providedBy: string`; both non-empty.
- `PurchaseOrderNumber`: final readonly; wraps formatted string `PO-{YYYY}-{NNNNNN}` (e.g. `PO-2026-000142`); `toString()`. Note: clinic_short is NOT included in the format.
- `PurchaseOrderTotals`: final readonly; `subtotal: Money`; computed, not stored.

**T6.2** `PurchaseOrderLine.php` entity (NOT an aggregate root — child of PurchaseOrder):
- Fields: id, articleId, catalogEntryId, orderedQuantity, unitPrice, receivedQuantity (default 0), status, note.
- `recordReception(Quantity $qty): void`: adds qty to receivedQuantity; if receivedQuantity == orderedQuantity sets status = FULLY_RECEIVED. Throws if CANCELLED.
- `cancel(string $reason): void`: sets status = CANCELLED.
- `lineTotal(): Money`: orderedQuantity * unitPrice (use Money arithmetic).
- No events — events raised on the PurchaseOrder aggregate.

**T6.3** Events (all carry purchaseOrderId + clinicId at minimum):
- `PurchaseOrderCreated`, `PurchaseOrderLineAdded`, `PurchaseOrderLineUpdated`, `PurchaseOrderLineRemoved`, `PurchaseOrderSubmittingStarted` (raised on DRAFT → SUBMITTING), `PurchaseOrderSubmitted` (carries externalReference; raised on SUBMITTING → SUBMITTED), `PurchaseOrderSendFailed` (carries reason; raised on SUBMITTING → SEND_FAILED), `PurchaseOrderConfirmed`, `PurchaseOrderPartiallyReceived`, `PurchaseOrderFullyReceived`, `PurchaseOrderClosed`, `PurchaseOrderCancelled`, `PurchaseOrderLineCancelled`.

**T6.4** Exceptions: `PurchaseOrderNotFoundException`, `InvalidPurchaseOrderStatusTransitionException`, `EmptyPurchaseOrderException`, `PurchaseOrderClosedException`.

**T6.5** `PurchaseOrderRepositoryInterface`: `save(PurchaseOrder): void`; `findById(PurchaseOrderId): ?PurchaseOrder`; `findByClinicAndNumber(ClinicId, PurchaseOrderNumber): ?PurchaseOrder`.

**T6.6** `PurchaseOrder.php` aggregate:
- `create(... CurrencyCode $currency ...)`: status = DRAFT; `currency: CurrencyCode` stored as snapshot of Supplier.defaultCurrency at creation time; raises PurchaseOrderCreated.
- `addLine()`: guard DRAFT; raises PurchaseOrderLineAdded.
- `updateLine()`: guard DRAFT; raises PurchaseOrderLineUpdated.
- `removeLine()`: guard DRAFT; raises PurchaseOrderLineRemoved.
- `markAsSubmitting(\DateTimeImmutable $updatedAt): void`: guard DRAFT or SEND_FAILED; check at least 1 ACTIVE line (EmptyPurchaseOrderException); DRAFT/SEND_FAILED → SUBMITTING; raises PurchaseOrderSubmittingStarted.
- `submit(ExternalReference $ref, \DateTimeImmutable $submittedAt, \DateTimeImmutable $updatedAt): void`: guard SUBMITTING; SUBMITTING → SUBMITTED; raises PurchaseOrderSubmitted.
- `markAsSendingFailed(string $reason, \DateTimeImmutable $updatedAt): void`: guard SUBMITTING; SUBMITTING → SEND_FAILED; raises PurchaseOrderSendFailed.
- `retryReset(\DateTimeImmutable $updatedAt): void`: guard SEND_FAILED; SEND_FAILED → DRAFT.
- `confirm(...)`: guard SUBMITTED; SUBMITTED → CONFIRMED; raises PurchaseOrderConfirmed.
- `recordPartialReception(...)`: guard CONFIRMED or PARTIALLY_RECEIVED; → PARTIALLY_RECEIVED; raises PurchaseOrderPartiallyReceived. Called with the receipt's lines to update line receivedQuantity.
- `recordFullReception(...)`: guard CONFIRMED or PARTIALLY_RECEIVED; → RECEIVED; raises PurchaseOrderFullyReceived.
- `cancelLine(PurchaseOrderLineId, string $reason, ...)`: guard SUBMITTED | CONFIRMED | PARTIALLY_RECEIVED; line.cancel(); totals recalculated; raises PurchaseOrderLineCancelled.
- `close(...)`: guard RECEIVED | PARTIALLY_RECEIVED; if PARTIALLY_RECEIVED: auto-cancel ACTIVE remaining lines; → CLOSED; raises PurchaseOrderClosed.
- `cancel(string $reason, ...)`: guard DRAFT | SUBMITTED | CONFIRMED | SEND_FAILED; throws if PARTIALLY_RECEIVED or RECEIVED; → CANCELLED; raises PurchaseOrderCancelled.
- Any invalid transition → `InvalidPurchaseOrderStatusTransitionException`.
- `totalAmount(): Money` — uses `$this->currency` instead of EUR fallback for the zero-lines case.
- `linesActiveRemaining(): PurchaseOrderLine[]`, `daysSinceSubmission(): int`.
- `version: int` field for optimistic locking.

Status transitions summary:
- DRAFT → SUBMITTING (`markAsSubmitting`)
- SUBMITTING → SUBMITTED (`submit` — on adapter success)
- SUBMITTING → SEND_FAILED (`markAsSendingFailed` — on adapter failure)
- SEND_FAILED → DRAFT (`retryReset` — operator manually resets for retry)
- SEND_FAILED → CANCELLED (`cancel`)
- SUBMITTED → CONFIRMED (`confirm`)
- CONFIRMED / PARTIALLY_RECEIVED → PARTIALLY_RECEIVED / RECEIVED (`recordPartialReception` / `recordFullReception`)
- RECEIVED / PARTIALLY_RECEIVED → CLOSED (`close`)
- DRAFT / SUBMITTED / CONFIRMED / SEND_FAILED → CANCELLED (`cancel`)

**T6.7** `tests/Context/Procurement/Unit/Domain/PurchaseOrder/PurchaseOrderTest.php`:
- addLine, updateLine, removeLine on non-DRAFT status → throws.
- submit with empty lines → EmptyPurchaseOrderException.

**T6.8** `tests/Context/Procurement/Unit/Domain/PurchaseOrder/PurchaseOrderWorkflowTest.php`:
- Full path: DRAFT → SUBMITTING → SUBMITTED → CONFIRMED → PARTIALLY_RECEIVED → RECEIVED → CLOSED.
- SUBMITTING → SEND_FAILED → DRAFT (retryReset) → SUBMITTING → SUBMITTED (retry success path).
- Cancel from DRAFT/SUBMITTED/CONFIRMED/SEND_FAILED → CANCELLED.
- Cancel from PARTIALLY_RECEIVED → throws InvalidPurchaseOrderStatusTransitionException.
- Close from PARTIALLY_RECEIVED → ACTIVE lines auto-cancelled.
- cancelLine on CONFIRMED reduces totalAmount.
- All invalid transitions raise InvalidPurchaseOrderStatusTransitionException.

---

#### TASK GROUP 7 — SupplierReceipt Aggregate + Integration Events + Tests

**T7.1** VOs: `SupplierReceiptId`, `SupplierReceiptLineId`, `DeliveryNoteReference`, `LotInformation`, `ReceiptMatchType`, `SupplierReceiptStatus`
- `DeliveryNoteReference`: final readonly; non-empty string max 128.
- `LotInformation`: final readonly; `lotNumber: string` (max 64), `expiryDate: \DateTimeImmutable`, `manufacturedAt: ?\DateTimeImmutable`.
- `ReceiptMatchType`: backed string enum — AUTO_MATCHED, MANUALLY_MATCHED, MANUALLY_CREATED.
- `SupplierReceiptStatus`: backed string enum — PENDING_REVIEW, VALIDATED.

**T7.2** `SupplierReceiptLine.php` entity:
- Fields: id, purchaseOrderLineId, articleId, receivedQuantity, lotInformation (nullable), actualUnitPrice (nullable Money), note (nullable string).

**T7.3** Events:
- `SupplierReceiptCreated`: receiptId, clinicId, supplierId, `purchaseOrderId: string` (uses PurchaseOrderId, not PurchaseOrderRef — PurchaseOrderRef has been removed), deliveryNoteReference, matchType.
- `SupplierReceiptValidated`: receiptId, clinicId, validatedAt.
- `SupplierReceiptCompleted` (domain event, extends AbstractDomainEvent): same payload as integration event but as a domain event.
- `SupplierReceiptLineAdded`: raised by `SupplierReceipt::addLine()`.
- `SupplierReceiptLineRemoved`: raised by `SupplierReceipt::removeLine()`.

**T7.4** `SupplierReceiptCompletedIntegrationEvent` (extends AbstractIntegrationEvent, BOUNDED_CONTEXT='procurement'):
```php
public function __construct(
    public readonly string $receiptId,
    public readonly string $clinicId,
    public readonly string $supplierId,
    public readonly string $purchaseOrderId,
    /** @var array<int, array{purchaseOrderLineId: string, articleId: string, receivedAmount: string, receivedUnit: string, lotNumber: ?string, expiryDate: ?string, manufacturedAt: ?string, actualUnitPriceMinor: ?int, actualUnitPriceCurrency: ?string}> */
    public readonly array $lines,
)
```
This is the published contract for Inventory to consume.

**T7.5** Exceptions: `SupplierReceiptNotFoundException`, `ReceiptValidationException`.

**T7.6** `SupplierReceiptRepositoryInterface`: `save(SupplierReceipt): void`; `findById(SupplierReceiptId): ?SupplierReceipt`; `findByDeliveryNoteReference(SupplierId, DeliveryNoteReference): ?SupplierReceipt`.

**T7.7** `SupplierReceipt.php` aggregate:
- `create()`: status = PENDING_REVIEW; raises SupplierReceiptCreated.
- `addLine()`: guard PENDING_REVIEW; raises SupplierReceiptLineAdded.
- `removeLine()`: guard PENDING_REVIEW; raises SupplierReceiptLineRemoved.
- `updateComment()`: guard PENDING_REVIEW.
- `validate(\DateTimeImmutable $validatedAt, \DateTimeImmutable $updatedAt)`: guard PENDING_REVIEW; → VALIDATED; raises SupplierReceiptValidated, then SupplierReceiptCompleted.

**T7.8** `tests/Context/Procurement/Unit/Domain/SupplierReceipt/SupplierReceiptTest.php`:
- validate() transitions to VALIDATED; raises both SupplierReceiptValidated and SupplierReceiptCompleted.
- addLine() on VALIDATED → throws ReceiptValidationException.
- removeLine() on VALIDATED → throws ReceiptValidationException.

---

#### TASK GROUP 8 — Persistence: Entities + Mappers + Repositories

**T8.1** Doctrine Entities (8 files, PHP attributes only, `procurement__` tables; note: `procurement__unmatched_deliveries` and simulated tables have no Doctrine entity — managed via raw DBAL):
Each entity mirrors the DB schema from the spec. Key notes:
- `SupplierEntity`: flat columns for SupplierContact fields (not JSON); `#[Index]` for status/type; `#[Index(flags: ['fulltext'])]` for name.
- `SupplierAccountEntity`: `billingAddressJson: ?string` and `deliveryAddressJson: ?string` as TEXT/JSON columns; `#[UniqueConstraint]` on clinic_id+supplier_id; `#[Version]` on version.
- `SupplierCatalogEntryEntity`: catalog_price stored as 3 columns (minor, currency, valid_from) + optional valid_to; FULLTEXT on name; `#[UniqueConstraint]` on supplier_id+supplier_product_code.
- `SupplierPricingEntity`: `#[UniqueConstraint]` on clinic_id+supplier_catalog_entry_id.
- `PurchaseOrderEntity`: delivery_address_json as JSON; external_reference split into 2 columns; `#[Version]` on version. The `lines` collection (`OneToMany` → `PurchaseOrderLineEntity`) **must use `fetch: 'EAGER'`** — same pattern as `StockItemEntity::$lots` — to prevent `EntityManagerClosed` exceptions when the entity is accessed outside the original EM lifecycle (e.g. inside Messenger handlers).
- `PurchaseOrderLineEntity`: `#[ManyToOne]` → `PurchaseOrderEntity` with `#[JoinColumn(onDelete: 'CASCADE')]`; `mappedBy: 'purchaseOrder'`.
- `SupplierReceiptEntity`: `received_by` as nullable `Uuid` column (UuidType::NAME); not FK to User entity. The `lines` collection also uses **EAGER fetch** for same reason.
- `SupplierReceiptLineEntity`: `#[ManyToOne]` → `SupplierReceiptEntity` with CASCADE; lot fields as separate nullable columns.

**T8.2** Mappers (6 files):
Each mapper has `toEntity(Domain, ?Entity): Entity` and `toDomain(Entity): Domain`.
- `SupplierMapper`: maps SupplierContact as embedded flat fields; handles nullable contact.
- `SupplierAccountMapper`: JSON decode/encode for Address.
- `PurchaseOrderMapper`: maps PurchaseOrderLine collection; JSON decode for deliveryAddress; handles nullable ExternalReference.
- `SupplierReceiptMapper`: maps SupplierReceiptLine collection; maps LotInformation from/to lot_* columns.

**T8.3** Write Repositories (6 files, Doctrine*Repository):
- Each implements the corresponding `*RepositoryInterface`.
- `DoctrinePurchaseOrderRepository` must handle `ConcurrentModificationException` (Doctrine OptimisticLockException → wrap in domain exception).
- All use EntityManager->find() + mapper->toDomain() for loading; save() calls mapper->toEntity() then persist().

**T8.4** `DoctrinePurchaseOrderNumberSequenceRepository` (implements `PurchaseOrderNumberGeneratorInterface`):
- Interface method: `next(ClinicId $clinicId, int $year): PurchaseOrderNumber`
- Uses DBAL `SELECT last_number FROM procurement__purchase_order_number_sequences WHERE clinic_id = ? AND year = ? FOR UPDATE`; if not found, inserts row with last_number=0; increments; formats PurchaseOrderNumber as `PO-{YYYY}-{NNNNNN}` (no clinic_short).
- Called by `CreatePurchaseOrderHandler` INSIDE `wrapInTransaction`.

**T8.5** Read Repositories (6 files, implement Application/Port read interfaces):
- Query via DBAL or QueryBuilder, return arrays of scalars (no domain objects).
- `DoctrineSupplierCatalogReadRepository::search(string $term, SupplierId $supplierId): array` uses FULLTEXT MATCH(...) AGAINST(?). If `strlen($term) < 4`: use `LIKE '%{$term}%'` fallback instead of FULLTEXT MATCH AGAINST, to work around MySQL `ft_min_word_len=4` (or `innodb_ft_min_token_size`) setting which silently ignores short tokens.
- `DoctrinePurchaseOrderReadRepository::findStale(int $daysThreshold): array` filters status=PARTIALLY_RECEIVED AND submitted_at < NOW() - INTERVAL $daysThreshold DAY.
- `DoctrineSupplierReceiptReadRepository::findUnmatched(ClinicId $clinicId): array` queries `procurement__unmatched_deliveries`.

**T8.6** Migration `migrations/Procurement/Version<timestamp>.php`:
- Migration should ideally be generated via `bin/console doctrine:migrations:diff` after entities are defined, then reviewed. Hand-written migrations must use column type matching Doctrine's UuidType output: `BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'` for UUID columns. Do NOT use raw BINARY(16) without the comment — Doctrine schema validation will reject the mismatch.
- Creates all 12 tables. The 12 tables are:
  1. `procurement__suppliers`
  2. `procurement__supplier_accounts`
  3. `procurement__supplier_catalog_entries`
  4. `procurement__supplier_pricing`
  5. `procurement__purchase_orders` — add `currency CHAR(3) NOT NULL` column (snapshot of Supplier.defaultCurrency at creation time)
  6. `procurement__purchase_order_lines`
  7. `procurement__supplier_receipts`
  8. `procurement__supplier_receipt_lines`
  9. `procurement__purchase_order_number_sequences`
  10. `procurement__simulated_orders`
  11. `procurement__simulated_deliveries`
  12. `procurement__unmatched_deliveries` (schema below)
- FULLTEXT indexes on `procurement__suppliers.name` and `procurement__supplier_catalog_entries.name`.
- FKs as defined in schema (no FK to User entity — received_by stored as raw BINARY).

`procurement__unmatched_deliveries` schema:
```
id                         BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)' PRIMARY KEY
clinic_id                  BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'
supplier_id                BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'
delivery_note_reference    VARCHAR(128) NOT NULL
raw_payload_json           JSON NOT NULL
received_at                DATETIME NOT NULL
resolved_at                DATETIME NULL
resolved_by                BINARY(16) NULL COMMENT '(DC2Type:uuid)'

INDEX idx_unmatched_clinic_resolved (clinic_id, resolved_at)
UNIQUE INDEX uniq_unmatched_supplier_dnr (supplier_id, delivery_note_reference)
```

**T8.7** Integration tests for mappers (domain ↔ entity round-trip, assert all fields):
- `tests/Context/Procurement/Integration/Persistence/*MapperTest.php` (6 files).

**T8.8** Integration tests for repositories:
- `tests/Context/Procurement/Integration/Persistence/Doctrine*RepositoryTest.php` (6 + 1 number generator files).
- `PurchaseOrderNumberGeneratorTest`: assert sequential numbers with concurrent calls do not duplicate.
- `DoctrinePurchaseOrderRepositoryTest`: test optimistic lock conflict raises ConcurrentModificationException.

---

#### TASK GROUP 9 — Application Ports + DTOs

**T9.1** `SupplierIntegrationAdapterInterface`:
```php
interface SupplierIntegrationAdapterInterface
{
    public function identifier(): string;
    public function mode(): SupplierIntegrationMode;
    public function sendOrder(PurchaseOrder $order, SupplierAccount $account): SendOrderResult;
    /** @return list<DeliveryNoteData> */
    public function fetchDeliveries(Supplier $supplier, SupplierAccount $account, \DateTimeImmutable $since, \DateTimeImmutable $until): array;
    public function supportsAsyncDeliveryPolling(): bool;
    public function supportsCatalogImport(): bool;
    /** @return list<CatalogEntryData> */
    public function fetchCatalog(Supplier $supplier): array;
}
```
`supportsCatalogImport()` behaviour per adapter type:
- AUTOMATIC adapters: return true if they support `fetchCatalog()`.
- MANUAL_EXPORT adapters: return false.
- SIMULATION adapter: return true (reads YAML).

**T9.2** DTOs (final readonly classes, pure PHP):
- `SendOrderResult`: `success: bool`, `externalReference: ?ExternalReference`, `sentAt: \DateTimeImmutable`, `errorMessage: ?string`, `trackingUrl: ?string`. Note: AUTOMATIC adapters → null; MANUAL_EXPORT adapters → download URL of the generated file; SIMULATION adapter → null.
- `DeliveryNoteData`: `deliveryNoteReference: string`, `purchaseOrderExternalReference: string`, `lines: array` (each: articleCode, qty, unit, lotNumber, expiryDate, actualPrice).
- `CatalogEntryData`: `supplierProductCode: string`, `name: string`, `gtin: ?string`, `priceMinor: int`, `currency: string`, `unit: ?string`, `packagingAmount: ?string`.

**T9.3** Read Port interfaces (in `Application/Port/`):
- `ArticleProviderInterface`: `exists(ArticleId, ClinicId): bool`; `isActive(ArticleId, ClinicId): bool`.
- `ClinicProviderInterface`: `getCurrency(ClinicId): string`. Note: `getShortCode()` has been REMOVED — PO number format is now `PO-{YYYY}-{NNNNNN}` without clinic_short. `ClinicProviderAdapter` should NOT query for short code.
- Read repository interfaces for 5 aggregates: define query methods matching what handlers need.

---

#### TASK GROUP 10 — Application Services + Command Handlers

**T10.0** Application Exceptions (9 files in `Application/Exception/`):
- `ReceiptQuantityExceedsOrderedException`: thrown by `PurchaseOrderLine::recordReception()` when received qty would exceed ordered qty.
- `CancelledLineCannotReceiveException`: thrown when attempting reception on a CANCELLED line.
- `PurchaseOrderClosedOrCancelledException`: thrown by `ValidateSupplierReceiptHandler` when PO status is CLOSED or CANCELLED.
- `DiscontinuedCatalogEntryException`: thrown by `AddPurchaseOrderLineHandler` when catalog entry is DISCONTINUED.
- `SupplierAccountDisabledException`: thrown by `CreatePurchaseOrderHandler` / `SubmitPurchaseOrderHandler` when account is DISABLED.
- `CatalogImportNotSupportedException`: thrown by `ImportSupplierCatalogHandler` when adapter returns `supportsCatalogImport() === false`.
- `UnmatchedDeliveryNotFoundException`: thrown by `MatchManualDeliveryHandler` when the unmatched delivery row is not found.
- `UnmatchedDeliveryAlreadyResolvedException`: thrown by `MatchManualDeliveryHandler` when `resolved_at` is already set.
- `ClinicSupplierMismatchException`: thrown by `MatchManualDeliveryHandler` when PO clinic or supplier does not match the delivery.

**T10.1** `SupplierIntegrationDispatcher`:
- Holds `iterable<SupplierIntegrationAdapterInterface> $adapters` (tagged).
- `dispatch(Supplier $supplier): SupplierIntegrationAdapterInterface`: finds adapter by `identifier()` matching `$supplier->adapterIdentifier()`. If not found and mode=SIMULATION, fallback to SimulatedSupplierAdapter. If still not found, throws \RuntimeException.

**T10.2** `PurchaseOrderTotalsCalculator`: sums line totals using Money arithmetic. Validates all lines share same currency.

**T10.3** `IncomingDeliveryProcessor` application service:
- `process(Supplier $supplier, SupplierAccount $account, list<DeliveryNoteData> $deliveries): void`
- Injects `CommandBusInterface`, `PurchaseOrderReadRepositoryInterface`, and `Doctrine\DBAL\Connection` (for unmatched deliveries insert).
- For each delivery:
  1. **Idempotence check first**: query `procurement__supplier_receipts` for `(supplier_id, delivery_note_reference)` — if found (any status: PENDING_REVIEW or VALIDATED), skip this delivery entirely.
  2. Look up open PO via `externalReference` match using `PurchaseOrderReadRepositoryInterface`.
  3. If match found: dispatch `CreateSupplierReceipt` command via `CommandBusInterface` with `matchType = AUTO_MATCHED`.
  4. If no match: insert into `procurement__unmatched_deliveries` via `Doctrine\DBAL\Connection` (raw DBAL, not EntityManager).

**T10.4** Supplier command handlers (4):
- `RegisterSupplierHandler`: validates SupplierCode uniqueness (findByCode → DuplicateSupplierCodeException); dispatches UUIDs via UuidGeneratorInterface; calls Supplier::register(); saves; publishes.
- `RenameSupplierHandler`, `ChangeSupplierIntegrationModeHandler`, `ArchiveSupplierHandler`: load → mutate → save → publish.

**T10.5** SupplierAccount command handlers (4):
- `CreateSupplierAccountHandler`: check (clinicId, supplierId) uniqueness; create; save; publish.
- `UpdateSupplierAccountHandler`, `DisableSupplierAccountHandler`, `EnableSupplierAccountHandler`.

**T10.6** SupplierCatalog command handlers (4):
- `AddSupplierCatalogEntryHandler`: check (supplierId, code) uniqueness.
- `UpdateSupplierCatalogEntryHandler`, `DiscontinueSupplierCatalogEntryHandler`.
- `ImportSupplierCatalogHandler`: loads adapter via dispatcher; checks `$adapter->supportsCatalogImport()` first — if false, throws `CatalogImportNotSupportedException`; calls `fetchCatalog()`; if result is empty, log a warning and return without error; for each CatalogEntryData: upsert SupplierCatalogEntry (find by code → update or create).

**T10.7** SupplierPricing command handlers (3):
- `NegotiateSupplierPricingHandler`: validates (clinicId, entryId) uniqueness; validates currency matches Supplier.defaultCurrency.
- `UpdateSupplierPricingHandler`, `RemoveSupplierPricingHandler`.

**T10.8** PurchaseOrder command handlers (8):
- `CreatePurchaseOrderHandler`: calls `ClinicProviderInterface::getCurrency()` (no getShortCode — removed) + `PurchaseOrderNumberGeneratorInterface::next(ClinicId, year)` INSIDE `wrapInTransaction`. Inject `PurchaseOrderNumberGeneratorInterface` (not the concrete class). Stores currency snapshot on PO.
- `AddPurchaseOrderLineHandler`: guard PO in DRAFT; load `SupplierCatalogEntry` via `SupplierCatalogReadRepositoryInterface`; if `status === DISCONTINUED` → throw `DiscontinuedCatalogEntryException` (in `Application/Exception/`); then `$po->addLine(...)`.
- `UpdatePurchaseOrderLineHandler`, `RemovePurchaseOrderLineHandler`: guard DRAFT.
- `SubmitPurchaseOrderHandler` (key handler — updated flow with SUBMITTING/SEND_FAILED states):
  1. Load PO (must be DRAFT or SEND_FAILED) + Supplier + SupplierAccount.
  2. Check SupplierAccount status — throw `SupplierAccountDisabledException` if DISABLED.
  3. `wrapInTransaction`: `$po->markAsSubmitting($now)` → save PO → publish (PurchaseOrderSubmittingStarted raised).
  4. OUTSIDE transaction: `$result = $adapter->sendOrder($po, $account)`.
  5a. On success: `wrapInTransaction`: `$po->submit($result->externalReference, $result->sentAt, $now)` → save → publish (PurchaseOrderSubmitted raised).
  5b. On failure: `wrapInTransaction`: `$po->markAsSendingFailed($errorMessage, $now)` → save → publish (PurchaseOrderSendFailed raised). Do NOT re-throw — PO is now in SEND_FAILED.
- `ConfirmPurchaseOrderHandler`, `CancelPurchaseOrderLineHandler`, `ClosePurchaseOrderHandler`, `CancelPurchaseOrderHandler`.

**T10.9** SupplierReceipt command handlers (4):
- `CreateSupplierReceiptHandler`: create receipt (PENDING_REVIEW); save; publish.
- `ValidateSupplierReceiptHandler` (key handler): load receipt(PENDING_REVIEW) + load PO; **guard PO status is CONFIRMED or PARTIALLY_RECEIVED** (if CLOSED/CANCELLED → throw `PurchaseOrderClosedOrCancelledException`); within wrapInTransaction: `receipt->validate(...)` + update PO lines' receivedQuantity + determine if `$po->recordPartialReception()` or `$po->recordFullReception()` → save receipt + save PO; **publish TWICE: first `$this->domainEventPublisher->publish($receipt)` then `$this->domainEventPublisher->publish($po)`** (DomainEventPublisher accepts only one AggregateRoot per call); then `$this->integrationEventPublisher->publish(new SupplierReceiptCompletedIntegrationEvent(...))`.
- `MatchManualDeliveryHandler` (full spec):
  - Command fields: `unmatchedDeliveryId: string`, `purchaseOrderId: string`, `clinicId: string`, `matchedBy: ?string`.
  - Handler steps: 1) Load UnmatchedDelivery row via DBAL (throw `UnmatchedDeliveryNotFoundException` if not found); 2) Assert `resolved_at IS NULL` (throw `UnmatchedDeliveryAlreadyResolvedException` if already matched); 3) Load PO via repository; 4) Assert `PO.clinicId == command.clinicId` AND `PO.supplierId == delivery.supplierId` (throw `ClinicSupplierMismatchException`); 5) Assert PO.status in [CONFIRMED, PARTIALLY_RECEIVED] (throw `InvalidPurchaseOrderStatusTransitionException`); 6) Dispatch `CreateSupplierReceipt` command (matchType=MANUALLY_MATCHED, populate lines from raw_payload_json); 7) UPDATE `procurement__unmatched_deliveries` SET `resolved_at=now(), resolved_by=matchedBy` WHERE `id=?`.
- `PollSupplierDeliveriesHandler`: iterates active AUTOMATIC suppliers → `adapter->fetchDeliveries()` → delegates to `IncomingDeliveryProcessor`.

**T10.10** Query handlers (12): each loads from the appropriate read repository and returns an array. No domain objects in query results.

---

#### TASK GROUP 11 — Infrastructure Adapters

**T11.1** `CatalogArticleProviderAdapter` (implements ArticleProviderInterface):
- Implements `ArticleProviderInterface` using `QueryBusInterface`. Dispatches Catalog BC queries (e.g. a `GetArticleInfo` query or similar) via the query bus. Does NOT query `catalog__` tables directly via DBAL. Does NOT import any class from `App\Context\Catalog` namespace. If the Catalog BC does not yet expose the required query, add a prerequisite task to create it.

**T11.2** `ClinicProviderAdapter` (implements ClinicProviderInterface):
- Queries `clinic__clinics` table for currency code only. Short code is NOT fetched (PO number format no longer includes clinic_short).

**T11.3** `SimulatedSupplierAdapter`:
- identifier(): 'simulated'; mode(): SIMULATION.
- `sendOrder()`: inserts into `procurement__simulated_orders` via DBAL; returns SendOrderResult with externalReference = "SIM-{UUIDv7}".
  - If profile=DEMO_FAST: immediately inserts a ready delivery into `procurement__simulated_deliveries` with `available_at = now()`.
  - If profile=STAGING_REALISTIC: inserts with `available_at = now() + deliveryDelay seconds`.
  - If profile=DEV_INSTANT: same as DEMO_FAST.
- `fetchDeliveries()`: SELECT * FROM `procurement__simulated_deliveries` WHERE available_at <= :until AND fetched_at IS NULL AND purchase_order_id = (SELECT id FROM simulated_orders WHERE external_reference IN (:refs)); marks fetched_at = now() (idempotent).
- `fetchCatalog()`: reads YAML from `resources/simulated-catalogs/{supplier_code}.yaml`; returns list<CatalogEntryData>.
- `supportsAsyncDeliveryPolling()`: returns true.

**T11.4** `SimulationProfileConfig`:
- backed string enum or final readonly class with profile constant.
- Profiles: DEMO_FAST (deliveryDelay=0), STAGING_REALISTIC (deliveryDelay=3600), DEV_INSTANT (deliveryDelay=0).
- Read from Symfony parameter `procurement.simulation.profile` (default: DEMO_FAST).

**T11.5** `ManualExportAdapter` (abstract base):
- `sendOrder()`: calls abstract `buildExportContent(PurchaseOrder, SupplierAccount): string`; stores via `FileStorageInterface` (to be defined as a simple interface + local filesystem implementation); returns SendOrderResult with externalReference = file identifier, trackingUrl = download URL.
- `fetchDeliveries()`: returns [].
- `fetchCatalog()`: returns [].
- `supportsAsyncDeliveryPolling()`: returns false.

**T11.6** `CentravetCsvExporter` (extends ManualExportAdapter):
- identifier(): 'centravet_csv'; mode(): MANUAL_EXPORT.
- CSV format: columns CODE_FOURNISSEUR, NOM_PRODUIT, QTE, UNITE, PRIX_UNITAIRE_HT, REFERENCE_COMMANDE.

**T11.7** `AlcyonCsvExporter` (extends ManualExportAdapter):
- identifier(): 'alcyon_csv'; mode(): MANUAL_EXPORT.
- Alcyon-specific CSV format with different column ordering.

**T11.8** `GenericCsvExporter` (extends ManualExportAdapter):
- identifier(): 'generic_csv'; mode(): MANUAL_EXPORT.
- Generic format: supplier_product_code, product_name, quantity, unit, unit_price_ht, currency, po_number.

**T11.9** Integration tests for adapters:
- `SimulatedSupplierAdapterTest`: sendOrder → simulated_orders row inserted; fetchDeliveries (DEMO_FAST profile) returns the delivery immediately; re-fetch returns empty (idempotent).
- `CentravetCsvExporterTest`: sendOrder returns CSV content with correct columns; fetches return empty.

---

#### TASK GROUP 12 — Console Commands

**T12.1** `PollSupplierDeliveriesCommand` (`app:procurement:poll-deliveries`):
- Option: `--limit=N` (default: 100) — maximum deliveries to process per supplier per run.
- Finds all Suppliers with AUTOMATIC mode.
- For each, dispatches `PollSupplierDeliveries` command via CommandBus.
- Logs counts: processed, skipped (idempotent), errors.
- In `IncomingDeliveryProcessor::process()`: wrap each individual delivery in try/catch; log errors via `LoggerInterface`; continue to next delivery on failure (error isolation).

**T12.2** `ImportSupplierCatalogsCommand` (`app:procurement:import-catalogs [--supplier-code=]`):
- Finds suppliers (optionally filtered by code).
- Dispatches `ImportSupplierCatalog` command for each.

**T12.3** `CloseStaleOrdersCommand` (`app:procurement:close-stale-orders`):
- Queries `GetStaleOpenPurchaseOrders` (> 60 days in PARTIALLY_RECEIVED).
- Outputs list to console output (no auto-close — visibility only).

**T12.4** `DemoBootstrapClinicCommand` (`app:demo:bootstrap-clinic`):
- Options: --name=, --country=FR, --currency=EUR.
- Dispatches `CreateClinic` command (Clinic BC) via CommandBus.
- Creates 4 demo users (1 manager, 2 vets, 1 ASV) — via Clinic/AccessControl commands.
- Outputs ClinicId as JSON for piping.

**T12.5** `DemoApplyStarterCatalogCommand` (`app:demo:apply-starter-catalog`):
- Option: --clinic= (required).
- Dispatches `ApplyStarterCatalog` command (Catalog BC).
- Idempotent guard: skip if catalog already has > 10 articles for this clinic.

**T12.6** `DemoRegisterSuppliersCommand` (`app:demo:register-suppliers`):
- Option: --clinic= (required).
- Creates 3 Suppliers (CENTRAVET, ALCYON, HIPPOCAMPE) via `RegisterSupplier` commands if not already existing.
- Creates SupplierAccounts for this clinic if not already existing.
- Dispatches `ImportSupplierCatalog` for each (reads YAML files).
- Negotiates 10-15 SupplierPricings for common products.

**T12.7** `DemoSimulatePurchaseOrdersCommand` (`app:demo:simulate-purchase-orders`):
- Options: --clinic= (required), --count=5.
- Creates N POs with 3-5 lines each via `CreatePurchaseOrder` + `AddPurchaseOrderLine`.
- Submits each via `SubmitPurchaseOrder` (SIMULATION adapter, DEMO_FAST → immediate delivery).
- Dispatches `PollSupplierDeliveries` synchronously.
- Dispatches `ValidateSupplierReceipt` for each created receipt.
- Output: N POs in RECEIVED status + stock credited in Inventory.

**T12.8** `DemoSimulateConsumptionCommand` (`app:demo:simulate-consumption`):
- Options: --clinic= (required), --days=14.
- For each simulated day: selects random articles from Catalog, dispatches `ConsumeStock` (Inventory BC) for random quantities.
- Outputs final stock state + active alerts.

---

#### TASK GROUP 13 — Fixtures (Foundry v2)

**T13.1** `SupplierEntityFactory` (Foundry PersistentProxyObjectFactory):
- Default attributes: name = Faker company, code = strtoupper(Faker word), type = CENTRALE, countryCode = FR, defaultCurrency = EUR, integrationMode = SIMULATION, status = ACTIVE, version = 1.

**T13.2** `SupplierAccountEntityFactory`: references SupplierEntityFactory; clinicId = Faker uuid; customerCode = Faker numerify('########').

**T13.3** `SupplierCatalogEntryEntityFactory`: references SupplierEntityFactory; generates realistic product names.

**T13.4** `SupplierPricingEntityFactory`: references SupplierCatalogEntryEntityFactory.

**T13.5** `PurchaseOrderEntityFactory`: references SupplierEntityFactory + SupplierAccountEntityFactory; status = DRAFT by default.

**T13.6** `PurchaseOrderLineEntityFactory`: references PurchaseOrderEntityFactory; status = ACTIVE.

**T13.7** `SupplierReceiptEntityFactory` + `SupplierReceiptLineEntityFactory`.

**T13.8** Stories:
- `ThreeSimulatedSuppliersStory`: creates Centravet/Alcyon/Hippocampe suppliers with SIMULATION mode.
- `ClinicWithSupplierAccountsStory`: one clinic with 3 supplier accounts.
- `ActivePurchaseOrdersStory`: POs in DRAFT, SUBMITTED, CONFIRMED, PARTIALLY_RECEIVED statuses.
- `CompletedReceiptsStory`: POs in RECEIVED with associated validated receipts.

**T13.9** Simulated catalog YAML files:
- `resources/simulated-catalogs/centravet.yaml`: ~100 veterinary products (vaccins, antibiotiques, antiparasitaires, consommables), format:
  ```yaml
  products:
    - code: CVT-VAC-RABIES-001
      name: "Vaccin Antirabique Lyovac 10 doses"
      gtin: "3401583742391"
      price_minor: 4250
      currency: EUR
      unit: FLACON
      packaging_amount: "10"
  ```
- `resources/simulated-catalogs/alcyon.yaml`: laboratory-oriented products.
- `resources/simulated-catalogs/hippocampe.yaml`: consumables.

---

#### TASK GROUP 14 — End-to-End Tests

**T14.1** `ValidateSupplierReceiptHandlerTest` (integration):
- Given: PO in CONFIRMED with 2 lines; SupplierReceipt in PENDING_REVIEW with partial quantities.
- When: ValidateSupplierReceipt command dispatched.
- Then: receipt.status = VALIDATED; PO.status = PARTIALLY_RECEIVED; PO.line[0].receivedQuantity updated; SupplierReceiptCompletedIntegrationEvent captured on integration bus.

**T14.2** `DemoBootstrapPipelineTest` (E2E):
- Runs all 5 demo commands in sequence on a test clinic.
- Asserts after completion: 3 suppliers exist; POs in RECEIVED status; Inventory stock > 0 for at least 1 article.
- Runs the 5 commands a second time; asserts no duplicates (idempotence).

---

#### TASK GROUP 15 — Application Unit Tests (100% Coverage)

Each command handler test follows this pattern:
- Mock all dependencies (repositories, ports, buses, clock, uuid generator)
- Test happy path: correct aggregate state changes, correct publish calls
- Test error paths: each exception variant, each guard failure
- No database, no Doctrine — pure PHP mocks

**T15.1** Command handler unit tests (27 files):
Each test in `tests/Context/Procurement/Unit/Application/Command/*HandlerTest.php`:
- Mock the write repository (implements RepositoryInterface)
- Mock `DomainEventPublisher` — assert `publish()` called once per aggregate mutated
- Mock `ClockInterface` — return fixed `\DateTimeImmutable`
- Mock `UuidGeneratorInterface` — return deterministic UUID string
- Mock any Port interfaces (`ClinicProviderInterface`, `ArticleProviderInterface`, etc.)
- Mock `EntityManagerInterface` with callable `wrapInTransaction` that immediately invokes the closure
- `SubmitPurchaseOrderHandlerTest`: mock `SupplierIntegrationAdapterInterface` + `SupplierIntegrationDispatcher`; test SUBMITTING→SUBMITTED on success, SUBMITTING→SEND_FAILED on adapter failure
- `ValidateSupplierReceiptHandlerTest` (unit): mock receipt + PO repos; assert `publish()` called twice (`$receipt` then `$po`); assert `integrationEventPublisher->publish()` called once with correct payload

**T15.2** Query handler unit tests (12 files):
- Mock the read repository (implements `Application/Port/*ReadRepositoryInterface`)
- Assert result shape matches expected array structure
- Test empty result and non-empty result

**T15.3** Application service unit tests (3 files):
- `SupplierIntegrationDispatcherTest`: test adapter lookup by identifier; SIMULATION fallback; `RuntimeException` when not found
- `IncomingDeliveryProcessorTest`: mock DBAL Connection + `CommandBusInterface`; test idempotence (delivery already in receipts → skip); test match → `CreateSupplierReceipt` dispatch; test no-match → `unmatched_deliveries` insert
- `PurchaseOrderTotalsCalculatorTest`: test sum with multiple lines; mixed currencies throws; all-cancelled lines returns zero

**T15.4** `tests/Context/Procurement/Integration/Infrastructure/CleansSimulationTables.php` trait:
- Provides `cleanSimulationTables(): void`
- Calls `DELETE FROM procurement__simulated_orders; DELETE FROM procurement__simulated_deliveries;`
- Called in `tearDown()` of tests that write to these tables (`SimulatedSupplierAdapterTest`, `DemoBootstrapPipelineTest`)
- Necessary because DAMA DoctrineTestBundle does NOT rollback raw DBAL writes

---

### Acceptance Criteria

#### AC-1: Supplier Registration
**Given** no supplier with code "CENTRAVET" exists  
**When** `RegisterSupplier` command is dispatched with code="CENTRAVET", integrationMode=SIMULATION  
**Then** a Supplier aggregate is persisted in `procurement__suppliers`, `SupplierRegistered` domain event is raised, code is unique-indexed

**Given** a supplier with code "CENTRAVET" already exists  
**When** `RegisterSupplier` is dispatched with the same code  
**Then** `DuplicateSupplierCodeException` is thrown, no aggregate is persisted

**Given** an ARCHIVED supplier  
**When** `RenameSupplier` is dispatched  
**Then** `ArchivedSupplierException` is thrown

**Given** a supplier with integrationMode=MANUAL_EXPORT  
**When** `ChangeSupplierIntegrationMode(AUTOMATIC, null)` is dispatched  
**Then** exception is thrown (adapterIdentifier required for AUTOMATIC)

#### AC-2: SupplierAccount Lifecycle
**Given** no account exists for (clinicA, supplierX)  
**When** `CreateSupplierAccount` is dispatched  
**Then** account is persisted; `SupplierAccountCreated` is raised

**Given** account already exists for (clinicA, supplierX)  
**When** `CreateSupplierAccount` is dispatched again  
**Then** `DuplicateSupplierAccountException` is thrown

#### AC-3: CatalogPrice Validity and PriceResolver
**Given** a SupplierCatalogEntry with catalogPrice valid 2026-01-01 to 2026-12-31  
**And** no SupplierPricing for this clinic/entry  
**When** `PriceResolver::resolve(null, entry, date=2026-06-01)` is called  
**Then** returns EffectivePrice with source=CATALOG_DEFAULT

**Given** same entry + SupplierPricing with expiresAt=2026-03-01  
**When** `PriceResolver::resolve(pricing, entry, date=2026-06-01)`  
**Then** pricing is expired; falls back to CATALOG_DEFAULT

**Given** entry with catalogPrice valid only until 2025-12-31 + no pricing  
**When** `PriceResolver::resolve(null, entry, date=2026-06-01)`  
**Then** `NoEffectivePriceException` is thrown

#### AC-4: PurchaseOrder Workflow
**Given** a PO in DRAFT with 2 lines  
**When** `SubmitPurchaseOrder` is dispatched (SIMULATION adapter)  
**Then** PO status = SUBMITTED; `PurchaseOrderSubmitted` raised; externalReference set; PO persisted

**Given** a PO in DRAFT with 0 ACTIVE lines  
**When** `SubmitPurchaseOrder` is dispatched  
**Then** `EmptyPurchaseOrderException` thrown; PO stays DRAFT

**Given** a PO in CONFIRMED  
**When** `CancelPurchaseOrder` is dispatched with a reason  
**Then** PO status = CANCELLED; `PurchaseOrderCancelled` raised

**Given** a PO in PARTIALLY_RECEIVED  
**When** `CancelPurchaseOrder` is dispatched  
**Then** `InvalidPurchaseOrderStatusTransitionException` thrown

**Given** a PO in PARTIALLY_RECEIVED with 2 ACTIVE lines remaining  
**When** `ClosePurchaseOrder` is dispatched  
**Then** PO status = CLOSED; both remaining ACTIVE lines auto-set to CANCELLED; `PurchaseOrderClosed` raised

#### AC-5: SupplierReceipt Validation + Integration Event
**Given** a PO in CONFIRMED with line [articleX, qty=10]; a SupplierReceipt in PENDING_REVIEW with line [articleX, qty=6]  
**When** `ValidateSupplierReceipt` is dispatched  
**Then**:
- receipt.status = VALIDATED; receipt.validatedAt set
- PO.line[articleX].receivedQuantity = 6; PO.status = PARTIALLY_RECEIVED
- `SupplierReceiptCompleted` domain event raised on receipt
- `SupplierReceiptCompletedIntegrationEvent` published on integration bus
- Integration event carries correct lines payload including lotNumber if provided

**Given** a SupplierReceipt already VALIDATED  
**When** `addLine(...)` is called on the aggregate  
**Then** `ReceiptValidationException` thrown

#### AC-6: PurchaseOrderNumber Uniqueness
**Given** no sequence exists for (clinicA, 2026)  
**When** `CreatePurchaseOrder` is dispatched  
**Then** PO number = "PO-2026-000001" (no clinic_short in format)

**When** `CreatePurchaseOrder` is dispatched again for same clinic in same year  
**Then** PO number = "PO-2026-000002" (no collision even under concurrent load)

#### AC-7: SIMULATION Adapter End-to-End
**Given** a Supplier with SIMULATION mode + DEMO_FAST profile  
**When** `SubmitPurchaseOrder` is dispatched  
**Then** a row is inserted in `procurement__simulated_orders`; a row is inserted in `procurement__simulated_deliveries` with `available_at = now()`

**When** `PollSupplierDeliveries` is dispatched immediately after  
**Then** the delivery is fetched; a SupplierReceipt (AUTO_MATCHED) is created for the PO

**When** `PollSupplierDeliveries` is dispatched a second time  
**Then** the same delivery is NOT re-processed (idempotent: fetched_at already set)

#### AC-8: MANUAL_EXPORT Adapter
**Given** a Supplier with MANUAL_EXPORT mode and adapter='centravet_csv'  
**When** `SubmitPurchaseOrder` is dispatched  
**Then** a CSV file is generated with Centravet column format; `externalReference` = file identifier; PO status = SUBMITTED

#### AC-9: Fixtures Coverage
**Given** `ThreeSimulatedSuppliersStory` + `ActivePurchaseOrdersStory` are loaded  
**Then** at least one PO exists in each status: DRAFT, SUBMITTED, CONFIRMED, PARTIALLY_RECEIVED  
**And** at least one completed PO with a VALIDATED receipt in `CompletedReceiptsStory`

#### AC-10: Demo Bootstrap Pipeline
**Given** an empty database  
**When** the 5 demo commands are run sequentially for a new clinic  
**Then**:
- 3 Suppliers exist (CENTRAVET, ALCYON, HIPPOCAMPE) with SIMULATION mode
- 3 SupplierAccounts exist for the clinic
- Supplier catalog entries exist (> 50 per supplier from YAML)
- N POs (default 5) exist in RECEIVED status
- Inventory has stock credited (> 0) for articles received
- Re-running all 5 commands produces no duplicates and no errors
- `DemoBootstrapPipelineTest` uses real cross-BC CommandBus dispatches (no mocks of external BCs); if a required BC command does not exist (e.g. `CreateClinic`), the test is skipped via `$this->markTestSkipped()` at setup

#### AC-11: Receipt Line — Over-reception Guard
**Given** a PO line with `orderedQuantity = 10 UNITE`; existing `receivedQuantity = 8 UNITE`  
**When** `SupplierReceiptLine::recordReception(Quantity 5 UNITE)` is called  
**Then** an exception is thrown (received would exceed ordered); `receivedQuantity` stays at 8

#### AC-12: ValidateSupplierReceipt on Incompatible PO Status
**Given** a SupplierReceipt in PENDING_REVIEW linked to a PO in CLOSED status  
**When** `ValidateSupplierReceipt` command is dispatched  
**Then** `InvalidPurchaseOrderStatusTransitionException` is thrown; receipt stays PENDING_REVIEW; no integration event emitted

#### AC-13: DISCONTINUED Catalog Entry Blocks New PO Line
**Given** a SupplierCatalogEntry with status=DISCONTINUED  
**When** `AddPurchaseOrderLine` command is dispatched referencing that entry  
**Then** `DiscontinuedCatalogEntryException` is thrown; PO receives no new line

#### AC-14: Match Manual Delivery
**Given** an UnmatchedDelivery row exists with `resolved_at=NULL`, supplier=ALCYON, clinic=clinicA  
**And** a PO in CONFIRMED status for clinicA + ALCYON  
**When** `MatchManualDelivery` command is dispatched  
**Then** `UnmatchedDelivery.resolved_at` is set; a SupplierReceipt is created with `matchType=MANUALLY_MATCHED`; PO lines updated

**Given** the same UnmatchedDelivery already has `resolved_at` set  
**When** `MatchManualDelivery` command is dispatched again  
**Then** `UnmatchedDeliveryAlreadyResolvedException` thrown

## Additional Context

### Dependencies

**Internal BC dependencies (pre-existing):**
- `Context/Catalog`: Article must exist for `ArticleProviderInterface`; `ApplyStarterCatalog` command must exist for demo command.
- `Context/Inventory`: `ConsumeStock` command must exist for `DemoSimulateConsumptionCommand`. Inventory's consumer for `SupplierReceiptCompletedIntegrationEvent` is out of scope of this spec — to be implemented in a separate story in Inventory BC.
- `Context/Clinic`: `CreateClinic` command must exist for `DemoBootstrapClinicCommand`.
- `Shared/Money`: `App\Shared\Money\Domain\ValueObject\Money` — confirmed present.
- `Shared/UnitOfMeasure`: `App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure` — confirmed present.

**External dependencies (infrastructure):**
- MySQL 8.0+ (FULLTEXT support) — confirm CI environment.
- No Gotenberg required (deferred).
- No external FTP/SFTP/SOAP — SIMULATION adapter only at MVP.

**Prerequisite story: chore/shared-file-storage**: `Shared/Domain/Storage/FileStorageInterface.php` (method: `store(string $filename, string $content): string` → returns file identifier) + `Shared/Infrastructure/Storage/LocalFileSystemStorage.php`. This MUST be delivered before `ManualExportAdapter` implementation (T11.5). If not yet delivered, `ManualExportAdapter` implementation is blocked.

**Follow-up stories (NOT in scope):**
- Inventory BC: `SupplierReceiptCompletedIntegrationEventConsumer` + `HandleSupplierReceiptCompleted` handler.
- Procurement: `GeneratePurchaseOrderPdf` + Gotenberg integration.
- Procurement: First AUTOMATIC adapter (e.g. CentravetFtpAdapter) when client signs.

### Testing Strategy

**Unit tests** (Domain only, pure PHP):
- All 6 aggregates with invariants, state transitions, event assertions.
- PriceResolver with all 5 scenarios.
- VO validation (SupplierCode regex, Gtin format, CatalogPrice validity, PurchaseOrderNumber format).

**Integration tests** (KernelTestCase + real DB, Foundry factories):
- Mapper round-trips for all 6 aggregates — assert every field survives domain → entity → domain.
- Repository CRUD for all 6.
- PurchaseOrderNumberGenerator: concurrent SELECT FOR UPDATE test.
- Optimistic locking: PurchaseOrder with version conflict → ConcurrentModificationException.
- SimulatedSupplierAdapter: full send + fetch + idempotence cycle.
- CentravetCsvExporter: CSV output format assertion.
- `ValidateSupplierReceiptHandler`: full integration test including PO status update + integration event capture.

**E2E test** (DemoBootstrapPipelineTest):
- Runs all 5 commands end-to-end; asserts final state; verifies idempotence.
- Uses `CleansSimulationTables` trait in `tearDown()` — DAMA DoctrineTestBundle does NOT rollback raw DBAL writes to `procurement__simulated_orders` and `procurement__simulated_deliveries`.

**CleansSimulationTables trait** (applies to `SimulatedSupplierAdapterTest` and `DemoBootstrapPipelineTest`):
Integration tests that write to `procurement__simulated_orders` or `procurement__simulated_deliveries` MUST use the `CleansSimulationTables` trait in their `tearDown()` method. DAMA DoctrineTestBundle wraps test DB operations in a transaction, but raw DBAL writes (used by `SimulatedSupplierAdapter`) are NOT included in that rollback. Without explicit `DELETE FROM`, these tables accumulate data across tests. The trait executes: `DELETE FROM procurement__simulated_orders; DELETE FROM procurement__simulated_deliveries;`

**Coverage target**: 100% line coverage on `src/Context/Procurement/Domain/` and `src/Context/Procurement/Application/`. Infrastructure adapters require integration tests but are excluded from the 100% line coverage target (external I/O code).

### Notes

- **SupplierReceiptCompletedIntegrationEvent payload design**: the `lines` array is intentionally flat (scalar primitives only) to avoid coupling Inventory to Procurement domain types.
- **Address VO**: `countryCode` is typed as `?CountryCode` (from `App\Shared\Domain\ValueObject\CountryCode`) inside the domain. In DB it's stored as part of the JSON blob, serialized as 2-char string.
- **SimulatedSupplierAdapter** writes directly to DB via DBAL (not through domain/mappers) — these are pure technical tables, not domain entities.
- **`received_by` in SupplierReceipt**: stored as raw BINARY(16) UUID string representing a user ID. No FK to User/AccessControl entity (keeps BC clean). Nullable.
- **`pdfFileId`**: nullable string on PurchaseOrder aggregate and entity. Not exposed in any command or query in this spec.
- **YAML simulated catalogs**: include realistic French veterinary product names. Product prices in EUR (minor units, e.g. 4250 = €42.50). At least 80 products per supplier.
- **Technical debt**: `src/Context/Inventory/Application/Command/ReceiveStock/ReceiveStockHandler.php` uses `Uuid::v7()` directly. This must be fixed in a separate chore story. Do NOT follow this pattern in Procurement — use `UuidGeneratorInterface::generate()`.
- **PurchaseOrder.currency**: snapshot of `Supplier.defaultCurrency` at PO creation time. Used by `totalAmount()` as the authoritative currency for the zero-lines Money result.
- **DomainEventPublisher single-arg contract**: `DomainEventPublisher::publish()` accepts only one `AggregateRoot` per call. Where multiple aggregates are mutated (e.g. `ValidateSupplierReceiptHandler`), call `publish()` once per aggregate sequentially.

### Prerequisite Stories

Before implementing this spec, the following stories must be delivered:

1. **chore/shared-address** — `Address` VO in `Shared/Domain/ValueObject/Address.php` (may already exist from previous BC work; verify before creating).
2. **chore/shared-file-storage** — `FileStorageInterface` + `LocalFileSystemStorage` (required for `ManualExportAdapter`, Phase B).
3. **tech-debt/inventory-uuid-generator** — Fix `ReceiveStockHandler` to use `UuidGeneratorInterface::generate()` instead of `Uuid::v7()` directly.

---

## Dev Agent Record

### Implementation Notes

**Completed by:** Dev Agent (Claude Sonnet 4.6)  
**Completed at:** 2026-05-26  
**Branch:** `feature/context-procurement-bc`

### Completion Notes

All 15 task groups implemented and verified:

- **T1-T7 (Domain layer):** 6 aggregate roots (Supplier, SupplierAccount, SupplierCatalogEntry, SupplierPricing, PurchaseOrder, SupplierReceipt), all VOs, domain events, exceptions, repository interfaces, PriceResolver domain service. 100% PHPStan max-level clean.
- **T8 (Persistence):** 8 Doctrine entities, 6 mappers, 6 write repositories, DoctrinePurchaseOrderNumberSequenceRepository (SELECT FOR UPDATE), 6 DBAL read repositories, 12-table migration. Fixed EntityIdentityCollisionException in PO/Receipt repositories.
- **T9 (Application Ports + Services):** SupplierIntegrationAdapterInterface, 5 read port interfaces, 3 DTOs, 2 cross-BC adapters (CatalogArticleProviderAdapter, ClinicProviderAdapter), 3 application services (SupplierIntegrationDispatcher, PurchaseOrderTotalsCalculator, IncomingDeliveryProcessor). Shared file storage (FileStorageInterface + LocalFileSystemStorage) also implemented.
- **T10 (Application Handlers):** 24 command handlers (all with wrapInTransaction + publish pattern), 12 query handlers. SubmitPurchaseOrder two-phase pattern and ValidateSupplierReceipt dual-publish + integration event implemented correctly.
- **T11 (Infrastructure Adapters):** SimulatedSupplierAdapter (3 profiles: DEMO_FAST, STAGING_REALISTIC, DEV_INSTANT), ManualExportAdapter base, CentravetCsvExporter, AlcyonCsvExporter, GenericCsvExporter. 3 simulated catalog YAML files (~100 products each).
- **T12 (Console Commands):** 8 commands (3 operational: poll-deliveries, import-catalogs, close-stale-orders; 5 demo: bootstrap-clinic, apply-starter-catalog, register-suppliers, simulate-purchase-orders, simulate-consumption). Cross-BC commands guarded with `class_exists` checks.
- **T13 (Fixtures):** 8 Foundry v2 PersistentProxyObjectFactory factories, 4 stories (ThreeSimulatedSuppliers, ClinicWithSupplierAccounts, ActivePurchaseOrders, CompletedReceipts).
- **T14-T15 (Tests):** 6 mapper round-trip tests (pure PHP), 6 repository integration tests, PurchaseOrderNumberGeneratorTest, ValidateSupplierReceiptHandlerTest (integration), DemoBootstrapPipelineTest (E2E).

### Key Deviations from Spec

1. `ClinicProviderAdapter` uses `currency_code` column (not `default_currency` as in the spec — confirmed from `ClinicEntity.php`)
2. `LocalFileSystemStorage` uses `DEFAULT_URI` env var (already in `.env`) instead of `APP_URL` which does not exist
3. Read repository implementations required explicit `implements` declarations added post-creation
4. `DoctrinePurchaseOrderRepository.save()` and `DoctrineSupplierReceiptRepository.save()` use update-in-place logic to avoid EntityIdentityCollisionException

### File List (summary)

- `src/Context/Procurement/` — 249 PHP files
- `tests/Context/Procurement/` — 66 PHP files (65 test classes + 1 trait)
- `migrations/Procurement/Version20260525000000.php` — 12-table migration
- `fixtures/Context/Procurement/` — 12 PHP files (8 factories + 4 stories)
- `resources/simulated-catalogs/centravet.yaml`, `alcyon.yaml`, `hippocampe.yaml`
- `src/Shared/Domain/Storage/FileStorageInterface.php`
- `src/Shared/Infrastructure/Storage/LocalFileSystemStorage.php`
- `config/packages/doctrine.yaml` — Procurement mapping added
- `config/packages/doctrine_migrations.yaml` — Procurement migration path added
- `config/services.yaml` — Procurement service bindings + FileStorageInterface alias

### Change Log

- 2026-05-26: Implemented Context/Procurement bounded context — 6 aggregates, 24 commands, 12 queries, 3 integration adapters, 8 console commands, Foundry v2 fixtures, full test suite. `make ci` green: 2092 tests, 5361 assertions, PHPStan level max: 0 errors.
