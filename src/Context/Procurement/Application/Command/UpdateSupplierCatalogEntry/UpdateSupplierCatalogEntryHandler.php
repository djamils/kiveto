<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierCatalogEntry;

use App\Context\Procurement\Domain\SupplierCatalog\Exception\SupplierCatalogEntryNotFoundException;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSupplierCatalogEntryHandler
{
    public function __construct(
        private SupplierCatalogEntryRepositoryInterface $catalogRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdateSupplierCatalogEntry $command): void
    {
        $entry = $this->catalogRepository->findById(SupplierCatalogEntryId::fromString($command->entryId));

        if (null === $entry) {
            throw new SupplierCatalogEntryNotFoundException($command->entryId);
        }

        $packagingUnit = null !== $command->packagingUnit ? UnitOfMeasure::fromString($command->packagingUnit) : null;

        $entry->update(
            name: SupplierProductName::fromString($command->name),
            gtin: null !== $command->gtin ? Gtin::fromString($command->gtin) : null,
            packagingUnit: $packagingUnit,
            packagingAmount: $command->packagingAmount,
            updatedAt: $this->clock->now(),
        );

        $this->entityManager->wrapInTransaction(function () use ($entry): void {
            $this->catalogRepository->save($entry);
        });

        $this->domainEventPublisher->publish($entry);
    }
}
