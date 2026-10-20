<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\OnboardingState;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformCode;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\SyncCursor;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\PlatformConnectionEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PlatformConnectionMapper
{
    public function toDomain(PlatformConnectionEntity $entity): PlatformConnection
    {
        return PlatformConnection::reconstitute(
            id: PlatformConnectionId::fromString($entity->getId()->toRfc4122()),
            organisationId: Siren::fromInput($entity->getOrganisationId()),
            platform: PlatformCode::fromString($entity->getPlatform()),
            state: OnboardingState::from($entity->getState()),
            rejectionReason: $entity->getRejectionReason(),
            cursor: SyncCursor::at($entity->getCursorUpdatedAfter(), $entity->getCursorLastFlowId()),
            openedAt: $entity->getOpenedAt(),
            verifiedAt: $entity->getVerifiedAt(),
        );
    }

    public function toEntity(PlatformConnection $connection, ?PlatformConnectionEntity $entity = null): PlatformConnectionEntity
    {
        $entity ??= new PlatformConnectionEntity();

        $entity->setId(Uuid::fromString($connection->id()->toString()));
        $entity->setOrganisationId($connection->organisationId()->value());
        $entity->setPlatform($connection->platform()->toString());
        $entity->setState($connection->state()->value);
        $entity->setRejectionReason($connection->rejectionReason());
        $entity->setCursorUpdatedAfter($connection->cursor()->updatedAfter());
        $entity->setCursorLastFlowId($connection->cursor()->lastFlowId());
        $entity->setOpenedAt($connection->openedAt());
        $entity->setVerifiedAt($connection->verifiedAt());

        return $entity;
    }
}
