<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Port\ConfiguredPlatformInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformCode;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionRefusedException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenPlatformConnectionHandler
{
    public function __construct(
        private PlatformConnectionRepositoryInterface $connections,
        private ConfiguredPlatformInterface $platform,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenPlatformConnection $command): string
    {
        $code = $this->platform->code();

        if (null === $code) {
            throw new PlatformConnectionRefusedException(PlatformConnectionRefusedException::NO_PLATFORM, 'Aucune plateforme de facturation électronique n\'est configurée.');
        }

        $organisation = Siren::fromInput($command->organisationId);

        if (null !== $this->connections->byOrganisation($organisation)) {
            throw new PlatformConnectionRefusedException(PlatformConnectionRefusedException::ALREADY_OPENED, \sprintf('L\'organisation %s a déjà une connexion.', $organisation->value()));
        }

        $connection = PlatformConnection::open(
            PlatformConnectionId::fromString($this->uuids->generate()),
            $organisation,
            PlatformCode::fromString($code),
            $this->clock->now(),
        );

        $this->connections->save($connection);

        return $connection->id()->toString();
    }
}
