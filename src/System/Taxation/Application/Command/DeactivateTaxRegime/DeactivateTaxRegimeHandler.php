<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\DeactivateTaxRegime;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Exception\UnknownTaxRegimeException;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeactivateTaxRegimeHandler
{
    public function __construct(
        private TaxRegimeRepositoryInterface $regimeRepo,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeactivateTaxRegime $command): void
    {
        $regime = $this->regimeRepo->findById($command->regimeId);

        if (null === $regime) {
            throw new UnknownTaxRegimeException($command->regimeId->toString());
        }

        $regime->deactivate($this->clock);
        $this->regimeRepo->save($regime);
        $this->domainEventPublisher->publish($regime);
    }
}
