<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Translation\Domain\TranslationEntry;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;

final class TranslationEntryMapper
{
    public function toDomain(TranslationEntryEntity $entity): TranslationEntry
    {
        return new TranslationEntry(
            TranslationKey::fromString($entity->getTranslationKey()),
            TranslationText::fromString($entity->getTranslationValue()),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            null !== $entity->getCreatedBy()
                ? ActorId::fromString($entity->getCreatedBy()->toRfc4122())
                : null,
            null !== $entity->getUpdatedBy()
                ? ActorId::fromString($entity->getUpdatedBy()->toRfc4122())
                : null,
            $entity->getDescription(),
        );
    }
}
