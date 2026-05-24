<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\RenameAct;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RenameActHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RenameAct $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $actId    = ActId::fromString($command->actId);

        $act = $this->actRepository->findById($actId, $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($command->actId);
        }

        $act->rename(ActName::fromString($command->name), $this->clock->now());

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);
    }
}
