<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPublishedDocumentRepositoryTest extends KernelTestCase
{
    private const string D1           = '01960000-0000-7000-8000-0000000dd001';
    private const string D2           = '01960000-0000-7000-8000-0000000dd002';
    private const string D3           = '01960000-0000-7000-8000-0000000dd003';
    private const string D4           = '01960000-0000-7000-8000-0000000dd004';
    private const string CLINIC       = '01960000-0000-7000-8000-0000000dc001';
    private const string CLINIC2      = '01960000-0000-7000-8000-0000000dc002';
    private const string CLIENT       = '01960000-0000-7000-8000-0000000dc101';
    private const string CLIENT2      = '01960000-0000-7000-8000-0000000dc102';
    private const string SOURCE       = '01960000-0000-7000-8000-0000000dc201';
    private const string SOURCE2      = '01960000-0000-7000-8000-0000000dc202';
    private const string SOURCE3      = '01960000-0000-7000-8000-0000000dc203';
    private const string SOURCE4      = '01960000-0000-7000-8000-0000000dc204';
    private const string CONSULTATION = '01960000-0000-7000-8000-0000000dc301';
    private const string PATIENT      = '01960000-0000-7000-8000-0000000dc401';
    private const string PATIENT2     = '01960000-0000-7000-8000-0000000dc402';

    public function testEveryPropertyIsReadBackAndUpdatedInPlace(): void
    {
        $documents = $this->documents();
        $paris     = new \DateTimeZone('Europe/Paris');
        $documents->save(PublishedDocument::reconstitute(
            PublishedDocumentId::fromString(self::D1),
            self::CLINIC,
            self::CLIENT,
            DocumentType::REPORT,
            self::SOURCE,
            self::CONSULTATION,
            self::PATIENT,
            ['pet-1', 'pet-2'],
            'Compte rendu',
            new \DateTimeImmutable('2026-10-30 10:00', $paris),
            'Dr Martin',
            'portal/key-v2',
            2,
            3,
            1,
            PublicationStatus::PENDING_FILE,
            new \DateTimeImmutable('2026-10-29 08:00', $paris),
            new \DateTimeImmutable('2026-10-31 12:00', $paris),
        ));

        $found = $this->connection()->transactional(
            fn (): ?PublishedDocument => $documents->findForUpdate(self::CLINIC, DocumentType::REPORT, self::SOURCE),
        );
        self::assertNotNull($found);
        self::assertSame(
            [
                self::D1, self::CLINIC, self::CLIENT, DocumentType::REPORT, self::SOURCE, self::CONSULTATION,
                self::PATIENT, ['pet-1', 'pet-2'], 'Compte rendu', '2026-10-30 09:00', 'UTC', 'Dr Martin',
                'portal/key-v2', 2, 3, 1, PublicationStatus::PENDING_FILE, '2026-10-29 07:00', '2026-10-31 11:00',
            ],
            [
                $found->id()->toString(), $found->clinicId(), $found->clientId(), $found->type(), $found->sourceRef(),
                $found->consultationId(), $found->patientId(), $found->petIds(), $found->title(),
                $found->issuedAt()->format('Y-m-d H:i'), $found->issuedAt()->getTimezone()->getName(),
                $found->practitionerName(), $found->storageKey(), $found->fileVersion(), $found->pendingFileVersion(),
                $found->renderAttempts(), $found->status(), $found->publishedAt()->format('Y-m-d H:i'),
                $found->updatedAt()->format('Y-m-d H:i'),
            ],
        );
        self::assertNull($documents->findForUpdate(self::CLINIC, DocumentType::INVOICE, self::SOURCE), 'other type');
        self::assertNull($documents->findForUpdate(self::CLINIC2, DocumentType::REPORT, self::SOURCE), 'other clinic');

        $found->attachFile(3, 'portal/key-v3', new \DateTimeImmutable('2026-11-01 08:00', new \DateTimeZone('UTC')));
        $found->assignPets(['pet-3'], new \DateTimeImmutable('2026-11-01 08:00', new \DateTimeZone('UTC')));
        $documents->save($found);

        $again = $documents->findByIdForUpdate(self::D1);
        self::assertNotNull($again);
        self::assertSame(
            [
                'portal/key-v3', 3, null, 0, PublicationStatus::AVAILABLE, ['pet-3'], '2026-11-01 08:00',
                '2026-10-29 07:00',
            ],
            [
                $again->storageKey(), $again->fileVersion(), $again->pendingFileVersion(), $again->renderAttempts(),
                $again->status(), $again->petIds(), $again->updatedAt()->format('Y-m-d H:i'),
                $again->publishedAt()->format('Y-m-d H:i'),
            ],
        );
        self::assertNull($documents->findByIdForUpdate(self::D2));
    }

    public function testATombstoneKeepsNullsAndWinsOverALatePublication(): void
    {
        $documents = $this->documents();
        $now       = new \DateTimeImmutable('2026-10-31 09:00', new \DateTimeZone('UTC'));

        $type = DocumentType::PRESCRIPTION;

        self::assertTrue($documents->insertIfAbsent(
            PublishedDocument::tombstone(PublishedDocumentId::fromString(self::D1), self::CLINIC, $type, self::SOURCE, $now),
        ));
        self::assertFalse($documents->insertIfAbsent($this->published(self::D2, $type, self::SOURCE, true, $now)));

        $found = $documents->findForUpdate(self::CLINIC, DocumentType::PRESCRIPTION, self::SOURCE);
        self::assertNotNull($found);
        self::assertSame(
            [self::D1, null, null, null, [], '', null, null, null, PublicationStatus::WITHDRAWN],
            [
                $found->id()->toString(), $found->clientId(), $found->consultationId(), $found->patientId(),
                $found->petIds(), $found->title(), $found->practitionerName(), $found->storageKey(),
                $found->pendingFileVersion(), $found->status(),
            ],
        );
        self::assertNull($documents->findByIdForUpdate(self::D2));

        // Another source of the same clinic and type is free.
        self::assertTrue($documents->insertIfAbsent($this->published(self::D2, $type, self::SOURCE2, true, $now)));
    }

    public function testTheDocumentsAwaitingALinkOldestFirst(): void
    {
        $documents = $this->documents();
        $invoice   = DocumentType::INVOICE;
        $documents->save($this->published(self::D1, $invoice, self::SOURCE, false, new \DateTimeImmutable('2026-10-31 10:00')));
        $documents->save($this->published(self::D2, $invoice, self::SOURCE2, false, new \DateTimeImmutable('2026-10-30 10:00')));
        $documents->save($this->published(self::D3, $invoice, self::SOURCE3, true, new \DateTimeImmutable('2026-10-29 10:00')));
        $documents->save(
            $this->published(self::D4, DocumentType::REPORT, self::SOURCE4, false, new \DateTimeImmutable('2026-10-28'), self::CLIENT2),
        );

        $awaiting = $documents->findAwaitingLink(self::CLINIC, self::CLIENT, 10);
        self::assertSame([self::D2, self::D1], $this->ids($awaiting));
        self::assertSame([self::D2], $this->ids($documents->findAwaitingLink(self::CLINIC, self::CLIENT, 1)));
        self::assertSame([self::D4], $this->ids($documents->findAwaitingLink(self::CLINIC, self::CLIENT2, 10)));
        self::assertSame([], $documents->findAwaitingLink(self::CLINIC2, self::CLIENT, 10));
    }

    public function testTheStalePendingFilesBelowTheMaximumAttempts(): void
    {
        $documents = $this->documents();
        $documents->save($this->pending(self::D1, self::SOURCE, '2026-10-31 08:00', 0));
        $documents->save($this->pending(self::D2, self::SOURCE2, '2026-10-31 07:00', 4));
        $documents->save($this->pending(self::D3, self::SOURCE3, '2026-10-31 06:00', 5));
        $documents->save($this->pending(self::D4, self::SOURCE4, '2026-10-31 09:30', 0));
        $documents->save($this->published(
            '01960000-0000-7000-8000-0000000dd005',
            DocumentType::INVOICE,
            '01960000-0000-7000-8000-0000000dc205',
            false,
            new \DateTimeImmutable('2026-10-01'),
        ));

        $before = new \DateTimeImmutable('2026-10-31 10:00', new \DateTimeZone('Europe/Paris'));
        self::assertSame([self::D2, self::D1], $this->ids($documents->findStalePendingFiles($before, 10)));
        self::assertSame([self::D2], $this->ids($documents->findStalePendingFiles($before, 1)));
        $exactly = new \DateTimeImmutable('2026-10-31 07:00', new \DateTimeZone('UTC'));
        self::assertSame([], $documents->findStalePendingFiles($exactly, 10), 'strictly before');
    }

    public function testTheDocumentsOfAPatient(): void
    {
        $documents = $this->documents();
        $now       = new \DateTimeImmutable('2026-10-31');
        $documents->save($this->published(self::D2, DocumentType::INVOICE, self::SOURCE, true, $now));
        $documents->save($this->published(self::D1, DocumentType::REPORT, self::SOURCE2, true, $now));
        $other = $this->published(self::D3, DocumentType::REPORT, self::SOURCE3, true, $now);
        $other->repointPatient(self::PATIENT2, $now);
        $documents->save($other);

        self::assertSame([self::D1, self::D2], $this->ids($documents->findByPatient(self::CLINIC, self::PATIENT)));
        self::assertSame([self::D3], $this->ids($documents->findByPatient(self::CLINIC, self::PATIENT2)));
        self::assertSame([], $documents->findByPatient(self::CLINIC2, self::PATIENT));
    }

    private function published(
        string $id,
        DocumentType $type,
        string $source,
        bool $linked,
        \DateTimeImmutable $now,
        string $client = self::CLIENT,
    ): PublishedDocument {
        return PublishedDocument::publish(
            PublishedDocumentId::fromString($id),
            self::CLINIC,
            $client,
            $type,
            $source,
            null,
            self::PATIENT,
            [],
            'Document',
            $now,
            null,
            $linked,
            $now,
        );
    }

    private function pending(string $id, string $source, string $updatedAt, int $attempts): PublishedDocument
    {
        $at = new \DateTimeImmutable($updatedAt, new \DateTimeZone('UTC'));

        return PublishedDocument::reconstitute(
            PublishedDocumentId::fromString($id),
            self::CLINIC,
            self::CLIENT,
            DocumentType::REPORT,
            $source,
            null,
            null,
            [],
            'Compte rendu',
            $at,
            null,
            null,
            0,
            1,
            $attempts,
            PublicationStatus::PENDING_FILE,
            $at,
            $at,
        );
    }

    /**
     * @param list<PublishedDocument> $documents
     *
     * @return list<string>
     */
    private function ids(array $documents): array
    {
        return array_map(static fn (PublishedDocument $document): string => $document->id()->toString(), $documents);
    }

    private function documents(): PublishedDocumentRepositoryInterface
    {
        // Built by hand: the container drops the alias while nothing consumes it.
        return new DbalPublishedDocumentRepository($this->connection());
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
