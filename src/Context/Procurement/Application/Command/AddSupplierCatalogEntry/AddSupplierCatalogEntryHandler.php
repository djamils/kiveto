<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\AddSupplierCatalogEntry;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Exception\DuplicateSupplierProductCodeException;
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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddSupplierCatalogEntryHandler
{
    public function __construct(
        private SupplierCatalogEntryRepositoryInterface $catalogRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AddSupplierCatalogEntry $command): void
    {
        $supplierId  = SupplierId::fromString($command->supplierId);
        $productCode = SupplierProductCode::fromString($command->productCode);

        if (null !== $this->catalogRepository->findBySupplierAndCode($supplierId, $productCode)) {
            throw new DuplicateSupplierProductCodeException($command->supplierId, $command->productCode);
        }

        $now = $this->clock->now();

        $currency     = CurrencyCode::fromString($command->catalogPriceCurrency);
        $amount       = Money::fromMinorUnits($command->catalogPriceMinor, $currency);
        $validFrom    = new \DateTimeImmutable($command->catalogPriceValidFrom);
        $validTo      = null !== $command->catalogPriceValidTo ? new \DateTimeImmutable($command->catalogPriceValidTo) : null;
        $catalogPrice = CatalogPrice::create($amount, $validFrom, $validTo);

        $packagingUnit = null !== $command->packagingUnit ? UnitOfMeasure::fromString($command->packagingUnit) : null;

        $entry = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString($this->uuidGenerator->generate()),
            supplierId: $supplierId,
            productCode: $productCode,
            name: SupplierProductName::fromString($command->name),
            gtin: null !== $command->gtin ? Gtin::fromString($command->gtin) : null,
            catalogPrice: $catalogPrice,
            packagingUnit: $packagingUnit,
            packagingAmount: $command->packagingAmount,
            createdAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($entry): void {
            $this->catalogRepository->save($entry);
        });

        $this->domainEventPublisher->publish($entry);
    }
}
