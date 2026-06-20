<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierPricing;

use App\Context\Procurement\Domain\SupplierPricing\Exception\SupplierPricingNotFoundException;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSupplierPricingHandler
{
    public function __construct(
        private SupplierPricingRepositoryInterface $pricingRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdateSupplierPricing $command): void
    {
        $pricing = $this->pricingRepository->findById(SupplierPricingId::fromString($command->pricingId));

        if (null === $pricing) {
            throw new SupplierPricingNotFoundException($command->pricingId);
        }

        $currency = CurrencyCode::fromString($command->currency);
        $amount   = Money::fromMinorUnits($command->amountMinor, $currency);

        $pricing->update(
            negotiatedPrice: NegotiatedPrice::create($amount, $command->discountPercentage, $command->notes),
            expiresAt: null !== $command->expiresAt ? new \DateTimeImmutable($command->expiresAt) : null,
            updatedAt: $this->clock->now(),
        );

        $this->entityManager->wrapInTransaction(function () use ($pricing): void {
            $this->pricingRepository->save($pricing);
        });

        $this->domainEventPublisher->publish($pricing);
    }
}
