<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Persistence\Doctrine\Entity;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use PHPUnit\Framework\TestCase;

final class TranslationEntryEntityTest extends TestCase
{
    public function testGetters(): void
    {
        $entity = new TranslationEntryEntity();
        $entity->setId('binary-id');
        $entity->setAppScope('clinic');
        $entity->setLocale('fr_FR');
        $entity->setDomain('messages');
        $entity->setTranslationKey('hello');
        $entity->setTranslationValue('Bonjour');
        $entity->setDescription('desc');
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        self::assertSame('binary-id', $entity->getId());
        self::assertSame('clinic', $entity->getAppScope());
        self::assertSame('fr_FR', $entity->getLocale());
        self::assertSame('messages', $entity->getDomain());
    }
}
