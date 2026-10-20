<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Activates the connection; what the organisation waited to send is released
 * by the activation event.
 */
#[AsMessageHandler]
final readonly class VerifyPlatformConnectionHandler
{
    public function __construct(
        private PlatformConnectionRepositoryInterface $connections,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyPlatformConnection $command): void
    {
        $connection = $this->connections->byOrganisation(Siren::fromInput($command->organisationId))
            ?? throw new PlatformConnectionNotFoundException($command->organisationId);

        $connection->markVerified($this->clock->now());

        $this->connections->save($connection);
        $this->domainEventPublisher->publish($connection);
    }
}
