<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper;

use App\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\ClinicalCare\Domain\ValueObject\NoteType;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ClinicalNoteMapper
{
    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function toEntity(ClinicalNoteRecord $note, string $consultationIdBinary): ClinicalNoteEntity
    {
        $entity = new ClinicalNoteEntity();
        $entity->setId(Uuid::fromString($this->uuidGenerator->generate())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setNoteType($note->noteType->value);
        $entity->setContent($note->content);
        $entity->setCreatedAtUtc($note->createdAt);
        $entity->setCreatedByUserId(Uuid::fromString($note->createdByUserId->toString())->toBinary());

        return $entity;
    }

    public function toDomain(ClinicalNoteEntity $entity): ClinicalNoteRecord
    {
        return new ClinicalNoteRecord(
            noteType: NoteType::from($entity->getNoteType()),
            content: $entity->getContent(),
            createdAt: $entity->getCreatedAtUtc(),
            createdByUserId: UserId::fromString(Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122()),
        );
    }
}
