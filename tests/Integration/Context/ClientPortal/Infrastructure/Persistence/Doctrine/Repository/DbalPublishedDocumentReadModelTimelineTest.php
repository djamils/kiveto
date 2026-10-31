<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Domain\Document\DocumentType as T;
use App\Context\ClientPortal\Domain\Document\PublicationStatus as S;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalClientLinkRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentReadModel;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The timeline reads of the registry: a pet's report rows and the documents
 * of their visits, scoped to the account's confirmed clients (a pet re-homed
 * from client A to client B shows each only the reports addressed to them).
 */
final class DbalPublishedDocumentReadModelTimelineTest extends KernelTestCase
{
    private const string USER     = '01960000-0000-7000-8000-0000000fa101';
    private const string CLINIC   = '01960000-0000-7000-8000-0000000fb101';
    private const string CLIENT   = '01960000-0000-7000-8000-0000000fc101';
    private const string FORMER   = '01960000-0000-7000-8000-0000000fc102';
    private const string PET      = '01960000-0000-7000-8000-0000000fd101';
    private const string OTHERPET = '01960000-0000-7000-8000-0000000fd102';
    private const string V1       = '01960000-0000-7000-8000-0000000fe101';
    private const string V2       = '01960000-0000-7000-8000-0000000fe102';
    private const string V3       = '01960000-0000-7000-8000-0000000fe103';
    private const string V4       = '01960000-0000-7000-8000-0000000fe104';
    private const string V5       = '01960000-0000-7000-8000-0000000fe105';

    // Reports of PET addressed to CLIENT, newest first: R5 (V5), R1 (V1).
    private const string R1 = '01960000-0000-7000-8000-0000000ff101';
    private const string R5 = '01960000-0000-7000-8000-0000000ff105';
    // Never in the account's timeline of PET.
    private const string R2 = '01960000-0000-7000-8000-0000000ff102';
    private const string R3 = '01960000-0000-7000-8000-0000000ff103';
    private const string R4 = '01960000-0000-7000-8000-0000000ff104';
    // Other documents of the visits.
    private const string INVOICE       = '01960000-0000-7000-8000-0000000ff201';
    private const string FORMERINVOICE = '01960000-0000-7000-8000-0000000ff202';
    private const string PRESCRIPTION  = '01960000-0000-7000-8000-0000000ff203';

    private DbalPublishedDocumentReadModel $model;

    protected function setUp(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->model = new DbalPublishedDocumentReadModel($connection);

        $now = new \DateTimeImmutable('2026-10-29 09:00', new \DateTimeZone('UTC'));
        new DbalClientLinkRepository($connection)->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000f9101'), self::USER, self::CLINIC, self::CLIENT, $now));

        $documents = new DbalPublishedDocumentRepository($connection);
        $documents->save(self::document(self::R1, T::REPORT, self::V1, '2026-09-10 10:00', 'Dr Durand'));
        $documents->save(self::document(self::R5, T::REPORT, self::V5, '2026-10-20 10:00', null));
        $documents->save(self::document(self::R2, T::REPORT, self::V2, '2026-10-25 10:00', 'Dr Ancien', client: self::FORMER));
        $documents->save(self::document(self::R3, T::REPORT, self::V3, '2026-10-26 10:00', 'Dr Durand', S::WITHDRAWN));
        $documents->save(self::document(self::R4, T::REPORT, self::V4, '2026-10-27 10:00', 'Dr Durand', pets: [self::OTHERPET]));
        $documents->save(self::document(self::INVOICE, T::INVOICE, self::V1, '2026-09-10 11:00', null));
        $documents->save(self::document(self::FORMERINVOICE, T::INVOICE, self::V1, '2026-09-10 12:00', null, client: self::FORMER));
        $documents->save(self::document(self::PRESCRIPTION, T::PRESCRIPTION, self::V1, '2026-09-10 10:30', 'Dr Durand'));
    }

    public function testThePetsReportsAddressedToTheAccountNewestFirst(): void
    {
        $reports = $this->model->listable(self::USER, self::CLINIC, T::REPORT->value, self::PET, null, 10);

        self::assertSame([[self::R5, self::V5, null], [self::R1, self::V1, 'Dr Durand']], array_map(
            static fn (ListedDocument $d): array => [$d->id, $d->consultationId, $d->practitionerName],
            $reports,
        ));

        $after = new DocumentPosition(new \DateTimeImmutable('2026-10-20 10:00', new \DateTimeZone('UTC')), self::R5);
        self::assertSame([self::R1], array_map(
            static fn (ListedDocument $d): string => $d->id,
            $this->model->listable(self::USER, self::CLINIC, T::REPORT->value, self::PET, $after, 10),
        ));
    }

    public function testTheDocumentsOfTheVisitsAddressedToTheAccount(): void
    {
        $documents = $this->model->listableOfVisits(self::USER, self::CLINIC, [self::V1, self::V2, self::V3]);

        self::assertSame(
            [[self::INVOICE, 'invoice', self::V1], [self::PRESCRIPTION, 'prescription', self::V1], [self::R1, 'report', self::V1]],
            array_map(static fn (ListedDocument $d): array => [$d->id, $d->type, $d->consultationId], $documents),
        );
        self::assertSame([], $this->model->listableOfVisits(self::USER, self::CLINIC, []));
        self::assertSame([], $this->model->listableOfVisits(self::USER, self::CLINIC, [self::V2]), 'addressed to the former owner');
    }

    /**
     * @param list<string> $pets
     */
    private static function document(
        string $id,
        T $type,
        string $consultationId,
        string $issuedAt,
        ?string $practitioner,
        S $status = S::AVAILABLE,
        string $client = self::CLIENT,
        array $pets = [self::PET],
    ): PublishedDocument {
        $utc = new \DateTimeZone('UTC');

        return PublishedDocument::reconstitute(
            PublishedDocumentId::fromString($id),
            self::CLINIC,
            $client,
            $type,
            T::REPORT === $type ? $consultationId : $id,
            $consultationId,
            null,
            $pets,
            'Document ' . $id,
            new \DateTimeImmutable($issuedAt, $utc),
            $practitioner,
            'client_portal/documents/' . $id . '-v1.pdf',
            1,
            null,
            0,
            $status,
            new \DateTimeImmutable($issuedAt, $utc),
            new \DateTimeImmutable($issuedAt, $utc),
        );
    }
}
