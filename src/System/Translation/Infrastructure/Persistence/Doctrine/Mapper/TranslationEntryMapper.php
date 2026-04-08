<?php

declare(strict_types=1);

namespace App\System\Translation\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\Translation\Domain\TranslationEntry;
use App\System\Translation\Domain\ValueObject\ActorId;
use App\System\Translation\Domain\ValueObject\TranslationKey;
use App\System\Translation\Domain\ValueObject\TranslationText;
use App\System\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;

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
