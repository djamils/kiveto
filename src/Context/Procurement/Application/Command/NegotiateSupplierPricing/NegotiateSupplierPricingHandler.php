<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\NegotiateSupplierPricing;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NegotiateSupplierPricingHandler
{
    public function __construct(
        private SupplierPricingRepositoryInterface $pricingRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(NegotiateSupplierPricing $command): void
    {
        $clinicId       = ClinicId::fromString($command->clinicId);
        $catalogEntryId = SupplierCatalogEntryId::fromString($command->catalogEntryId);

        if (null !== $this->pricingRepository->findByClinicAndEntry($clinicId, $catalogEntryId)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'A negotiated pricing for clinic "%s" and catalog entry "%s" already exists.',
                    $command->clinicId,
                    $command->catalogEntryId,
                ),
            );
        }

        $now      = $this->clock->now();
        $currency = CurrencyCode::fromString($command->currency);
        $amount   = Money::fromMinorUnits($command->amountMinor, $currency);

        $pricing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            supplierId: SupplierId::fromString($command->supplierId),
            catalogEntryId: $catalogEntryId,
            negotiatedPrice: NegotiatedPrice::create($amount, $command->discountPercentage, $command->notes),
            expiresAt: null !== $command->expiresAt ? new \DateTimeImmutable($command->expiresAt) : null,
            negotiatedAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($pricing): void {
            $this->pricingRepository->save($pricing);
        });

        $this->domainEventPublisher->publish($pricing);
    }
}
