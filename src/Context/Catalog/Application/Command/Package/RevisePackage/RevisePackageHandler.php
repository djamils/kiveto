<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\RevisePackage;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevisePackageHandler
{
    public function __construct(
        private PackageRepositoryInterface $packageRepository,
        private TaxCategoryRegistryInterface $taxCategoryRegistry,
        private ClinicInfoProviderInterface $clinicInfoProvider,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RevisePackage $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $package  = $this->packageRepository->findById(PackageId::fromString($command->packageId), $clinicId);

        if (null === $package) {
            throw new PackageNotFoundException($command->packageId);
        }

        $taxCategory = TaxCategoryCode::fromString($command->taxCategoryCode);

        if (!$this->taxCategoryRegistry->has($taxCategory)) {
            throw new \DomainException(\sprintf('Unknown tax category code: "%s".', $command->taxCategoryCode));
        }

        $fixedPrice = null;

        if (null !== $command->fixedPriceMinorUnits) {
            $currency       = $command->fixedPriceCurrency ?? '';
            $clinicCurrency = $this->clinicInfoProvider->getCurrencyCode($clinicId);

            if ($clinicCurrency->toString() !== $currency) {
                throw new ClinicCurrencyMismatchException($clinicCurrency->toString(), $currency);
            }

            $fixedPrice = Money::fromMinorUnits($command->fixedPriceMinorUnits, CurrencyCode::fromString($currency));
        }

        $package->revise(
            PackageName::fromString($command->name),
            $taxCategory,
            PackagePricingMode::from($command->pricingMode),
            $fixedPrice,
            $this->clock->now(),
        );

        $this->packageRepository->save($package);
        $this->domainEventPublisher->publish($package);
    }
}
