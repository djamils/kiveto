<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Package\GetPackageDetail;

use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPackageDetailHandler
{
    public function __construct(
        private readonly PackageRepositoryInterface $packageRepository,
    ) {
    }

    public function __invoke(GetPackageDetail $query): PackageDetailView
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $package  = $this->packageRepository->findById(PackageId::fromString($query->packageId), $clinicId);

        if (null === $package) {
            throw new PackageNotFoundException($query->packageId);
        }

        $components = array_map(
            static fn (PackageComponent $c) => [
                'id'       => $c->id()->toString(),
                'itemType' => $c->itemRef()->type()->value,
                'itemId'   => $c->itemRef()->id(),
                'quantity' => $c->quantity()->value(),
                'weight'   => $c->weight(),
            ],
            $package->components(),
        );

        $fixedPrice = $package->fixedPrice();

        return new PackageDetailView(
            id: $package->id()->toString(),
            clinicId: $package->clinicId()->toString(),
            name: $package->name()->toString(),
            code: $package->code()->toString(),
            taxCategoryCode: $package->taxCategory()->toString(),
            pricingMode: $package->pricingMode()->value,
            fixedPriceMinorUnits: $fixedPrice?->minorUnits(),
            fixedPriceCurrency: $fixedPrice?->currency()->toString(),
            components: $components,
            status: $package->status()->value,
            createdAt: $package->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $package->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
