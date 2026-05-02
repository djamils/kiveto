<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\Command\OpenMicrochipRegistryLookup;

use App\Context\Regulatory\Domain\MicrochipRegistryLookup;
use App\Context\Regulatory\Domain\Repository\MicrochipRegistryLookupRepositoryInterface;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenMicrochipRegistryLookupHandler
{
    public function __construct(
        private MicrochipRegistryLookupRepositoryInterface $repo,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenMicrochipRegistryLookup $command): string
    {
        $id  = MicrochipRegistryLookupId::fromString($this->uuidGenerator->generate());
        $now = $this->clock->now();

        $lookup = MicrochipRegistryLookup::initiate(
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
