<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientNoteEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ClientNoteMapper
{
    public function toDomain(ClientNoteEntity $entity): ClientNote
    {
        return ClientNote::reconstitute(
            id: ClientNoteId::fromString($entity->id->toString()),
            clinicId: ClinicId::fromString($entity->clinicId->toString()),
            clientId: ClientId::fromString($entity->clientId->toString()),
            kind: $entity->kind,
            content: $entity->content,
            authorName: $entity->authorName,
            createdAt: $entity->createdAt,
        );
    }

    public function toEntity(ClientNote $note): ClientNoteEntity
    {
        return new ClientNoteEntity(
            id: Uuid::fromString($note->id()->toString()),
            clinicId: Uuid::fromString($note->clinicId()->toString()),
            clientId: Uuid::fromString($note->clientId()->toString()),
            kind: $note->kind(),
            content: $note->content(),
            authorName: $note->authorName(),
            createdAt: $note->createdAt(),
        );
    }
}
