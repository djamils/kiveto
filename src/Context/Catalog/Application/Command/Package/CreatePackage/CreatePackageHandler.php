<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\CreatePackage;

use App\Context\Catalog\Domain\Package\Exception\DuplicatePackageCodeException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreatePackageHandler
{
    public function __construct(
        private readonly PackageRepositoryInterface $packageRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CreatePackage $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $code     = PackageCode::fromString($command->code);

        if ($this->packageRepository->existsByCode($code, $clinicId)) {
            throw new DuplicatePackageCodeException($command->code);
        }

        $fixedPrice = null;

        if (null !== $command->fixedPriceMinorUnits && null !== $command->fixedPriceCurrency) {
            $fixedPrice = Money::fromMinorUnits(
                $command->fixedPriceMinorUnits,
                CurrencyCode::fromString($command->fixedPriceCurrency),
            );
        }

        $packageId = PackageId::fromString($this->uuidGenerator->generate());
        $now       = $this->clock->now();

        $package = Package::create(
            id: $packageId,
            clinicId: $clinicId,
            name: PackageName::fromString($command->name),
            code: $code,
            taxCategory: TaxCategoryCode::fromString($command->taxCategoryCode),
            pricingMode: PackagePricingMode::from($command->pricingMode),
            fixedPrice: $fixedPrice,
            createdAt: $now,
        );

        $this->packageRepository->save($package);
        $this->domainEventPublisher->publish($package);

        return $packageId->toString();
    }
}
