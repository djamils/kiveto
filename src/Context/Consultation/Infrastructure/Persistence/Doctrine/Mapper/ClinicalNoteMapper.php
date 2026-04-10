<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use Symfony\Component\Uid\Uuid;

final class ClinicalNoteMapper
{
    public function toEntity(ClinicalNoteRecord $note, string $consultationIdBinary): ClinicalNoteEntity
    {
        $entity = new ClinicalNoteEntity();
        $entity->setId(Uuid::fromString($note->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setNoteType($note->getNoteType()->value);
        $entity->setContent($note->getContent());
        $entity->setCreatedAtUtc($note->getCreatedAtUtc());
        $entity->setCreatedByUserId(Uuid::fromString($note->getCreatedByUserId())->toBinary());

        return $entity;
    }

    public function toDomain(ClinicalNoteEntity $entity): ClinicalNoteRecord
    {
        return ClinicalNoteRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            noteType: NoteType::from($entity->getNoteType()),
            content: $entity->getContent(),
            createdAtUtc: $entity->getCreatedAtUtc(),
            createdByUserId: Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122(),
        );
    }
}
