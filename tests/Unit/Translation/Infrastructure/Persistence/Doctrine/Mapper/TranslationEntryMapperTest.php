<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use App\Translation\Infrastructure\Persistence\Doctrine\Mapper\TranslationEntryMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TranslationEntryMapperTest extends TestCase
{
    public function testToDomain(): void
    {
        $entity = new TranslationEntryEntity();
        $entity->setId(Uuid::v7()->toBinary());
        $entity->setAppScope('clinic');
        $entity->setLocale('fr_FR');
        $entity->setDomain('messages');
        $entity->setTranslationKey('hello');
        $entity->setTranslationValue('Bonjour');
        $entity->setDescription('desc');
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $entity->setCreatedBy(Uuid::v7()->toBinary());
        $entity->setUpdatedBy(Uuid::v7()->toBinary());

        $mapper = new TranslationEntryMapper();

        $domain = $mapper->toDomain($entity);

        self::assertSame('hello', $domain->key()->toString());
        self::assertSame('Bonjour', $domain->text()->toString());
        self::assertSame('desc', $domain->description());
        self::assertSame($entity->getCreatedAt(), $domain->createdAt());
        self::assertSame($entity->getUpdatedAt(), $domain->updatedAt());
    }
}
