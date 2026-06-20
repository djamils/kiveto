<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ChangeSupplierIntegrationMode;

use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeSupplierIntegrationModeHandler
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ChangeSupplierIntegrationMode $command): void
    {
        $supplier = $this->supplierRepository->findById(SupplierId::fromString($command->supplierId));

        if (null === $supplier) {
            throw new SupplierNotFoundException($command->supplierId);
        }

        $supplier->changeIntegrationMode(
            newMode: SupplierIntegrationMode::from($command->newMode),
            adapterIdentifier: $command->adapterIdentifier,
            updatedAt: $this->clock->now(),
        );

        $this->entityManager->wrapInTransaction(function () use ($supplier): void {
            $this->supplierRepository->save($supplier);
        });

        $this->domainEventPublisher->publish($supplier);
    }
}
