<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Persistence\Doctrine\Entity;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TranslationEntryEntityTest extends TestCase
{
    public function testGetters(): void
    {
        $entity = new TranslationEntryEntity();
        $id     = Uuid::v7();
        $entity->setId($id);
        $entity->setAppScope('clinic');
        $entity->setLocale('fr_FR');
        $entity->setDomain('messages');
        $entity->setTranslationKey('hello');
        $entity->setTranslationValue('Bonjour');
        $entity->setDescription('desc');
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        self::assertSame($id, $entity->getId());
        self::assertSame('clinic', $entity->getAppScope());
        self::assertSame('fr_FR', $entity->getLocale());
        self::assertSame('messages', $entity->getDomain());
    }
}
