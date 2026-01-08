<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Translation\Factory\TranslationEntryFactory;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use App\Translation\Infrastructure\Persistence\Doctrine\Mapper\TranslationEntryMapper;
use App\Translation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTranslationCatalogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTranslationCatalogRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineTranslationCatalogRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);

        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var TranslationEntryMapper $mapper */
        $mapper = $container->get(TranslationEntryMapper::class);
        /** @var UuidGeneratorInterface $uuidGenerator */
        $uuidGenerator = $container->get(UuidGeneratorInterface::class);

        $this->entityManager = $entityManager;

        $this->repository = new DoctrineTranslationCatalogRepository(
            $entityManager,
            $mapper,
            $uuidGenerator,
        );
    }

    public function testFindReturnsNullWhenCatalogDoesNotExist(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('clinic', 'fr', 'messages');

        $result = $this->repository->find($catalogId);

        self::assertNull($result);
    }

    public function testFindReturnsCatalogWithEntries(): void
    {
        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'test_find_catalog_welcome',
            'translationValue' => 'Bienvenue',
        ]);

        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'test_find_catalog_goodbye',
            'translationValue' => 'Au revoir',
        ]);

        $catalogId = TranslationCatalogId::fromStrings('clinic', 'fr', 'messages');

        $result = $this->repository->find($catalogId);

        self::assertInstanceOf(TranslationCatalog::class, $result);
        self::assertSame($catalogId, $result->id());
        self::assertCount(2, $result->entries());
        self::assertTrue($result->hasKey(TranslationKey::fromString('test_find_catalog_welcome')));
        self::assertTrue($result->hasKey(TranslationKey::fromString('test_find_catalog_goodbye')));
    }

    public function testSaveCreatesNewEntries(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('portal', 'en', 'forms');
        $catalog   = TranslationCatalog::createEmpty($catalogId);

        $now = new \DateTimeImmutable();

        $catalog->upsert(
            TranslationKey::fromString('submit'),
            TranslationText::fromString('Submit'),
            $now,
        );

        $catalog->upsert(
            TranslationKey::fromString('cancel'),
            TranslationText::fromString('Cancel'),
            $now,
        );

        $this->repository->save($catalog);
        $this->entityManager->clear();

        $found = $this->repository->find($catalogId);

        self::assertNotNull($found);
        self::assertCount(2, $found->entries());
        self::assertTrue($found->hasKey(TranslationKey::fromString('submit')));
        self::assertTrue($found->hasKey(TranslationKey::fromString('cancel')));
    }

    public function testSaveUpdatesExistingEntries(): void
    {
        TranslationEntryFactory::createOne([
            'appScope'         => 'shared',
            'locale'           => 'de',
            'domain'           => 'emails',
            'translationKey'   => 'test_save_updates_subject_welcome',
            'translationValue' => 'Willkommen',
        ]);

        $catalogId = TranslationCatalogId::fromStrings('shared', 'de', 'emails');
        $catalog   = $this->repository->find($catalogId);

        self::assertNotNull($catalog);

        $now = new \DateTimeImmutable();

        $catalog->upsert(
            TranslationKey::fromString('test_save_updates_subject_welcome'),
            TranslationText::fromString('Herzlich Willkommen'),
            $now,
        );

        $this->repository->save($catalog);
        $this->entityManager->clear();

        $found = $this->repository->find($catalogId);

        self::assertNotNull($found);
        self::assertCount(1, $found->entries());
    }

    public function testSaveRemovesDeletedKeys(): void
    {
        TranslationEntryFactory::createOne([
            'appScope'         => 'backoffice',
            'locale'           => 'es',
            'domain'           => 'menu',
            'translationKey'   => 'test_save_removes_dashboard',
            'translationValue' => 'Panel de control',
        ]);

        TranslationEntryFactory::createOne([
            'appScope'         => 'backoffice',
            'locale'           => 'es',
            'domain'           => 'menu',
            'translationKey'   => 'test_save_removes_settings',
            'translationValue' => 'Configuración',
        ]);

        $catalogId = TranslationCatalogId::fromStrings('backoffice', 'es', 'menu');
        $catalog   = $this->repository->find($catalogId);

        self::assertNotNull($catalog);
        self::assertCount(2, $catalog->entries());

        $now = new \DateTimeImmutable();

        $catalog->remove(TranslationKey::fromString('test_save_removes_settings'), null, $now);

        $this->repository->save($catalog);
        $this->entityManager->clear();

        $found = $this->repository->find($catalogId);

        self::assertNotNull($found);
        self::assertCount(1, $found->entries());
        self::assertTrue($found->hasKey(TranslationKey::fromString('test_save_removes_dashboard')));
        self::assertFalse($found->hasKey(TranslationKey::fromString('test_save_removes_settings')));
    }

    public function testFindFiltersByScopeLocaleAndDomain(): void
    {
        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'clinic.fr.messages',
            'translationValue' => 'Value 1',
        ]);

        TranslationEntryFactory::createOne([
            'appScope'         => 'clinic',
            'locale'           => 'en',
            'domain'           => 'messages',
            'translationKey'   => 'clinic.en.messages',
            'translationValue' => 'Value 2',
        ]);

        TranslationEntryFactory::createOne([
            'appScope'         => 'portal',
            'locale'           => 'fr',
            'domain'           => 'messages',
            'translationKey'   => 'portal.fr.messages',
            'translationValue' => 'Value 3',
        ]);

        $this->entityManager->clear();

        $catalogId = TranslationCatalogId::fromStrings('clinic', 'fr', 'messages');
        $result    = $this->repository->find($catalogId);

        self::assertNotNull($result);
        self::assertCount(1, $result->entries());
    }
}
