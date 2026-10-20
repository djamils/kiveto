<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\AdvanceSyncCursor;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Connection\SyncCursor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AdvanceSyncCursorHandler
{
    public function __construct(private PlatformConnectionRepositoryInterface $connections)
    {
    }

    public function __invoke(AdvanceSyncCursor $command): void
    {
        $connection = $this->connections->byOrganisation(Siren::fromInput($command->organisationId));

        if (null === $connection) {
            return;
        }

        $connection->advanceCursor(SyncCursor::at(new \DateTimeImmutable($command->updatedAfter), $command->lastFlowId));

        $this->connections->save($connection);
    }
}
