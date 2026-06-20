<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ResolveEffectivePrice;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\SupplierCatalog\Exception\SupplierCatalogEntryNotFoundException;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\Service\PriceResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResolveEffectivePriceHandler
{
    public function __construct(
        private SupplierPricingRepositoryInterface $pricingRepository,
        private SupplierCatalogEntryRepositoryInterface $catalogRepository,
        private PriceResolver $priceResolver,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(ResolveEffectivePrice $query): array
    {
        $entryId = SupplierCatalogEntryId::fromString($query->entryId);
        $entry   = $this->catalogRepository->findById($entryId);

        if (null === $entry) {
            throw new SupplierCatalogEntryNotFoundException($query->entryId);
        }

        $clinicId      = ClinicId::fromString($query->clinicId);
        $referenceDate = new \DateTimeImmutable($query->referenceDate);

        $pricing = $this->pricingRepository->findByClinicAndEntry($clinicId, $entryId);

        $effectivePrice = $this->priceResolver->resolve($pricing, $entry, $referenceDate);

        return [
            'amountMinor' => $effectivePrice->amount->minorUnits(),
            'currency'    => $effectivePrice->amount->currency()->toString(),
            'source'      => $effectivePrice->source->value,
            'resolvedAt'  => $effectivePrice->resolvedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
