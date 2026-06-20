<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RemoveSupplierPricing;

use App\Context\Procurement\Domain\SupplierPricing\Exception\SupplierPricingNotFoundException;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveSupplierPricingHandler
{
    public function __construct(
        private SupplierPricingRepositoryInterface $pricingRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RemoveSupplierPricing $command): void
    {
        $pricing = $this->pricingRepository->findById(SupplierPricingId::fromString($command->pricingId));

        if (null === $pricing) {
            throw new SupplierPricingNotFoundException($command->pricingId);
        }

        $pricing->remove(updatedAt: $this->clock->now());

        $this->entityManager->wrapInTransaction(function () use ($pricing): void {
            $this->pricingRepository->remove($pricing);
        });

        $this->domainEventPublisher->publish($pricing);
    }
}
