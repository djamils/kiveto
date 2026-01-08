<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Translation\Factory\TranslationEntryFactory;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationDomain;
use App\Translation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTranslationSearchRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTranslationSearchRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineTranslationSearchRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);

        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->repository = new DoctrineTranslationSearchRepository($connection);
    }

    public function testFindCatalogReturnsEmptyArrayWhenNoCatalogExists(): void
    {
        $result = $this->repository->findCatalog(
            AppScope::CLINIC,
            Locale::fromString('fr'),
            TranslationDomain::fromString('messages'),
        );

        self::assertSame([], $result);
    }

    public function testFindCatalogReturnsKeyValuePairs(): void
    {
        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'test_search_find_welcome',
            'translationValue' => 'Bienvenue',
        ]);

        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'test_search_find_goodbye',
            'translationValue' => 'Au revoir',
        ]);

        $result = $this->repository->findCatalog(
            AppScope::CLINIC,
            Locale::fromString('fr'),
            TranslationDomain::fromString('messages'),
        );

        self::assertCount(2, $result);
        self::assertSame('Bienvenue', $result['test_search_find_welcome']);
        self::assertSame('Au revoir', $result['test_search_find_goodbye']);
    }

    public function testSearchWithEmptyCriteriaReturnsAllEntries(): void
    {
        TranslationEntryFactory::createMany(3);

        $result = $this->repository->search([], 1, 20);

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        self::assertCount(3, $result['items']);
        self::assertSame(3, $result['total']);
    }

    public function testSearchWithScopeCriteria(): void
    {
        TranslationEntryFactory::createOne(['appScope' => 'clinic']);
        TranslationEntryFactory::createOne(['appScope' => 'portal']);
        TranslationEntryFactory::createOne(['appScope' => 'clinic']);

        $result = $this->repository->search(
            ['scope' => AppScope::CLINIC],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);

        foreach ($result['items'] as $item) {
            self::assertSame('clinic', $item['scope']);
        }
    }

    public function testSearchWithLocaleCriteria(): void
    {
        TranslationEntryFactory::createOne(['locale' => 'fr']);
        TranslationEntryFactory::createOne(['locale' => 'en']);
        TranslationEntryFactory::createOne(['locale' => 'fr']);

        $result = $this->repository->search(
            ['locale' => Locale::fromString('fr')],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchWithDomainCriteria(): void
    {
        TranslationEntryFactory::createOne(['domain' => 'messages']);
        TranslationEntryFactory::createOne(['domain' => 'validators']);
        TranslationEntryFactory::createOne(['domain' => 'messages']);

        $result = $this->repository->search(
            ['domain' => TranslationDomain::fromString('messages')],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchWithKeyContainsCriteria(): void
    {
        TranslationEntryFactory::createOne(['translationKey' => 'user.name']);
        TranslationEntryFactory::createOne(['translationKey' => 'user.email']);
        TranslationEntryFactory::createOne(['translationKey' => 'product.title']);

        $result = $this->repository->search(
            ['keyContains' => 'user'],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchWithValueContainsCriteria(): void
    {
        TranslationEntryFactory::createOne(['translationValue' => 'Welcome to our application']);
        TranslationEntryFactory::createOne(['translationValue' => 'Welcome back']);
        TranslationEntryFactory::createOne(['translationValue' => 'Goodbye']);

        $result = $this->repository->search(
            ['valueContains' => 'Welcome'],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchWithPagination(): void
    {
        TranslationEntryFactory::createMany(25);

        $page1 = $this->repository->search([], 1, 10);
        $page2 = $this->repository->search([], 2, 10);
        $page3 = $this->repository->search([], 3, 10);

        self::assertCount(10, $page1['items']);
        self::assertCount(10, $page2['items']);
        self::assertCount(5, $page3['items']);
        self::assertSame(25, $page1['total']);
        self::assertSame(25, $page2['total']);
        self::assertSame(25, $page3['total']);
    }

    public function testSearchWithUpdatedAfterCriteria(): void
    {
        $oldDate = new \DateTimeImmutable('2024-01-01 10:00:00');
        $newDate = new \DateTimeImmutable('2024-12-01 10:00:00');

        TranslationEntryFactory::createOne(['updatedAt' => $oldDate]);
        TranslationEntryFactory::createOne(['updatedAt' => $newDate]);
        TranslationEntryFactory::createOne(['updatedAt' => $newDate]);

        $result = $this->repository->search(
            ['updatedAfter' => new \DateTimeImmutable('2024-06-01')],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchWithUpdatedByCriteria(): void
    {
        $actorId = Uuid::v7()->toRfc4122();

        TranslationEntryFactory::createOne(['updatedBy' => Uuid::fromString($actorId)->toBinary()]);
        TranslationEntryFactory::createOne(['updatedBy' => null]);
        TranslationEntryFactory::createOne(['updatedBy' => Uuid::fromString($actorId)->toBinary()]);

        $result = $this->repository->search(
            ['updatedBy' => $actorId],
            1,
            20,
        );

        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testListDomainsWithoutFilters(): void
    {
        TranslationEntryFactory::createOne(['domain' => 'messages']);
        TranslationEntryFactory::createOne(['domain' => 'validators']);
        TranslationEntryFactory::createOne(['domain' => 'messages']);
        TranslationEntryFactory::createOne(['domain' => 'forms']);

        $result = $this->repository->listDomains(null, null);

        self::assertCount(3, $result);
        self::assertContains('messages', $result);
        self::assertContains('validators', $result);
        self::assertContains('forms', $result);
    }

    public function testListDomainsWithScopeFilter(): void
    {
        TranslationEntryFactory::createOne(['appScope' => 'clinic', 'domain' => 'messages']);
        TranslationEntryFactory::createOne(['appScope' => 'clinic', 'domain' => 'validators']);
        TranslationEntryFactory::createOne(['appScope' => 'portal', 'domain' => 'forms']);

        $result = $this->repository->listDomains(AppScope::CLINIC, null);

        self::assertCount(2, $result);
        self::assertContains('messages', $result);
        self::assertContains('validators', $result);
        self::assertNotContains('forms', $result);
    }

    public function testListDomainsWithLocaleFilter(): void
    {
        TranslationEntryFactory::createOne(['locale' => 'fr', 'domain' => 'messages']);
        TranslationEntryFactory::createOne(['locale' => 'fr', 'domain' => 'validators']);
        TranslationEntryFactory::createOne(['locale' => 'en', 'domain' => 'forms']);

        $result = $this->repository->listDomains(null, Locale::fromString('fr'));

        self::assertCount(2, $result);
        self::assertContains('messages', $result);
        self::assertContains('validators', $result);
        self::assertNotContains('forms', $result);
    }

    public function testListLocalesWithoutFilters(): void
    {
        TranslationEntryFactory::createOne(['locale' => 'fr']);
        TranslationEntryFactory::createOne(['locale' => 'en']);
        TranslationEntryFactory::createOne(['locale' => 'fr']);
        TranslationEntryFactory::createOne(['locale' => 'de']);

        $result = $this->repository->listLocales(null, null);

        self::assertCount(3, $result);
        self::assertContains('fr', $result);
        self::assertContains('en', $result);
        self::assertContains('de', $result);
    }

    public function testListLocalesWithScopeFilter(): void
    {
        TranslationEntryFactory::createOne(['appScope' => 'clinic', 'locale' => 'fr']);
        TranslationEntryFactory::createOne(['appScope' => 'clinic', 'locale' => 'en']);
        TranslationEntryFactory::createOne(['appScope' => 'portal', 'locale' => 'de']);

        $result = $this->repository->listLocales(AppScope::CLINIC, null);

        self::assertCount(2, $result);
        self::assertContains('fr', $result);
        self::assertContains('en', $result);
        self::assertNotContains('de', $result);
    }

    public function testListLocalesWithDomainFilter(): void
    {
        TranslationEntryFactory::createOne(['domain' => 'messages', 'locale' => 'fr']);
        TranslationEntryFactory::createOne(['domain' => 'messages', 'locale' => 'en']);
        TranslationEntryFactory::createOne(['domain' => 'validators', 'locale' => 'de']);

        $result = $this->repository->listLocales(null, TranslationDomain::fromString('messages'));

        self::assertCount(2, $result);
        self::assertContains('fr', $result);
        self::assertContains('en', $result);
        self::assertNotContains('de', $result);
    }

    public function testSearchResultsAreOrderedByUpdatedAtDescAndKeyAsc(): void
    {
        $oldDate = new \DateTimeImmutable('2024-01-01 10:00:00');
        $newDate = new \DateTimeImmutable('2024-12-01 10:00:00');

        TranslationEntryFactory::createOne(['translationKey' => 'c.key', 'updatedAt' => $newDate]);
        TranslationEntryFactory::createOne(['translationKey' => 'a.key', 'updatedAt' => $newDate]);
        TranslationEntryFactory::createOne(['translationKey' => 'b.key', 'updatedAt' => $oldDate]);

        $result = $this->repository->search([], 1, 20);

        self::assertCount(3, $result['items']);
        self::assertSame('a.key', $result['items'][0]['key']);
        self::assertSame('c.key', $result['items'][1]['key']);
        self::assertSame('b.key', $result['items'][2]['key']);
    }
}
