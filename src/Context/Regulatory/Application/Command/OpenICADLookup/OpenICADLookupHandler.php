<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\Command\OpenICADLookup;

use App\Context\Regulatory\Domain\ICADLookup;
use App\Context\Regulatory\Domain\Repository\ICADLookupRepositoryInterface;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenICADLookupHandler
{
    public function __construct(
        private ICADLookupRepositoryInterface $repo,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenICADLookup $command): string
    {
        $id  = ICADLookupId::fromString($this->uuidGenerator->generate());
        $now = $this->clock->now();

        $lookup = ICADLookup::initiate(
            id: $id,
            chipNumber: $command->chipNumber,
            clinicId: $command->clinicId,
            now: $now,
        );

        $this->repo->save($lookup);
        $this->domainEventPublisher->publish($lookup);

        return $id->value();
    }
}
