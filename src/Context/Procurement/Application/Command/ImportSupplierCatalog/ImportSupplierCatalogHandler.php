<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ImportSupplierCatalog;

use App\Context\Procurement\Application\Exception\CatalogImportNotSupportedException;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportSupplierCatalogHandler
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository,
        private SupplierCatalogEntryRepositoryInterface $catalogRepository,
        private SupplierIntegrationDispatcher $dispatcher,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportSupplierCatalog $command): void
    {
        $supplier = $this->supplierRepository->findById(SupplierId::fromString($command->supplierId));

        if (null === $supplier) {
            throw new SupplierNotFoundException($command->supplierId);
        }

        $adapter = $this->dispatcher->dispatch($supplier);

        if (!$adapter->supportsCatalogImport()) {
            throw new CatalogImportNotSupportedException($command->supplierId);
        }

        $entries = $adapter->fetchCatalog($supplier);

        if ([] === $entries) {
            $this->logger->warning('ImportSupplierCatalog: adapter returned empty catalog.', [
                'supplierId' => $command->supplierId,
            ]);

            return;
        }

        $now = $this->clock->now();

        foreach ($entries as $data) {
            $productCode   = SupplierProductCode::fromString($data->supplierProductCode);
            $currency      = CurrencyCode::fromString($data->currency);
            $amount        = Money::fromMinorUnits($data->priceMinor, $currency);
            $catalogPrice  = CatalogPrice::create($amount, $now, null);
            $packagingUnit = null !== $data->unit ? UnitOfMeasure::fromString($data->unit) : null;

            $existing = $this->catalogRepository->findBySupplierAndCode($supplier->id(), $productCode);

            if (null !== $existing) {
                $existing->update(
                    name: SupplierProductName::fromString($data->name),
                    gtin: null !== $data->gtin ? Gtin::fromString($data->gtin) : null,
                    packagingUnit: $packagingUnit,
                    packagingAmount: $data->packagingAmount,
                    updatedAt: $now,
                );
                $existing->updatePrice($catalogPrice, $now);

                $this->entityManager->wrapInTransaction(function () use ($existing): void {
                    $this->catalogRepository->save($existing);
                });

                $this->domainEventPublisher->publish($existing);
            } else {
                $entry = SupplierCatalogEntry::add(
                    id: SupplierCatalogEntryId::fromString($this->uuidGenerator->generate()),
                    supplierId: $supplier->id(),
                    productCode: $productCode,
                    name: SupplierProductName::fromString($data->name),
                    gtin: null !== $data->gtin ? Gtin::fromString($data->gtin) : null,
                    catalogPrice: $catalogPrice,
                    packagingUnit: $packagingUnit,
                    packagingAmount: $data->packagingAmount,
                    createdAt: $now,
                );

                $this->entityManager->wrapInTransaction(function () use ($entry): void {
                    $this->catalogRepository->save($entry);
                });

                $this->domainEventPublisher->publish($entry);
            }
        }
    }
}
