<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientCommunicationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ClientCommunicationMapper
{
    public function toDomain(ClientCommunicationEntity $entity): ClientCommunication
    {
        return ClientCommunication::reconstitute(
            id: ClientCommunicationId::fromString($entity->id->toString()),
            clinicId: ClinicId::fromString($entity->clinicId->toString()),
            clientId: ClientId::fromString($entity->clientId->toString()),
            channel: $entity->channel,
            direction: $entity->direction,
            subject: $entity->subject,
            body: $entity->body,
            authorName: $entity->authorName,
            occurredAt: $entity->occurredAt,
            origin: $entity->origin,
            deliveryStatus: $entity->deliveryStatus,
            notificationMessageId: $entity->notificationMessageId?->toString(),
            deliveryStatusSequence: $entity->deliveryStatusSequence,
            statusChangedAt: $entity->statusChangedAt,
        );
    }

    public function toEntity(ClientCommunication $communication): ClientCommunicationEntity
    {
        return new ClientCommunicationEntity(
            id: Uuid::fromString($communication->id()->toString()),
            clinicId: Uuid::fromString($communication->clinicId()->toString()),
            clientId: Uuid::fromString($communication->clientId()->toString()),
            channel: $communication->channel(),
            direction: $communication->direction(),
            subject: $communication->subject(),
            body: $communication->body(),
            authorName: $communication->authorName(),
            occurredAt: $communication->occurredAt(),
            origin: $communication->origin(),
            deliveryStatus: $communication->deliveryStatus(),
            notificationMessageId: null === $communication->notificationMessageId() ? null : Uuid::fromString($communication->notificationMessageId()),
            deliveryStatusSequence: $communication->deliveryStatusSequence(),
            statusChangedAt: $communication->statusChangedAt(),
        );
    }

    /** Copies what an automated entry may change onto its row. */
    public function updateEntity(ClientCommunicationEntity $entity, ClientCommunication $communication): void
    {
        $entity->occurredAt             = $communication->occurredAt();
        $entity->deliveryStatus         = $communication->deliveryStatus();
        $entity->deliveryStatusSequence = $communication->deliveryStatusSequence();
        $entity->statusChangedAt        = $communication->statusChangedAt();
    }
}
