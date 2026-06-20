<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\DiscontinueSupplierCatalogEntry;

use App\Context\Procurement\Domain\SupplierCatalog\Exception\SupplierCatalogEntryNotFoundException;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DiscontinueSupplierCatalogEntryHandler
{
    public function __construct(
        private SupplierCatalogEntryRepositoryInterface $catalogRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DiscontinueSupplierCatalogEntry $command): void
    {
        $entry = $this->catalogRepository->findById(SupplierCatalogEntryId::fromString($command->entryId));

        if (null === $entry) {
            throw new SupplierCatalogEntryNotFoundException($command->entryId);
        }

        $entry->discontinue(updatedAt: $this->clock->now());

        $this->entityManager->wrapInTransaction(function () use ($entry): void {
            $this->catalogRepository->save($entry);
        });

        $this->domainEventPublisher->publish($entry);
    }
}
