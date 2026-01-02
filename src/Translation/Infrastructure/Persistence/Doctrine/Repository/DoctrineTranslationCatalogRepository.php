<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\TranslationEntry;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use App\Translation\Infrastructure\Persistence\Doctrine\Mapper\TranslationEntryMapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineTranslationCatalogRepository implements TranslationCatalogRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private TranslationEntryMapper $mapper,
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function save(TranslationCatalog $catalog): void
    {
        $connection = $this->em->getConnection();
        $id         = $catalog->id();

        foreach ($catalog->removedKeys() as $key) {
            $connection->delete(
                'translation__entries',
                [
                    'app_scope'       => $id->scope()->value,
                    'locale'          => $id->locale()->toString(),
                    'domain'          => $id->domain()->toString(),
                    'translation_key' => $key->toString(),
                ],
            );
        }

        foreach ($catalog->entries() as $entry) {
            $this->upsertEntry($connection, $id, $entry);
        }
    }

    public function find(TranslationCatalogId $id): ?TranslationCatalog
    {
        $repository = $this->em->getRepository(TranslationEntryEntity::class);
        $entities   = $repository->findBy([
            'appScope' => $id->scope()->value,
            'locale'   => $id->locale()->toString(),
            'domain'   => $id->domain()->toString(),
        ]);

        if ([] === $entities) {
            return null;
        }

        $entries = array_map(
            fn (TranslationEntryEntity $entity): TranslationEntry => $this->mapper->toDomain($entity),
            $entities,
        );

        return TranslationCatalog::reconstitute($id, $entries);
    }

    private function upsertEntry(Connection $connection, TranslationCatalogId $catalogId, TranslationEntry $entry): void
    {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO translation__entries (
                     id,
                     app_scope,
                     locale,
                     domain,
                     translation_key,
                     translation_value,
                     description,
                     created_at,
                     created_by,
                     updated_at,
                     updated_by
                ) VALUES (
                     :id,
                     :scope,
                     :locale,
                     :domain,
                     :key,
                     :value,
                     :description,
                     :createdAt,
                     :createdBy,
                     :updatedAt,
                     :updatedBy
                )
                ON DUPLICATE KEY UPDATE
                    translation_value = VALUES(translation_value),
                    description = VALUES(description),
                    updated_at = VALUES(updated_at),
                    updated_by = VALUES(updated_by)
            SQL,
            [
                'id'          => Uuid::fromString($this->uuidGenerator->generate())->toBinary(),
                'scope'       => $catalogId->scope()->value,
                'locale'      => $catalogId->locale()->toString(),
                'domain'      => $catalogId->domain()->toString(),
                'key'         => $entry->key()->toString(),
                'value'       => $entry->text()->toString(),
                'description' => $entry->description(),
                'createdAt'   => $entry->createdAt()->format('Y-m-d H:i:s.u'),
                'createdBy'   => null !== $entry->createdBy()
                    ? Uuid::fromString($entry->createdBy()->toString())->toBinary()
                    : null,
                'updatedAt' => $entry->updatedAt()->format('Y-m-d H:i:s.u'),
                'updatedBy' => null !== $entry->updatedBy()
                    ? Uuid::fromString($entry->updatedBy()->toString())->toBinary()
                    : null,
            ],
            [
                'id'        => Types::BINARY,
                'createdBy' => Types::BINARY,
                'updatedBy' => Types::BINARY,
            ],
        );
    }
}
