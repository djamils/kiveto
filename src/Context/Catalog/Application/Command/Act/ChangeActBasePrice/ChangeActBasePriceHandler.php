<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ChangeActBasePrice;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeActBasePriceHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClinicInfoProviderInterface $clinicInfoProvider,
    ) {
    }

    public function __invoke(ChangeActBasePrice $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);

        $clinicCurrency = $this->clinicInfoProvider->getCurrencyCode($clinicId);

        if ($clinicCurrency->toString() !== $command->basePriceCurrency) {
            throw new ClinicCurrencyMismatchException($clinicCurrency->toString(), $command->basePriceCurrency);
        }

        $act = $this->actRepository->findById(ActId::fromString($command->actId), $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($command->actId);
        }

        $act->changeBasePrice(
            Money::fromMinorUnits(
                $command->basePriceMinorUnits,
                CurrencyCode::fromString($command->basePriceCurrency),
            ),
            $this->clock->now(),
        );

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);
    }
}
