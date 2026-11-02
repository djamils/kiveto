<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentReadModel;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The « Partagé le … » of the consultation documents panel. */
final class DbalPublishedDocumentReportSharedAtTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-0000000ea001';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000ea002';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000ea101';
    private const string SHARED  = '01960000-0000-7000-8000-0000000ea201';
    private const string GONE    = '01960000-0000-7000-8000-0000000ea202';
    private const string INVOICE = '01960000-0000-7000-8000-0000000ea203';
    private const string NEVER   = '01960000-0000-7000-8000-0000000ea204';

    public function testTheLastShareOfALiveReportIsItsShareNotItsLastUpdate(): void
    {
        $this->save('01960000-0000-7000-8000-0000000ea301', DocumentType::REPORT, self::SHARED, PublicationStatus::AVAILABLE, '2026-10-31 09:30');
        $this->save('01960000-0000-7000-8000-0000000ea302', DocumentType::REPORT, self::GONE, PublicationStatus::WITHDRAWN, '2026-10-31 09:30');
        $this->save('01960000-0000-7000-8000-0000000ea303', DocumentType::INVOICE, self::INVOICE, PublicationStatus::AVAILABLE, '2026-10-31 09:30');

        $model    = new DbalPublishedDocumentReadModel($this->connection());
        $sharedAt = $model->reportSharedAt(self::CLINIC, self::SHARED);

        self::assertSame(['2026-10-31 09:30', 'UTC'], [$sharedAt?->format('Y-m-d H:i'), $sharedAt?->getTimezone()->getName()]);
        self::assertNull($model->reportSharedAt(self::CLINIC2, self::SHARED), 'other clinic');
        self::assertNull($model->reportSharedAt(self::CLINIC, self::GONE), 'withdrawn');
        self::assertNull($model->reportSharedAt(self::CLINIC, self::INVOICE), 'not a report');
        self::assertNull($model->reportSharedAt(self::CLINIC, self::NEVER), 'never shared');
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }

    private function save(string $id, DocumentType $type, string $source, PublicationStatus $status, string $sharedAt): void
    {
        $utc = new \DateTimeZone('UTC');
        new DbalPublishedDocumentRepository($this->connection())->save(PublishedDocument::reconstitute(
            PublishedDocumentId::fromString($id),
            self::CLINIC,
            self::CLIENT,
            $type,
            $source,
            null,
            null,
            [],
            'Compte rendu du 30/10/2026',
            new \DateTimeImmutable('2026-10-30 12:00', $utc),
            null,
            null,
            0,
            1,
            0,
            $status,
            new \DateTimeImmutable('2026-10-30 12:05', $utc),
            // A later update (a file attached, pets assigned) is not a share
            new \DateTimeImmutable('2026-11-01 10:00', $utc),
            new \DateTimeImmutable($sharedAt, $utc),
        ));
    }
}
