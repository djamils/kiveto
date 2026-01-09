<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Translation\Factory\TranslationEntryEntityFactory;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationDomain;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTranslationSearchRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindCatalogReturnsKeyValueMap(): void
    {
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'hello',
            'translationValue' => 'Bonjour',
        ]);
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'bye',
            'translationValue' => 'Au revoir',
        ]);

        /** @var TranslationSearchRepository $repo */
        $repo = static::getContainer()->get(TranslationSearchRepository::class);

        $catalog = $repo->findCatalog(
            AppScope::fromString('shared'),
            Locale::fromString('fr_FR'),
            TranslationDomain::fromString('messages'),
        );

        self::assertSame('Bonjour', $catalog['hello'] ?? null);
        self::assertSame('Au revoir', $catalog['bye'] ?? null);
    }

    public function testListDomainsAndLocales(): void
    {
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'k1',
            'translationValue' => 'v1',
        ]);
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'en_US',
            'domain'           => 'validators',
            'translationKey'   => 'k2',
            'translationValue' => 'v2',
        ]);

        /** @var TranslationSearchRepository $repo */
        $repo = static::getContainer()->get(TranslationSearchRepository::class);

        self::assertSame(
            ['messages'],
            $repo->listDomains(AppScope::fromString('shared'), Locale::fromString('fr_FR')),
        );

        self::assertSame(
            ['en_US', 'fr_FR'],
            $repo->listLocales(AppScope::fromString('shared'), null),
        );

        self::assertSame(
            ['fr_FR'],
            $repo->listLocales(AppScope::fromString('shared'), TranslationDomain::fromString('messages')),
        );
    }

    public function testSearchSupportsCriteriaAndPagination(): void
    {
        $aliceUuid = '00000000-0000-0000-0000-0000000000aa';
        $bobUuid   = '00000000-0000-0000-0000-0000000000bb';

        $t0 = new \DateTimeImmutable('2026-01-09 10:00:00.000000');
        $t1 = new \DateTimeImmutable('2026-01-09 11:00:00.000000');

        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'hello',
            'translationValue' => 'Salut',
            'description'      => 'Informal greeting',
            'createdAt'        => $t0,
            'createdBy'        => Uuid::fromString($aliceUuid)->toBinary(),
            'updatedAt'        => $t1,
            'updatedBy'        => Uuid::fromString($bobUuid)->toBinary(),
        ]);
        TranslationEntryEntityFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => 'welcome',
            'translationValue' => 'Bienvenue',
            'description'      => 'Welcome message',
            'createdAt'        => $t0,
            'createdBy'        => Uuid::fromString($aliceUuid)->toBinary(),
            'updatedAt'        => $t0,
            'updatedBy'        => Uuid::fromString($aliceUuid)->toBinary(),
        ]);

        /** @var TranslationSearchRepository $repo */
        $repo = static::getContainer()->get(TranslationSearchRepository::class);

        $criteria = [
            'scope'         => AppScope::fromString('shared'),
            'locale'        => Locale::fromString('fr_FR'),
            'domain'        => TranslationDomain::fromString('messages'),
            'keyContains'   => 'hel',
            'valueContains' => 'Sal',
            'updatedBy'     => $bobUuid,
            'updatedAfter'  => new \DateTimeImmutable('2026-01-09 10:30:00.000000'),
            'createdBy'     => $aliceUuid,
            'createdAfter'  => new \DateTimeImmutable('2026-01-09 09:30:00.000000'),
        ];

        $page1 = $repo->search($criteria, 1, 10);

        self::assertSame(1, $page1['total']);
        self::assertCount(1, $page1['items']);
        self::assertSame('hello', $page1['items'][0]['key']);
        self::assertSame('Salut', $page1['items'][0]['value']);
        self::assertSame($bobUuid, $page1['items'][0]['updatedBy']);
        self::assertSame($aliceUuid, $page1['items'][0]['createdBy']);

        // Pagination sanity: page 2 with perPage > total => empty
        $page2 = $repo->search(
            [
                'scope'  => AppScope::fromString('shared'),
                'locale' => Locale::fromString('fr_FR'),
                'domain' => TranslationDomain::fromString('messages'),
            ],
            2,
            50,
        );

        self::assertSame(2, $page2['total']);
        self::assertCount(0, $page2['items']);
    }
}
