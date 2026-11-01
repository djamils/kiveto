<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Document\DocumentReadRepositoryInterface;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\DocumentReadEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PublishedDocumentEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalDocumentReadRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\SqlValues;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalDocumentReadRepositoryTest extends KernelTestCase
{
    private const string DOCUMENT  = '01960000-0000-7000-8000-0000000de001';
    private const string DOCUMENT2 = '01960000-0000-7000-8000-0000000de002';
    private const string USER      = '01960000-0000-7000-8000-0000000de101';
    private const string USER2     = '01960000-0000-7000-8000-0000000de102';

    public function testTheFirstReadDateIsKept(): void
    {
        $reads = $this->reads();
        $utc   = new \DateTimeZone('UTC');
        $paris = new \DateTimeZone('Europe/Paris');
        $reads->markRead(self::DOCUMENT, self::USER, new \DateTimeImmutable('2026-10-31 10:00', $paris));
        $reads->markRead(self::DOCUMENT, self::USER, new \DateTimeImmutable('2026-11-02 10:00', $utc));
        $reads->markRead(self::DOCUMENT, self::USER2, new \DateTimeImmutable('2026-11-01 10:00', $utc));

        self::assertSame(
            [[self::USER, '2026-10-31 09:00:00'], [self::USER2, '2026-11-01 10:00:00']],
            $this->readsOf(self::DOCUMENT),
        );
    }

    public function testANewVersionIsUnreadAgainForEveryone(): void
    {
        $reads = $this->reads();
        $at    = new \DateTimeImmutable('2026-10-31 10:00', new \DateTimeZone('UTC'));
        $reads->markRead(self::DOCUMENT, self::USER, $at);
        $reads->markRead(self::DOCUMENT, self::USER2, $at);
        $reads->markRead(self::DOCUMENT2, self::USER, $at);

        $reads->forgetReads(self::DOCUMENT);

        self::assertSame([], $this->readsOf(self::DOCUMENT));
        self::assertSame([[self::USER, '2026-10-31 10:00:00']], $this->readsOf(self::DOCUMENT2));

        // Read again after the reset: a fresh date.
        $reads->markRead(self::DOCUMENT, self::USER, $at->modify('+3 days'));
        self::assertSame([[self::USER, '2026-11-03 10:00:00']], $this->readsOf(self::DOCUMENT));
    }

    public function testTheTablesAreMappedForTheSchemaTools(): void
    {
        $at = new \DateTimeImmutable('2026-10-31 10:00', new \DateTimeZone('UTC'));
        $this->reads()->markRead(self::DOCUMENT, self::USER, $at);

        $em   = $this->em();
        $key  = ['documentId' => Uuid::fromString(self::DOCUMENT), 'userId' => Uuid::fromString(self::USER)];
        $read = $em->find(DocumentReadEntity::class, $key);
        self::assertInstanceOf(DocumentReadEntity::class, $read);
        self::assertSame('2026-10-31 10:00', $read->readAt->format('Y-m-d H:i'));
        self::assertSame($at, new DocumentReadEntity(Uuid::v7(), Uuid::v7(), $at)->readAt);

        $now      = new \DateTimeImmutable('2026-10-31');
        $document = new PublishedDocumentEntity(
            Uuid::fromString(self::DOCUMENT2),
            Uuid::v7(),
            null,
            'report',
            Uuid::v7(),
            null,
            null,
            ['pet-1'],
            'Compte rendu',
            $now,
            null,
            null,
            0,
            null,
            0,
            'awaiting_link',
            $now,
            $now,
        );
        $em->persist($document);
        $em->flush();
        $em->clear();

        $found = $em->find(PublishedDocumentEntity::class, Uuid::fromString(self::DOCUMENT2));
        self::assertInstanceOf(PublishedDocumentEntity::class, $found);
        self::assertSame(['report', ['pet-1'], 'awaiting_link'], [$found->type, $found->petIds, $found->status]);
    }

    public function testAJsonListOfStringsIsReadDefensively(): void
    {
        self::assertSame(['a', 'b'], SqlValues::stringList('["a", 2, null, "b"]'));
        self::assertSame(['x'], SqlValues::stringList('{"k": "x"}'));
        self::assertSame([], SqlValues::stringList('"a"'));
        self::assertSame([], SqlValues::stringList('not json'));
        self::assertSame([], SqlValues::stringList('[]'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function readsOf(string $documentId): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT user_id, read_at FROM ' . DbalDocumentReadRepository::TABLE
            . ' WHERE document_id = :document ORDER BY user_id',
            ['document' => SqlValues::binary($documentId)],
        );

        return array_map(
            static fn (array $row): array => [
                Uuid::fromBinary(RowAccessor::string($row, 'user_id'))->toRfc4122(),
                RowAccessor::string($row, 'read_at'),
            ],
            $rows,
        );
    }

    private function reads(): DocumentReadRepositoryInterface
    {
        // Built by hand: the container drops the alias while nothing consumes it.
        return new DbalDocumentReadRepository($this->connection());
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
