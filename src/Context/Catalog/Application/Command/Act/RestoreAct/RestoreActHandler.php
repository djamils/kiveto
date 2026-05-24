<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\RestoreAct;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestoreActHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RestoreAct $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $act      = $this->actRepository->findById(ActId::fromString($command->actId), $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($command->actId);
        }

        $act->restore($this->clock->now());

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);
    }
}
