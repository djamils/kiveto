<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Translation\Factory\TranslationEntryEntityFactory;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTranslationCatalogRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindReconstitutesCatalogFromDoctrineEntities(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('shared', 'fr_FR', 'messages');

        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'hello',
            'translationValue' => 'Bonjour',
            'description'      => 'Greeting',
            'createdAt'        => new \DateTimeImmutable('2026-01-09 10:00:00.123456'),
            'createdBy'        => Uuid::fromString('00000000-0000-0000-0000-000000000001')->toBinary(),
            'updatedAt'        => new \DateTimeImmutable('2026-01-09 10:00:00.123456'),
            'updatedBy'        => Uuid::fromString('00000000-0000-0000-0000-000000000001')->toBinary(),
        ]);

        /** @var TranslationCatalogRepository $repo */
        $repo = static::getContainer()->get(TranslationCatalogRepository::class);

        $catalog = $repo->find($catalogId);

        self::assertNotNull($catalog);
        self::assertSame('Bonjour', $catalog->toKeyValue()['hello'] ?? null);
    }

    public function testSaveUpsertsAndDeletesEntries(): void
    {
        /** @var TranslationCatalogRepository $repo */
        $repo = static::getContainer()->get(TranslationCatalogRepository::class);

        $catalogId = TranslationCatalogId::fromStrings('shared', 'fr_FR', 'messages');
        $alice     = ActorId::fromString('00000000-0000-0000-0000-0000000000aa');
        $bob       = ActorId::fromString('00000000-0000-0000-0000-0000000000bb');

        $t0 = new \DateTimeImmutable('2026-01-09 10:00:00.000000');
        $t1 = new \DateTimeImmutable('2026-01-09 11:00:00.000000');

        // Seed initial row via Foundry
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'hello',
            'translationValue' => 'Bonjour',
            'description'      => 'Greeting',
            'createdAt'        => $t0,
            'createdBy'        => Uuid::fromString($alice->toString())->toBinary(),
            'updatedAt'        => $t0,
            'updatedBy'        => Uuid::fromString($alice->toString())->toBinary(),
        ]);

        $catalog = $repo->find($catalogId);
        self::assertNotNull($catalog);

        // Upsert existing key
        $catalog->upsert(
            TranslationKey::fromString('hello'),
            TranslationText::fromString('Salut'),
            $t1,
            $bob,
            'Informal greeting',
        );
        $repo->save($catalog);

        $afterUpdate = $repo->find($catalogId);
        self::assertNotNull($afterUpdate);
        self::assertSame('Salut', $afterUpdate->toKeyValue()['hello'] ?? null);

        // Delete
        $afterUpdate->remove(TranslationKey::fromString('hello'), $bob, $t1);
        $repo->save($afterUpdate);

        self::assertNull($repo->find($catalogId));
    }
}
