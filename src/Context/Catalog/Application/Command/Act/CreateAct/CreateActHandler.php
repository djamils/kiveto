<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\CreateAct;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Exception\DuplicateActCodeException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateActHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly TaxCategoryRegistryInterface $taxCategoryRegistry,
        private readonly ClinicInfoProviderInterface $clinicInfoProvider,
    ) {
    }

    public function __invoke(CreateAct $command): string
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $taxCategory = TaxCategoryCode::fromString($command->taxCategoryCode);

        if (!$this->taxCategoryRegistry->has($taxCategory)) {
            throw new \DomainException(\sprintf('Unknown tax category code: "%s".', $command->taxCategoryCode));
        }

        $clinicCurrency = $this->clinicInfoProvider->getCurrencyCode($clinicId);

        if ($clinicCurrency->toString() !== $command->basePriceCurrency) {
            throw new ClinicCurrencyMismatchException($clinicCurrency->toString(), $command->basePriceCurrency);
        }

        $code = ActCode::fromString($command->code);

        if ($this->actRepository->existsByCode($code, $clinicId)) {
            throw new DuplicateActCodeException($command->code);
        }

        $actId = ActId::fromString($this->uuidGenerator->generate());
        $now   = $this->clock->now();

        $act = Act::create(
            id: $actId,
            clinicId: $clinicId,
            name: ActName::fromString($command->name),
            code: $code,
            description: ActDescription::fromNullableString($command->description),
            category: ActCategory::from($command->category),
            taxCategory: $taxCategory,
            basePrice: Money::fromMinorUnits(
                $command->basePriceMinorUnits,
                CurrencyCode::fromString($command->basePriceCurrency),
            ),
            estimatedDuration: ActDuration::ofMinutes($command->estimatedDurationMinutes),
            requiresAnesthesia: $command->requiresAnesthesia,
            createdAt: $now,
        );

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);

        return $actId->toString();
    }
}
